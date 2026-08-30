<?php

namespace BlueFission\Automata\Language\Claims;

use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;

abstract class ClaimValue extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig($this->defaults(), $data));
    }

    abstract protected function defaults(): array;

    public function toArray(): array
    {
        return parent::toArray();
    }
}
