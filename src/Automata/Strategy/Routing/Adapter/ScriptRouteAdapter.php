<?php

namespace BlueFission\Automata\Strategy\Routing\Adapter;

use BlueFission\Arr;
use BlueFission\Automata\Strategy\ScriptStrategy;
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyDefinition, StrategyEligibility, StrategyUsage, StrategyRouteRequest, StrategyAdapterResult};
use RuntimeException;

/** Bind reviewed scripts into the ordinary router without inventing cost or purity. */
final class ScriptRouteAdapter implements IStrategyRouteAdapter
{
    /** Only the host can attest that all parser/binding operations are side-effect-free. */
    public function __construct(private readonly ScriptStrategy $strategy, private readonly bool $sideEffectFree = false) {}

    /** Return fresh metadata so callers cannot mutate the registered identity. */
    public function definition(): StrategyDefinition
    {
        $id = $this->strategy->identity();
        return new StrategyDefinition(['id' => $id['strategy_id'], 'version' => $id['strategy_version'],
            'capability_id' => $id['strategy_id'] . '.execute', 'capability_version' => $id['strategy_version'],
            'family' => 'script', 'mode' => $this->strategy->generative() ? 'generative' : 'deterministic',
            'side_effect_free' => $this->sideEffectFree, 'availability' => 'available', 'metadata' => $id]);
    }

    /** Numeric usage cannot represent unknown costs; refuse budgets that would rely on them. */
    public function eligibility(StrategyRouteRequest $request): StrategyEligibility
    {
        $limits = $request->limits ?? [];
        $eligible = $request->subject_id === $this->strategy->identity()['subject_id'];
        // Do not let primitive construction coerce a malformed host request.
        if (!is_array($limits)) { throw new \TypeError('Script limits must be an array.'); }
        $limitValues = Arr::make($limits);
        foreach (['max_cost', 'max_energy', 'max_latency_ms'] as $key) {
            if ($limitValues->hasKey($key)) { $eligible = false; }
        }
        return new StrategyEligibility(['eligible' => $eligible, 'code' => $eligible ? 'eligible' : 'script_scope_or_budget_unsupported',
            'evidence' => ['cost_known' => false, 'provider_retry_count_known' => false]]);
    }

    /** Reserve the maximum outer script/generator dispatch count; other metrics remain unqualified. */
    public function estimate(StrategyRouteRequest $request): StrategyUsage
    {
        return new StrategyUsage(['invocations' => 1 + $this->strategy->maximumGenerations()]);
    }

    /** Throw on non-success so the router cannot silently retry/escalate an uncertain script. */
    public function execute(StrategyRouteRequest $request): StrategyAdapterResult
    {
        if (!$this->eligibility($request)->eligible) { throw new RuntimeException('Script scope or budget unsupported.'); }
        $result = $this->strategy->run($request->input);
        if (!$result->succeeded()) { throw new RuntimeException('Script stopped: ' . $result->status()); }
        $data = $result->toArray();
        return new StrategyAdapterResult(['status' => 'completed', 'code' => 'completed', 'output' => $result->output(),
            'usage' => ['invocations' => 1 + $data['generation_calls'], 'latency_ms' => $data['elapsed_ms']],
            'evidence' => ['script' => $data, 'cost_known' => false]]);
    }
}
