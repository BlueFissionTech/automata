<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Arr;
use BlueFission\DataTypes;

class StrategyRouteRequest extends RoutingValue
{
    public const ESCALATE_NONE = 'none';
    public const ESCALATE_NEXT_ELIGIBLE = 'next_eligible';

    public const SELECTION_DECLARED = 'declared';
    public const SELECTION_ADAPTIVE = 'adaptive';

    protected $_data = [
        'id' => '',
        'subject_id' => '',
        'capability_id' => '',
        'capability_version' => '',
        'candidates' => [],
        'input' => null,
        'fixtures' => [],
        'eligibility' => [],
        'limits' => [],
        'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC],
        'deterministic_preferred' => true,
        'selection_policy' => self::SELECTION_DECLARED,
        'context_key' => '',
        'escalation_policy' => self::ESCALATE_NONE,
        'correlation_id' => '',
        'causation_id' => '',
        'trace_id' => '',
        'metadata' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'subject_id' => DataTypes::STRING,
        'capability_id' => DataTypes::STRING,
        'capability_version' => DataTypes::STRING,
        'candidates' => DataTypes::ARRAY,
        'input' => DataTypes::GENERIC,
        'fixtures' => DataTypes::ARRAY,
        'eligibility' => DataTypes::ARRAY,
        'limits' => DataTypes::ARRAY,
        'allowed_modes' => DataTypes::ARRAY,
        'deterministic_preferred' => DataTypes::BOOLEAN,
        'selection_policy' => DataTypes::STRING,
        'context_key' => DataTypes::STRING,
        'escalation_policy' => DataTypes::STRING,
        'correlation_id' => DataTypes::STRING,
        'causation_id' => DataTypes::STRING,
        'trace_id' => DataTypes::STRING,
        'metadata' => DataTypes::ARRAY,
    ];

    protected $_lockDataType = true;

    public function allowsMode(string $mode): bool
    {
        return Arr::has($this->allowed_modes, $mode, true);
    }

    public function canEscalate(): bool
    {
        return $this->escalation_policy === self::ESCALATE_NEXT_ELIGIBLE;
    }

    public function adaptiveSelection(): bool
    {
        return $this->selection_policy === self::SELECTION_ADAPTIVE;
    }
}
