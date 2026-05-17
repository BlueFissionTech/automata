<?php

namespace BlueFission\Automata\LLM\Agent\State;

class CallableAgentModule implements IAgentModule
{
    protected string $name;
    protected $handler;

    public function __construct(string $name, callable $handler)
    {
        $this->name = $name;
        $this->handler = $handler;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function process(AgentState $state, array $context = []): AgentModuleResult
    {
        $result = ($this->handler)($state, $context);
        if ($result instanceof AgentModuleResult) {
            return $result;
        }

        return new AgentModuleResult(is_array($result) ? $result : [
            'module' => $this->name,
            'decision' => $result,
        ]);
    }
}
