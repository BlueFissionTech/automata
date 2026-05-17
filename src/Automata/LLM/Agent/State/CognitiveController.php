<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\DevElation as Dev;

class CognitiveController
{
    protected array $priorities;
    protected int $limitPerChannel;

    public function __construct(array $priorities = [], int $limitPerChannel = 5)
    {
        $this->priorities = $priorities ?: [
            AgentState::RULES,
            AgentState::GOALS,
            AgentState::OBSERVATIONS,
            AgentState::SOCIAL,
            AgentState::EXPECTATIONS,
        ];
        $this->limitPerChannel = max(1, $limitPerChannel);
    }

    public function decide(AgentState $state, mixed $decision = null, array $context = []): AgentModuleResult
    {
        $relevant = $state->relevant($this->priorities, $this->limitPerChannel);
        if ($decision === null) {
            $decision = [
                'intent' => 'continue',
                'basis' => $relevant,
            ];
        }

        $state->append(AgentState::DECISIONS, $decision);
        $state->write(AgentState::OUTPUTS, 'controller_decision', $decision);

        $result = new AgentModuleResult([
            'module' => 'cognitive_controller',
            'decision' => $decision,
            'writes' => [
                AgentState::DECISIONS,
                AgentState::OUTPUTS,
            ],
            'metadata' => [
                'bottleneck' => $relevant,
                'context' => $context,
            ],
        ]);

        Dev::do('automata.llm.agent.state.controller_decision', $result->toArray());

        return $result;
    }
}
