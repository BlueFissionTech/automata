<?php

namespace BlueFission\Automata\Strategy\Workflow;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Strategy\Routing\{StrategyRouter, StrategyRouteRequest};
use BlueFission\Automata\Support\RecordSnapshot;
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
        $this->advance();
        if ($this->status !== 'running' || $this->dispatches >= $this->maximumDispatches) { return []; }
        return Arr::make($this->nodes)->filter(fn ($node, $id) => $this->available($id) && $this->eligibility($id) === true)->keys()->val();
    }

    /** Executes one attempt. Authorization is refreshed for every retry and every node. */
    public function execute(string $nodeId): self
    {
        if (!Arr::make($this->ready())->has($nodeId, true)) { throw new LogicException('Node is not eligible for dispatch.'); }
        $config = $this->configs[$nodeId];
        $parents = [];
        foreach ($this->incoming($nodeId) as $edge) {
            if ($this->matches($edge)) {
                $parent = $this->nodes[$edge['from']];
                $parents[$edge['from']] = ['status' => $parent['status'], 'code' => $parent['code'], 'output' => $parent['output']];
            }
        }
        $attempt = Arr::make($this->nodes[$nodeId]['attempts'])->count() + 1;
        $request = new StrategyRouteRequest([
            'id' => $this->id . ':' . $nodeId . ':' . $attempt, 'subject_id' => $this->subjectId,
            'capability_id' => $config['capability_id'], 'capability_version' => $config['capability_version'],
            'candidates' => [['id' => $config['strategy_id'], 'version' => $config['strategy_version']]],
            'allowed_modes' => $config['allowed_modes'], 'limits' => $config['limits'],
            'input' => ['root' => $this->input, 'predecessors' => $parents], 'context_key' => $this->contextKey,
            'correlation_id' => $this->id, 'trace_id' => $this->trace?->taskId() ?? '',
            'metadata' => ['workflow_id' => $this->plan['id'], 'workflow_version' => $this->plan['version'], 'node_id' => $nodeId, 'attempt' => $attempt],
        ]);
        ++$this->dispatches;
        $this->nodes[$nodeId]['status'] = 'running';
        $started = hrtime(true);
        $receipt = ['request_id' => $request->id, 'status' => 'denied', 'code' => 'authorization_invalid',
            'input_fingerprint' => RecordSnapshot::fingerprint($request->input), 'authorization' => null,
            'output' => null, 'selected_strategy' => null, 'error_type' => null, 'elapsed_ms' => null];
        $invoking = false;
        try {
            $authorization = ($this->authorize)(RecordSnapshot::copy($request->toArray()));
            if ($this->status !== 'running') {
                $receipt['status'] = 'skipped';
                $receipt['code'] = 'dispatch_stopped';
            } elseif ($authorization instanceof AutonomyDecision) {
                $receipt['authorization'] = RecordSnapshot::copy($authorization->toArray(), 8);
                $authorization = new AutonomyDecision($receipt['authorization']);
                $invoking = true;
                $route = $this->router->route($request, $authorization, $this->trace);
                $receipt['code'] = $route->code;
                $receipt['status'] = match ($route->status) { 'completed' => 'completed', 'failed' => 'failed', default => 'denied' };
                if (Arr::make([StrategyRouter::CODE_EXECUTION_EXCEPTION, StrategyRouter::CODE_ACTUAL_BUDGET_EXCEEDED])->has($route->code, true)) { $receipt['status'] = 'uncertain'; }
                // Do not retain arbitrary exception diagnostics or coerce runtime output into evidence.
                $receipt['output'] = RecordSnapshot::copy($route->output, 8);
                $receipt['selected_strategy'] = RecordSnapshot::copy($route->selected_strategy, 8);
            }
        } catch (Throwable $error) {
            $receipt['status'] = $invoking ? 'uncertain' : 'denied';
            $receipt['code'] = $invoking ? 'execution_uncertain' : 'authorization_exception';
            $receipt['error_type'] = $error::class;
        }
        $receipt['elapsed_ms'] = Num::make(hrtime(true))->subtract($started)->divide(1000000)->val();
        $this->nodes[$nodeId]['attempts'][] = $receipt;
        foreach (['status', 'code', 'output'] as $field) { $this->nodes[$nodeId][$field] = $receipt[$field]; }
        if ($receipt['status'] === 'uncertain') { $this->close('uncertain'); }
        $this->advance();
        return $this;
    }

    public function cancel(): self
    {
        if ($this->status === 'running') { $this->close('cancelled'); }
        return $this;
    }

    public function result(): StrategyWorkflowResult
    {
        $this->advance();
        return new StrategyWorkflowResult(['schema_version' => 1, 'run_id' => $this->id,
            'workflow' => ['id' => $this->plan['id'], 'version' => $this->plan['version']],
            'plan_fingerprint' => RecordSnapshot::fingerprint($this->plan), 'subject_id' => $this->subjectId,
            'context_key' => $this->contextKey, 'status' => $this->status, 'settled' => $this->status !== 'running' && !$this->running(),
            'outputs' => $this->outputs, 'nodes' => $this->nodes, 'dispatches' => $this->dispatches,
            'maximum_dispatches' => $this->maximumDispatches, 'cost' => null, 'energy' => null, 'confidence' => null]);
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
        if ($outputs->count() >= $this->plan['minimum_outputs']) {
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
            && Arr::make($node['attempts'])->count() < $this->configs[$id]['maximum_attempts']
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
        return $waiting ? null : $matches === Arr::make($edges)->count();
    }

    private function matches(array $edge): bool
    {
        $node = $this->nodes[$edge['from']];
        if ($this->available($edge['from']) || $node['status'] !== $edge['on']) { return false; }
        if (!isset($edge['when'])) { return true; }
        $value = $node['output'];
        foreach ($edge['when']['path'] as $key) {
            if (!is_array($value) || !Arr::make($value)->hasKey($key)) { return false; }
            $value = $value[$key];
        }
        return $value === $edge['when']['equals'];
    }
}
