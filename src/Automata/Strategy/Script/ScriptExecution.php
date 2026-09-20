<?php

namespace BlueFission\Automata\Strategy\Script;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Parsing\Contracts\IGenerator;
use BlueFission\Parsing\Element;
use Closure;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * One synchronous execution's generator and control handle. This is a capability
 * boundary for cooperating host code, not isolation from arbitrary PHP code.
 * No live handle, authorization callback or generator is serialized in reports.
 */
final class ScriptExecution implements IGenerator
{
    private string $status = 'pending';
    private ?string $output = null;
    private ?string $diagnostic = null;
    private bool $closed = false;
    private bool $busy = false;
    private array $authorizations = [];
    private array $generations = [];
    private Closure $authorize;

    /** @internal ScriptStrategy creates a fresh handle for every admitted invocation. */
    public function __construct(private readonly array $identity, callable $authorize,
        private readonly ?IGenerator $generator, private readonly int $maximumGenerations)
    {
        $this->authorize = Closure::fromCallable($authorize);
    }

    /**
     * Authorize before host preparation/rendering. Exceptions become explicit
     * uncertainty, since arbitrary host work may already have executed. A caught
     * denial/cancellation remains latched and cannot become fabricated success.
     * @internal Only the owning strategy should call this single-use entrypoint.
     */
    public function execute(callable $render): ScriptResult
    {
        if ($this->closed || $this->status !== 'pending') { throw new LogicException('Script execution is single-use.'); }
        $started = hrtime(true);
        $this->status = 'running';
        try {
            $this->authorizeOperation('execute');
            $this->assertRunning();
            $output = $render($this);
            if ($this->status === 'running') {
                if (!is_string($output)) { throw new RuntimeException('Script renderer must return a string.'); }
                $this->output = $output;
                $this->status = 'completed';
            }
        } catch (ScriptExit) {
            // Merely throwing the signal does not constitute an approved finish.
            if ($this->status !== 'early_exit') { $this->fail('unexpected_exit'); }
        } catch (Throwable $error) {
            if ($this->status === 'running' || $this->status === 'early_exit') {
                $this->fail(get_class($error));
            }
        } finally {
            $this->closed = true;
        }
        return new ScriptResult($this->identity + [
            'status' => $this->status, 'output' => $this->output, 'diagnostic' => $this->diagnostic,
            'authorizations' => $this->authorizations, 'generations' => $this->generations,
            'generation_calls' => Arr::count(Arr::make($this->generations)->filter(static fn ($g) => $g['dispatched'])->val()),
            'elapsed_ms' => (int) ceil((hrtime(true) - $started) / 1e6),
            'cost' => null, 'confidence' => null,
        ]);
    }

    /**
     * Wrap the existing generator seam with fresh authorization and a call limit.
     * Completed output is hashed, not copied into receipts. A transport exception
     * retains an uncertain attempt and prevents further calls through this handle.
     */
    public function generate(Element $element): string
    {
        $this->assertRunning();
        if (!$this->generator || count($this->generations) >= $this->maximumGenerations) {
            $this->deny($this->generator ? 'generation_limit' : 'generator_unavailable');
        }
        $index = count($this->generations);
        $this->generations[] = ['ordinal' => $index + 1, 'status' => 'pending', 'dispatched' => false];
        try {
            $attributes = RecordSnapshot::copy($element->getAttributes());
            $this->authorizeOperation('generate', ['generation_index' => $index + 1,
                'element_tag' => $element->getTag(), 'attributes_fingerprint' => RecordSnapshot::fingerprint($attributes)]);
            $this->assertRunning();
            // Guard authorization and generator callbacks against nested dispatch.
            $this->busy = true;
            $this->generations[$index]['dispatched'] = true;
            $output = $this->generator->generate($element);
            $this->generations[$index]['status'] = 'completed';
            $this->generations[$index]['output_sha256'] = hash('sha256', $output);
        } catch (Throwable $error) {
            $this->generations[$index]['status'] = $this->generations[$index]['dispatched'] ? 'uncertain' : 'denied';
            if ($this->status === 'running') { $this->fail(get_class($error)); }
            throw $error;
        } finally {
            $this->busy = false;
        }
        // Cancellation preserves the completed receipt while unwinding the
        // parser before subsequent optional work. Do not relabel it uncertain.
        $this->checkpoint();
        return $output;
    }

    /** Stop optional work without erasing an already dispatched generator's receipt. */
    public function cancel(): self
    {
        if ($this->closed) { throw new LogicException('Script execution is closed.'); }
        if ($this->status === 'running') { $this->status = 'cancelled'; $this->output = null; }
        return $this;
    }

    /** Explicit terminal output: caller code must let the unwind signal propagate. */
    public function finish(string $output): never
    {
        $this->assertRunning();
        $this->output = $output;
        $this->status = 'early_exit';
        throw new ScriptExit('Script finished early.');
    }

    /** Recheck terminal state at a parser boundary, including a caught early exit. */
    public function checkpoint(): void
    {
        if (!$this->closed && $this->status === 'early_exit') { throw new ScriptExit('Script already finished.'); }
        $this->assertRunning();
    }

    /** Ask the host for a decision bound to this operation, actor and version. */
    private function authorizeOperation(string $operation, array $details = []): void
    {
        $request = $this->identity + ['operation' => $operation,
            'capability_id' => $this->identity['strategy_id'] . '.' . $operation,
            'capability_version' => $this->identity['strategy_version']] + $details;
        $this->busy = true;
        try {
            $decision = ($this->authorize)(RecordSnapshot::copy($request));
            $allowed = $decision instanceof AutonomyDecision && $decision->allowed === true
                && $decision->subject_id === $request['subject_id']
                && $decision->capability_id === $request['capability_id']
                && $decision->capability_version === $request['capability_version'];
            // Only dispatch reservations are measurable here. Never silently
            // discard a grant's unsupported monetary, energy or time ceiling.
            if ($allowed) {
                foreach ($decision->limits as $key => $limit) {
                    $reservation = $operation === 'execute' ? 1 + ($this->generator ? $this->maximumGenerations : 0) : 1;
                    if ($key !== 'max_invocations' || (!is_int($limit) && !is_float($limit))
                        || !is_finite((float) $limit) || $limit < $reservation) { $allowed = false; }
                }
            }
            $this->authorizations[] = ['operation' => $operation, 'allowed' => $allowed,
                'capability_id' => $request['capability_id'], 'generation_index' => $details['generation_index'] ?? null];
            if (!$allowed) { $this->deny('authorization_denied'); }
        } finally {
            $this->busy = false;
        }
    }

    /** Guard closed/terminal executions and reentrant generation/authorization. */
    private function assertRunning(): void
    {
        if ($this->closed || $this->status !== 'running' || $this->busy) {
            throw new LogicException('Script execution is not available for further work.');
        }
    }

    /** Denial is latched before throwing so catching cannot restore authority. */
    private function deny(string $code): never
    {
        $this->status = 'denied'; $this->output = null; $this->diagnostic = $code;
        throw new RuntimeException('Script operation denied: ' . $code);
    }

    /** Preserve unknown effects without exposing exception messages or private inputs. */
    private function fail(string $code): void
    {
        $this->status = 'uncertain'; $this->output = null; $this->diagnostic = $code;
    }
}
