<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Strategy\Script\{ScriptExecution, ScriptResult};
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Parsing\Parser;
use BlueFission\Parsing\Contracts\{IGenerator, IRenderableElement};
use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;
use WeakMap;

/**
 * Reviewed parser-backed cognition, usable by Intelligence like other strategies.
 * The host owns grammar, bindings and registry initialization. Source/version
 * identity describes a proposal, not proof that host callbacks or registries are
 * immutable. This adapter never installs or swaps process-wide parser registries.
 */
final class ScriptStrategy implements IStrategy
{
    private Closure $prepare;
    private Closure $authorize;
    private WeakMap $parsers;
    private bool $running = false;
    private ?ScriptResult $lastResult = null;

    /** Factory: (source, detached input, execution) -> fresh Parser or renderable. */
    public function __construct(private readonly string $id, private readonly string $version,
        private readonly string $source, callable $prepare, callable $authorize, private readonly string $subjectId,
        private readonly ?IGenerator $generator = null, private readonly int $maximumGenerations = 8,
        private readonly ?TaskTrace $trace = null)
    {
        RecordSnapshot::identifier($id, 'Script id');
        RecordSnapshot::identifier($version, 'Script version');
        RecordSnapshot::identifier($subjectId, 'Script subject');
        if ($maximumGenerations < 0 || $maximumGenerations > 1024) { throw new InvalidArgumentException('Generation limit must be between 0 and 1024.'); }
        $this->prepare = Closure::fromCallable($prepare);
        $this->authorize = Closure::fromCallable($authorize);
        $this->parsers = new WeakMap();
    }

    /** Stable identity for route registration and source-linked evidence. */
    public function identity(): array
    {
        return ['strategy_id' => $this->id, 'strategy_version' => $this->version,
            'source_sha256' => hash('sha256', $this->source), 'subject_id' => $this->subjectId];
    }

    /** Presence of generation capacity makes this a generative candidate, conservatively. */
    public function generative(): bool { return $this->generator !== null; }

    /** Dispatch allowance, not an assertion about generator-internal provider retries. */
    public function maximumGenerations(): int { return $this->generator ? $this->maximumGenerations : 0; }

    /**
     * Execute once, with fresh state. Reports preserve unknown cost/confidence and
     * do not automatically replay failures. Reentry/overlap on this instance is
     * denied; different instances still require host isolation of global registries.
     */
    public function run(mixed $input): ScriptResult
    {
        if ($this->running) { throw new LogicException('Script strategy is already running.'); }
        $this->running = true;
        $this->lastResult = null;
        try {
            $input = RecordSnapshot::copy($input);
            $identity = $this->identity() + ['run_id' => 'script-' . bin2hex(random_bytes(16)),
                'input_fingerprint' => RecordSnapshot::fingerprint($input)];
            $execution = new ScriptExecution($identity, $this->authorize, $this->generator, $this->maximumGenerations);
            $result = $execution->execute(function (ScriptExecution $run) use ($input): string {
                $parser = ($this->prepare)($this->source, RecordSnapshot::copy($input), $run);
                $run->checkpoint();
                if (!$parser instanceof Parser && !$parser instanceof IRenderableElement) {
                    throw new RuntimeException('Script factory must supply a Parser or IRenderableElement.');
                }
                if (isset($this->parsers[$parser])) { throw new LogicException('Script factory reused a parser.'); }
                $this->parsers[$parser] = true;
                return $parser->render();
            });
            $this->lastResult = $result;
            // Trace failure cannot erase a completed execution or justify replay.
            $telemetry = $this->trace ? 'failed' : 'not_requested';
            if ($this->trace) {
                try {
                    $span = $this->trace->startSpan('strategy', $this->id, $result->toArray());
                    $span->set('started_at', microtime(true) - $result->toArray()['elapsed_ms'] / 1000);
                    $this->trace->addSpan($span->finish($result->status()));
                    $telemetry = 'recorded';
                } catch (Throwable) { /* Preserve the result and expose telemetry failure below. */ }
            }
            return $this->lastResult = new ScriptResult($result->toArray() + ['telemetry_status' => $telemetry]);
        } finally {
            $this->running = false;
        }
    }

    /** Prediction returns only successful text; all other terminal states are explicit errors. */
    public function predict($input)
    {
        $result = $this->run($input);
        if (!$result->succeeded()) { throw new RuntimeException('Script did not complete: ' . $result->status()); }
        return $result->output();
    }

    /** Last detached report, including failed or cancelled execution. */
    public function lastResult(): ?ScriptResult { return $this->lastResult; }

    /** Scripts are versioned host code; fitting them in place is unsupported. */
    public function train(array $samples, array $labels, float $testSize = 0.2) { throw new LogicException('Script strategies are not trained in place.'); }

    /** Deterministic execution does not imply empirically measured accuracy. */
    public function accuracy(): float { throw new LogicException('Script accuracy requires independent evaluation.'); }

    /** Host bindings and authorization cannot be serialized as a model file. */
    public function saveModel(string $path): bool { throw new LogicException('Persist reviewed source/version; supply host bindings separately.'); }

    /** Restoring a file must never restore executable authority implicitly. */
    public function loadModel(string $path): bool { throw new LogicException('Construct a new reviewed script and host bindings explicitly.'); }
}
