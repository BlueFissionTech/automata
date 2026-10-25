<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Strategy\Routing\{StrategyRouter, StrategyRouteRequest, IStrategyDispatchGuard, StrategyDispatchContext, StrategyDispatchDecision};
use BlueFission\Automata\Support\{RecordSnapshot, SynchronousCallback};
use Closure;
use InvalidArgumentException;
use LogicException;
use Throwable;

/** Single-owner state; execute calls may overlap through a host's cooperative scheduler. */
final class StrategyWorkflowRun
{
    private array $plan;
    private array $configs = [];
    private array $nodes = [];
    private array $outputs = [];
    private mixed $input;
    private string $status = 'running';
    private int $dispatches = 0;
    private Closure $authorize;
    private ?WorkflowAdmissionPolicy $admission = null;
    private ?Closure $clock = null;
    private ?int $deadlineNanoseconds = null;
    private ?int $lastClock = null;
    private array $reservations = [];
    private array $executionAttempts = [];
    private array $invocations = [];
    private array $lastAdmission = [];
    private string $admissionStopCode = '';

    /** Additive factory keeps legacy constructors and named arguments untouched. */
    public static function admitted(StrategyWorkflow $workflow, StrategyRouter $router, callable $authorize,
        string $subjectId, mixed $input, string $id, WorkflowAdmissionPolicy $policy, callable $clock,
        mixed $deadlineNanoseconds = null, int $maximumDispatches = 256, string $contextKey = '', ?TaskTrace $trace = null): self
    {
        // Validate before storage or any host-supplied router callback. A ?int
        // parameter would already have coerced invalid weak-mode input here.
        if ($deadlineNanoseconds !== null && (!Num::isInt($deadlineNanoseconds) || $deadlineNanoseconds < 0)) {
            throw new InvalidArgumentException('Monotonic deadline must be null or a nonnegative integer.');
        }
        if (!$router->supportsGuardedExecution()) { throw new LogicException('Custom router must explicitly support guarded execution.'); }
        $run = new self($workflow, $router, $authorize, $subjectId, $input, $id, $maximumDispatches, $contextKey, $trace);
        $run->admission = $policy;
        $run->clock = Closure::fromCallable($clock);
        $run->deadlineNanoseconds = $deadlineNanoseconds;
        return $run;
    }

    public function __construct(StrategyWorkflow $workflow, private readonly StrategyRouter $router, callable $authorize,
        private readonly string $subjectId, mixed $input, private readonly string $id,
        private readonly int $maximumDispatches = 256, private readonly string $contextKey = '', private readonly ?TaskTrace $trace = null)
    {
        RecordSnapshot::identifier($id, 'run id');
        RecordSnapshot::identifier($subjectId, 'subject id');
        if ($maximumDispatches < 1 || $maximumDispatches > 1024) { throw new InvalidArgumentException('Dispatch limit must be 1..1024.'); }
        // Reserve depth for normalized inputs, attempt receipts and result envelopes.
        $this->input = RecordSnapshot::copy($input, 8);
        $this->authorize = Closure::fromCallable($authorize);
        $this->plan = $workflow->toArray();
        foreach ($this->plan['nodes'] as $node) {
            $this->configs[$node['id']] = $node['config'];
            $this->nodes[$node['id']] = ['status' => 'pending', 'code' => 'pending', 'output' => null, 'attempts' => []];
        }
    }

    public function ready(): array
    {
        $this->refreshAdmission();
        $this->advance();
        if ($this->status !== 'running' || $this->dispatches >= $this->maximumDispatches) { return []; }
        if ($this->admission && Arr::size($this->reservations) >= $this->admission->maximumInFlight) { return []; }
        return Arr::make($this->nodes)->filter(fn ($node, $id) => $this->available($id) && $this->eligibility($id) === true)->keys()->val();
    }

    /** Executes one attempt. Authorization is refreshed for every retry and every node. */
    public function execute(string $nodeId): self
    {
        $this->refreshAdmission();
        $this->advance();
        if ($this->admission && $this->status === 'running' && isset($this->nodes[$nodeId])
            && $this->available($nodeId) && $this->eligibility($nodeId) === true
            && Arr::size($this->reservations) >= $this->admission->maximumInFlight) {
            // Refusal is not a scheduling attempt. Do not allocate a reservation,
            // invoke authorization or consume a known-failure retry allowance.
            $this->lastAdmission = ['node_id' => $nodeId, 'code' => 'capacity_unavailable'];
            return $this;
        }
        if (!Arr::make($this->ready())->has($nodeId, true)) { throw new LogicException('Node is not eligible for dispatch.'); }
        $config = $this->configs[$nodeId];
        $parents = [];
        foreach ($this->incoming($nodeId) as $edge) {
            if ($this->matches($edge)) {
                $parent = $this->nodes[$edge['from']];
                $parents[$edge['from']] = ['status' => $parent['status'], 'code' => $parent['code'], 'output' => $parent['output']];
            }
        }
        $attempt = Arr::size($this->nodes[$nodeId]['attempts']) + 1;
        $request = new StrategyRouteRequest([
            'id' => $this->id . ':' . $nodeId . ':' . $attempt, 'subject_id' => $this->subjectId,
            'capability_id' => $config['capability_id'], 'capability_version' => $config['capability_version'],
            // The proposal owns the candidate set; the advisor only ranks it.
            // Legacy nodes keep exactly their previous single-strategy behavior.
            'candidates' => $config['candidates'] ?? [['id' => $config['strategy_id'], 'version' => $config['strategy_version']]],
            'selection_policy' => $config['selection_policy'] ?? StrategyRouteRequest::SELECTION_DECLARED,
            'deterministic_preferred' => $config['deterministic_preferred'] ?? true,
            'allowed_modes' => $config['allowed_modes'], 'limits' => $config['limits'],
            'input' => ['root' => $this->input, 'predecessors' => $parents], 'context_key' => $this->contextKey,
            'correlation_id' => $this->id, 'trace_id' => $this->trace?->taskId() ?? '',
            'metadata' => ['workflow_id' => $this->plan['id'], 'workflow_version' => $this->plan['version'], 'node_id' => $nodeId, 'attempt' => $attempt],
        ]);
        ++$this->dispatches;
        $this->nodes[$nodeId]['status'] = 'running';
        $scope = [];
        // Preserve the admitted identity before any host callback can mutate a
        // route request. Receipt construction must not depend on returned data.
        $requestDigest = $this->admission ? RecordSnapshot::fingerprint($request->toArray()) : null;
        $priorInvocations = $this->invocations[$nodeId] ?? 0;
        if ($this->admission) {
            // No callback/suspension between eligibility, reservation and running.
            $scope = ['run_id' => $this->id, 'node_id' => $nodeId, 'scheduling_attempt' => $attempt,
                'reservation_token' => $request->id . ':reservation', 'context_key' => $this->contextKey,
                'context_digest' => RecordSnapshot::fingerprint($request->input),
                'policy_version' => $this->admission->version];
            $this->reservations[$nodeId] = $scope['reservation_token'];
            $this->lastAdmission = ['node_id' => $nodeId, 'code' => 'reserved'];
        }
        try {
            $started = hrtime(true);
            $receipt = ['request_id' => $request->id, 'status' => 'denied', 'code' => 'authorization_invalid',
                'input_fingerprint' => RecordSnapshot::fingerprint($request->input), 'authorization' => null,
                'output' => null, 'selected_strategy' => null, 'error_type' => null, 'elapsed_ms' => null];
            $invoking = false;
            try {
                $authorization = ($this->authorize)(RecordSnapshot::copy($request->toArray()));
                if ($this->status !== 'running') {
                    $receipt['status'] = 'skipped';
                    $receipt['code'] = $this->admission ? $this->stopCode() : 'dispatch_stopped';
                } elseif ($authorization instanceof AutonomyDecision) {
                    $receipt['authorization'] = RecordSnapshot::copy($authorization->toArray(), 8);
                    $authorization = new AutonomyDecision($receipt['authorization']);
                    $invoking = true;
                    $route = $this->admission
                        ? $this->router->routeGuarded($request, $authorization, $this->dispatchGuard($nodeId, $scope, $request), $scope, $this->trace)
                        : $this->router->route($request, $authorization, $this->trace);
                    $receipt['code'] = $route->code;
                    $receipt['status'] = match ($route->status) { 'completed' => 'completed', 'failed' => 'failed', default => 'denied' };
                    if (Arr::make([StrategyRouter::CODE_EXECUTION_EXCEPTION, StrategyRouter::CODE_ACTUAL_BUDGET_EXCEEDED])->has($route->code, true)) { $receipt['status'] = 'uncertain'; }
                    // Do not retain arbitrary exception diagnostics or coerce runtime output into evidence.
                    $receipt['output'] = RecordSnapshot::copy($route->output, 8);
                    $receipt['selected_strategy'] = RecordSnapshot::copy($route->selected_strategy, 8);
                }
            } catch (Throwable $error) {
                if ($this->admission) { $invoking = ($this->invocations[$nodeId] ?? 0) > $priorInvocations; }
                $receipt['status'] = $invoking ? 'uncertain' : 'denied';
                $receipt['code'] = $invoking ? 'execution_uncertain' : 'authorization_exception';
                $receipt['error_type'] = $error::class;
            }
            if ($this->admission) {
                // Returning uncertainty settles this attempt; a different suspended
                // worker remains reserved. Cancellation never fabricates interruption.
                $this->refreshAdmission();
                $receipt['executed'] = ($this->invocations[$nodeId] ?? 0) > $priorInvocations;
                $receipt['admission'] = (new WorkflowAdmissionReceipt([
                    ...$scope, 'schema_version' => 1, 'request_digest' => $requestDigest,
                    'dependency_provenance' => $parents, 'adapter_invocations' => ($this->invocations[$nodeId] ?? 0) - $priorInvocations,
                    'late' => $this->status !== 'running', 'cancel_observed' => $this->status === 'cancelled',
                    'deadline_observed' => $this->status === 'expired', 'executed' => $receipt['executed'], 'code' => $receipt['code'],
                    // Keep the sticky clock/expiry cause separate from the actual
                    // adapter result, even when a suspended worker returns late.
                    'stop_code' => $this->admissionStopCode ?: null,
                ]))->toArray();
            }
            $receipt['elapsed_ms'] = Num::make(hrtime(true))->subtract($started)->divide(1000000)->val();
            $this->nodes[$nodeId]['attempts'][] = $receipt;
            foreach (['status', 'code', 'output'] as $field) { $this->nodes[$nodeId][$field] = $receipt[$field]; }
            if ($receipt['status'] === 'uncertain' && (!$this->admission || $this->status === 'running')) { $this->close('uncertain'); }
            $this->advance();
            return $this;
        } finally {
            // Capacity follows real settlement, never cancellation or a winning
            // peer's status. This also covers unexpected local unwinding.
            unset($this->reservations[$nodeId]);
        }
    }

    public function cancel(): self
    {
        if ($this->status === 'running') { $this->close('cancelled'); }
        return $this;
    }

    public function result(): StrategyWorkflowResult
    {
        $this->refreshAdmission();
        $this->advance();
        return new StrategyWorkflowResult(['schema_version' => 1, 'run_id' => $this->id,
            'workflow' => ['id' => $this->plan['id'], 'version' => $this->plan['version']],
            'plan_fingerprint' => RecordSnapshot::fingerprint($this->plan), 'subject_id' => $this->subjectId,
            'context_key' => $this->contextKey, 'status' => $this->status, 'settled' => $this->status !== 'running' && !$this->running(),
            'outputs' => $this->outputs, 'nodes' => $this->nodes, 'dispatches' => $this->dispatches,
            'maximum_dispatches' => $this->maximumDispatches, 'cost' => null, 'energy' => null, 'confidence' => null,
            ...($this->admission ? ['admission' => [
                'policy' => $this->admission->toArray(), 'in_flight' => Arr::size($this->reservations),
                'scheduling_attempts' => $this->dispatches, 'execution_attempts' => $this->counterTotal($this->executionAttempts),
                'adapter_invocations' => $this->counterTotal($this->invocations), 'last_outcome' => $this->lastAdmission,
                'stop_code' => $this->admissionStopCode ?: null,
            ]] : [])]);
    }

    private function counterTotal(array $counts): int
    {
        $total = 0;
        foreach ($counts as $count) { $total = Num::plus($total, $count); }
        return $total;
    }

    /** Clock objects and runtime epochs are intentionally absent from all snapshots. */
    private function refreshAdmission(): void
    {
        if (!$this->admission || $this->status !== 'running') { return; }
        $check = SynchronousCallback::evaluate($this->clock);
        $now = $check['value'] ?? null;
        if ($check['status'] !== 'returned' || !Num::isInt($now) || $now < 0 || ($this->lastClock !== null && $now < $this->lastClock)) {
            $this->admissionStopCode = 'dispatch_clock_invalid';
            $this->close('denied');
            return;
        }
        $this->lastClock = $now;
        if ($this->status === 'running' && $this->deadlineNanoseconds !== null && $now >= $this->deadlineNanoseconds) {
            $this->admissionStopCode = 'dispatch_expired';
            $this->close('expired');
        }
    }

    private function stopCode(): string
    {
        return $this->status === 'cancelled' ? 'dispatch_cancelled' : ($this->admissionStopCode ?: 'dispatch_stopped');
    }

    private function dispatchGuard(string $nodeId, array $scope, StrategyRouteRequest $request): IStrategyDispatchGuard
    {
        $digest = RecordSnapshot::fingerprint($request->toArray());
        $invoked = false;
        $check = function (StrategyDispatchContext $context) use ($nodeId, $scope, $digest, &$invoked): StrategyDispatchDecision {
            $this->refreshAdmission();
            $identity = $context->toArray();
            if ($this->status !== 'running') { return new StrategyDispatchDecision(false, $this->stopCode()); }
            if (($this->reservations[$nodeId] ?? null) !== $scope['reservation_token']
                || $identity['request_digest'] !== $digest) { return new StrategyDispatchDecision(false, 'dispatch_scope_mismatch'); }
            foreach ($scope as $key => $value) {
                if (($identity[$key] ?? null) !== $value) { return new StrategyDispatchDecision(false, 'dispatch_scope_mismatch'); }
            }
            // Router makes no user call between this allow and adapter execute.
            // First invocation counts one execution attempt; fallback counts an
            // additional invocation, not a new scheduling attempt or retry.
            if (!$invoked) { $this->executionAttempts[$nodeId] = ($this->executionAttempts[$nodeId] ?? 0) + 1; $invoked = true; }
            $this->invocations[$nodeId] = ($this->invocations[$nodeId] ?? 0) + 1;
            return new StrategyDispatchDecision(true, 'dispatch_allowed');
        };
        return new class($check) implements IStrategyDispatchGuard {
            public function __construct(private readonly Closure $check) {}
            public function check(StrategyDispatchContext $context): StrategyDispatchDecision { return ($this->check)($context); }
        };
    }

    private function advance(): void
    {
        if ($this->status !== 'running') { return; }
        // Output ids are validated as unique strings by the plan. Preserve their
        // declared order and keys through filtering, including false/null payloads.
        $outputs = Arr::make($this->plan['outputs'])->flip()
            ->map(fn (int $index, string $id): array => $this->nodes[$id])
            ->filter(static fn (array $node): bool => $node['status'] === 'completed')
            ->map(static fn (array $node): mixed => $node['output']);
        if ($outputs->size() >= $this->plan['minimum_outputs']) {
            $this->outputs = $outputs->val();
            $this->close('completed');
            return;
        }
        do {
            $changed = false;
            foreach ($this->nodes as $id => $node) {
                if ($this->available($id) && $this->eligibility($id) === false) {
                    $this->nodes[$id]['status'] = 'skipped';
                    $this->nodes[$id]['code'] = 'dependency_not_satisfied';
                    $changed = true;
                }
            }
        } while ($changed);
        if ($this->running()) { return; }
        if ($this->dispatches >= $this->maximumDispatches) { $this->close('exhausted'); return; }
        foreach ($this->nodes as $id => $node) { if ($this->available($id)) { return; } }
        $this->close('exhausted');
    }

    private function close(string $status): void
    {
        $this->status = $status;
        foreach ($this->nodes as $id => $node) {
            if ($this->available($id)) {
                $this->nodes[$id]['status'] = 'skipped';
                $this->nodes[$id]['code'] = 'workflow_' . $status;
            }
        }
    }

    private function available(string $id): bool
    {
        $node = $this->nodes[$id];
        return $node['status'] === 'pending' || ($node['status'] === 'failed'
            && ($this->admission ? ($this->executionAttempts[$id] ?? 0) : Arr::size($node['attempts'])) < $this->configs[$id]['maximum_attempts']
            && Arr::make($this->configs[$id]['retry_codes'])->has($node['code'], true));
    }

    private function running(): bool
    {
        foreach ($this->nodes as $node) { if ($node['status'] === 'running') { return true; } }
        return false;
    }

    private function incoming(string $id): array
    {
        return Arr::make($this->plan['edges'])->filter(static fn ($edge) => $edge['to'] === $id)->values()->val();
    }

    /** Null means waiting; false means dependencies can no longer be satisfied. */
    private function eligibility(string $id): ?bool
    {
        $edges = $this->incoming($id);
        if ($edges === []) { return true; }
        $waiting = false;
        $matches = 0;
        foreach ($edges as $edge) {
            $parent = $edge['from'];
            if ($this->available($parent) || $this->nodes[$parent]['status'] === 'running') { $waiting = true; continue; }
            if ($this->matches($edge)) { ++$matches; }
            elseif ($this->configs[$id]['join'] === 'all') { return false; }
        }
        if ($this->configs[$id]['join'] === 'any' && $matches > 0) { return true; }
        return $waiting ? null : $matches === Arr::size($edges);
    }

    private function matches(array $edge): bool
    {
        $node = $this->nodes[$edge['from']];
        if ($this->available($edge['from']) || $node['status'] !== $edge['on']) { return false; }
        if (!isset($edge['when'])) { return true; }
        $value = $node['output'];
        foreach ($edge['when']['path'] as $key) {
            if (!Arr::is($value) || !Arr::make($value)->hasKey($key)) {
                return false;
            }
            $value = $value[$key];
        }
        return $value === $edge['when']['equals'];
    }
}
