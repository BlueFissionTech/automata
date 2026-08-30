<?php

namespace BlueFission\Automata\Qualification;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;

class NurtureSuggestion extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig([
            'id' => '',
            'action' => '',
            'reason' => '',
            'priority' => 0,
            'allowed_when' => [],
            'prohibited_when' => [],
            'requires_review' => true,
            'evidence' => [],
            'metadata' => [],
        ], $data));
    }

    public function isAllowed(array $context): bool
    {
        $allowed = Arr::make($this->field('allowed_when') ?? [])->toArray();
        $prohibited = Arr::make($this->field('prohibited_when') ?? [])->toArray();

        return $this->matches($allowed, $context) && ($prohibited === [] || !$this->matches($prohibited, $context));
    }

    protected function matches(array $conditions, array $context): bool
    {
        foreach ($conditions as $key => $expected) {
            if (!Arr::hasKey($context, $key) || $context[$key] !== $expected) {
                return false;
            }
        }

        return true;
    }
}
