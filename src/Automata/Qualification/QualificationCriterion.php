<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Num;
use BlueFission\Obj;

class QualificationCriterion extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig([
            'key' => '',
            'weight' => 1.0,
            'minimum' => 0.0,
            'required' => false,
            'reason' => '',
            'metadata' => [],
        ], $data));
    }

    public function key(): string
    {
        return (string)$this->field('key');
    }

    public function weight(): float
    {
        return Num::max(0.0, (float)$this->field('weight'));
    }

    public function minimum(): float
    {
        return Num::min(1.0, Num::max(0.0, (float)$this->field('minimum')));
    }

    public function required(): bool
    {
        return (bool)$this->field('required');
    }
}
