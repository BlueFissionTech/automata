<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;
use BlueFission\DataTypes;
use BlueFission\Num;
use BlueFission\Val;

class StrategyUsage extends RoutingValue
{
    protected $_data = [
        'cost' => 0.0,
        'latency_ms' => 0,
        'energy' => 0.0,
        'invocations' => 0,
    ];

    protected $_types = [
        'cost' => DataTypes::GENERIC,
        'latency_ms' => DataTypes::INTEGER,
        'energy' => DataTypes::NUMBER,
        'invocations' => DataTypes::INTEGER,
    ];

    protected $_lockDataType = true;

    public function __construct(array $data = [])
    {
        $unknownCost = Arr::hasKey($data, 'cost') && Val::isNull($data['cost']);

        parent::__construct([
            'cost' => $unknownCost ? 0.0 : Num::max(0.0, (float)($data['cost'] ?? 0.0)),
            'latency_ms' => (int)Num::max(0, (int)($data['latency_ms'] ?? 0)),
            'energy' => Num::max(0.0, (float)($data['energy'] ?? 0.0)),
            'invocations' => (int)Num::max(0, (int)($data['invocations'] ?? 0)),
        ]);

        // Obj::field() treats null as a read, so retain an explicit unknown
        // observation in the backing record after ordinary field assignment.
        if ($unknownCost) {
            $this->_data['cost'] = null;
        }
    }

    public function plus(self $usage): self
    {
        return new self([
            'cost' => Val::isNull($this->cost) || Val::isNull($usage->cost)
                ? null
                : Num::plus($this->cost, $usage->cost),
            'latency_ms' => Num::plus($this->latency_ms, $usage->latency_ms),
            'energy' => Num::plus($this->energy, $usage->energy),
            'invocations' => Num::plus($this->invocations, $usage->invocations),
        ]);
    }

    public function withMinimumInvocations(int $minimum = 1): self
    {
        return new self([
            'cost' => $this->cost,
            'latency_ms' => $this->latency_ms,
            'energy' => $this->energy,
            'invocations' => Num::max($minimum, $this->invocations),
        ]);
    }

    public function within(array $limits): bool
    {
        return (!Arr::hasKey($limits, 'max_cost')
                || (!Val::isNull($this->cost) && $this->cost <= (float)$limits['max_cost']))
            && (!Arr::hasKey($limits, 'max_latency_ms') || $this->latency_ms <= (int)$limits['max_latency_ms'])
            && (!Arr::hasKey($limits, 'max_energy') || $this->energy <= (float)$limits['max_energy'])
            && (!Arr::hasKey($limits, 'max_invocations') || $this->invocations <= (int)$limits['max_invocations']);
    }
}
