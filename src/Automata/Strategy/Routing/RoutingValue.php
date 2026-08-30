<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Obj;

abstract class RoutingValue extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig($this->defaults(), $data));
    }

    abstract protected function defaults(): array;

    public function toArray(): array
    {
        return ToolDefinition::mergeConfig($this->defaults(), parent::toArray());
    }
}
