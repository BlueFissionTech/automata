<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;

class CallableAgentModule implements IAgentModule
{
    protected string $name;
    protected $handler;

    public function __construct(string $name, callable $handler)
    {
        $this->name = $name;
        $this->handler = $handler;
    }

    /**
     * Return the module name used in traces and results.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Execute the callable module against the shared agent state.
     */
    public function process(AgentState $state, array $context = []): AgentModuleResult
    {
        $result = ($this->handler)($state, $context);
        if ($result instanceof AgentModuleResult) {
            return $result;
        }

        return new AgentModuleResult(Arr::is($result) ? $result : [
            'module' => $this->name,
            'decision' => $result,
        ]);
    }
}
