<?php

namespace BlueFission\Automata\LLM\Agent\Response;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\Response\ResponseComposer;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;
use BlueFission\Automata\Support\RecordSnapshot;
use Closure;
use InvalidArgumentException;
use LogicException;
use Throwable;

/** Synchronous production and observation; neither a tool executor nor an authority source. */
final class AgentResponse
{
    private ResponseComposer $composer;
    private TaskTrace $trace;
    private string $sessionId;
    private bool $producing = false;
    private array $telemetryErrors = [];

    public function __construct(private Agent $agent, ResponseEnvelope $envelope, ?ResponsePolicy $policy = null)
    {
        $this->composer = new ResponseComposer($envelope, $policy ?? new ResponsePolicy());
        $this->sessionId = $agent->session()->id();
        $this->trace = $agent->taskTrace();
    }

    /** Adapt a producer to the existing orchestration worker signature. */
    public function worker(string $fragmentId, callable $producer): Closure
    {
        return fn (array $context = [], array $priorResults = []): array => $this->produce($fragmentId, $producer, $context, $priorResults);
    }

    /** Producers must explicitly return completed/failed plus output; confidence is optional. */
    public function produce(string $fragmentId, callable $producer, array $context = [], array $priorResults = []): array
    {
        $this->idle();
        $state = $this->composer->state();
        // Validate identity, cancellation and capacity before invoking caller code, without a real transition.
        $probe = clone $this->composer;
        $probe->progress($fragmentId, $state['fragments'][$fragmentId]['completion'] ?? 0.0);
        $this->producing = true;
        try {
            $result = $producer($context, $priorResults);
            $this->bound();
            if ($this->composer->state()['cancelled']) {
                $result = ['status' => 'cancelled', 'output' => null, 'confidence' => null];
            } else {
                $result = RecordSnapshot::copy($result);
                if (!Arr::is($result) || !Arr::hasKey($result, 'output')
                    || !Arr::has(['completed', 'failed'], $result['status'] ?? null, true)) {
                    throw new InvalidArgumentException('Producer requires explicit completed/failed status and output.');
                }
                if ($result['status'] === 'completed') {
                    $this->composer->resolve($fragmentId, $result['output'], $result['confidence'] ?? null);
                } else {
                    $this->composer->fail($fragmentId, 'producer_failed');
                }
                $result['confidence'] = $result['status'] === 'completed' ? ($result['confidence'] ?? null) : null;
            }
        } catch (Throwable $error) {
            if (!$this->composer->state()['cancelled']) { $this->composer->fail($fragmentId, 'producer_exception'); }
            $this->observe('produce', 'failed', ['fragment_id' => $fragmentId, 'error_type' => $error::class]);
            throw $error;
        } finally {
            $this->producing = false;
        }
        $this->observe('produce', $result['status'], ['fragment_id' => $fragmentId]);
        return $result;
    }

    public function progress(string $id, mixed $completion): self
    {
        $this->idle();
        $this->composer->progress($id, $completion);
        $this->observe('progress', 'running', ['fragment_id' => $id, 'completion' => $completion]);
        return $this;
    }

    public function resolve(string $id, mixed $payload, mixed $confidence = null): self
    {
        $this->idle();
        $this->composer->resolve($id, $payload, $confidence);
        $this->observe('resolve', 'completed', ['fragment_id' => $id]);
        return $this;
    }

    public function fail(string $id, string $reason): self
    {
        $this->idle();
        $this->composer->fail($id, $reason);
        $this->observe('fail', 'failed', ['fragment_id' => $id]);
        return $this;
    }

    public function prepare(int $nowMs): ?array
    {
        $this->idle();
        $previous = $this->composer->state()['pending_release_id'];
        $release = $this->composer->prepare($nowMs);
        if ($release !== null && $previous !== $release['id']) {
            $this->observe('prepare', 'prepared', ['release_id' => $release['id'],
                'fragment_ids' => Arr::make($release['fragments'])->map(static fn (array $fragment): string => $fragment['id'])->values()->val()]);
        }
        return $release;
    }

    public function acknowledge(string $releaseId, array $receipts): bool
    {
        $this->idle();
        $applied = $this->composer->acknowledge($releaseId, $receipts);
        if ($applied) {
            $this->observe('acknowledge', 'acknowledged', ['release_id' => $releaseId,
                'successful' => !Arr::has(Arr::make($receipts)->map(static fn (array $receipt): bool => $receipt['successful'])->val(), false, true)]);
        }
        return $applied;
    }

    /** Cancellation may arrive from a running producer; its late output is discarded. */
    public function cancel(string $reason): self
    {
        $this->bound();
        $wasCancelled = $this->composer->state()['cancelled'];
        $this->composer->cancel($reason);
        if (!$wasCancelled) { $this->observe('cancel', 'cancelled'); }
        return $this;
    }

    public function state(): array { return $this->composer->state(); }

    /** These bounded diagnostics describe observation loss, never work failure or retry permission. */
    public function telemetryErrors(): array { return $this->telemetryErrors; }

    public function snapshot(): array
    {
        $this->idle();
        return ['schema_version' => 1, 'session_id' => $this->sessionId, 'task_id' => $this->trace->taskId(),
            'composer' => $this->composer->snapshot()];
    }

    public static function restore(Agent $agent, array $checkpoint): self
    {
        $checkpoint = RecordSnapshot::copy($checkpoint);
        if (($checkpoint['schema_version'] ?? null) !== 1 || Arr::count($checkpoint) !== 4
            || !Arr::is($checkpoint['composer'] ?? null)) {
            throw new InvalidArgumentException('Malformed Agent response checkpoint.');
        }
        if (($checkpoint['session_id'] ?? null) !== $agent->session()->id()
            || ($checkpoint['task_id'] ?? null) !== $agent->taskId()) {
            throw new LogicException('Agent response scope does not match the current session and task.');
        }
        $composer = ResponseComposer::restore($checkpoint['composer']);
        $envelope = $composer->snapshot()['envelope'];
        $response = new self($agent, new ResponseEnvelope($envelope['id'],
            Arr::make($envelope['fragments'])->map(static fn (array $fragment): ResponseFragment => new ResponseFragment($fragment))->values()->val(),
            $envelope['trace']));
        $response->composer = $composer;
        return $response;
    }

    private function bound(): void
    {
        if ($this->agent->session()->id() !== $this->sessionId || $this->agent->taskTrace() !== $this->trace) {
            throw new LogicException('Agent response scope changed; bind the correct session and task before continuing.');
        }
    }

    private function idle(): void
    {
        $this->bound();
        if ($this->producing) { throw new LogicException('An active response producer cannot be reentered or checkpointed.'); }
    }

    private function observe(string $event, string $status, array $metadata = []): void
    {
        try {
            $this->trace->recordTaskCall(TaskTraceSpan::KIND_ORCHESTRATION, 'response.' . $event, [], [],
                ['response_id' => $this->composer->state()['response_id'], 'session_id' => $this->sessionId,
                    'status' => $status, ...$metadata]);
        } catch (Throwable $error) {
            if (Arr::count($this->telemetryErrors) < 32) {
                $this->telemetryErrors[] = ['event' => $event, 'error_type' => $error::class];
            }
        }
    }
}
