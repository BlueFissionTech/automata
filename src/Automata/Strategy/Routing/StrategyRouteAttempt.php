<?php

namespace BlueFission\Automata\Strategy\Routing;

class StrategyRouteAttempt extends RoutingValue
{
    protected function defaults(): array
    {
        return [
            'strategy_id' => '',
            'strategy_version' => '',
            'mode' => '',
            'status' => 'skipped',
            'code' => '',
            'eligible' => false,
            'executed' => false,
            'escalated' => false,
            'estimate' => [],
            'actual_usage' => [],
            'selection_score' => null,
            'confidence' => null,
            'uncertainty' => null,
            'reasons' => [],
            'evidence' => [],
            'diagnostics' => [],
            'metadata' => [],
        ];
    }
}
