<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;
use BlueFission\Automata\Goal\GoalDecision;
use BlueFission\Automata\Goal\IGoalManager;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

trait ControlsAgentState
{
    protected array $priorities = [];
    protected int $limitPerChannel = 5;
    protected int $optionLimit = 5;
    protected ?IGoalManager $goalManager = null;

    /**
     * Configure reusable state-controller behavior for default and custom controllers.
     */
    protected function initializeStateController(array $priorities = [], int $limitPerChannel = 5, ?IGoalManager $goalManager = null, int $optionLimit = 5): void
    {
        $this->priorities = $priorities ?: [
            AgentState::RULES,
            AgentState::GOALS,
            AgentState::OBSERVATIONS,
            AgentState::SOCIAL,
            AgentState::EXPECTATIONS,
        ];
        $this->limitPerChannel = Num::is($limitPerChannel) ? max(1, $limitPerChannel) : 5;
        $this->optionLimit = Num::is($optionLimit) ? max(1, $optionLimit) : 5;
        $this->goalManager = $goalManager;
    }

    /**
     * Attach an explicit goal manager for controller decisions.
     */
    public function useGoalManager(IGoalManager $goalManager): self
    {
        $this->goalManager = $goalManager;

        return $this;
    }

    /**
     * Build a bottlenecked decision from state, goals, and optional caller input.
     */
    public function decide(AgentState $state, mixed $decision = null, array $context = []): AgentModuleResult
    {
        return $this->stateDecision($state, $decision, $context);
    }

    /**
     * Execute the reusable controller pipeline for custom public decision hooks.
     */
    protected function stateDecision(AgentState $state, mixed $decision = null, array $context = []): AgentModuleResult
    {
        $state->enter(AgentState::STATE_REASONING);
        $state->allowInState(AgentState::STATE_REASONING, AgentState::ACTION_DECIDE);
        $relevant = $state->relevant($this->priorities, $this->limitPerChannel);
        $manager = $this->goalManager ?? $state->goals();
        $context = $this->decisionContext($state, $context);
        $options = $manager->recommend($context, $this->optionLimit);

        if ($decision === null) {
            $decision = [
                'intent' => 'continue',
                'selected' => $this->selectedOption($options),
                'options' => $this->optionsToArray($options),
                'basis' => $relevant,
            ];
        }

        $state->append(AgentState::DECISIONS, $decision);
        $state->write(AgentState::OUTPUTS, 'controller_decision', $decision);
        $state->leave(AgentState::STATE_REASONING);

        $result = new AgentModuleResult([
            'module' => 'cognitive_controller',
            'decision' => $decision,
            'writes' => [
                AgentState::DECISIONS,
                AgentState::OUTPUTS,
            ],
            'metadata' => [
                'bottleneck' => $relevant,
                'options' => $this->optionsToArray($options),
                'context' => $context,
            ],
        ]);

        Dev::do('automata.llm.agent.state.controller_decision', $result->toArray());

        return $result;
    }

    /**
     * Merge caller context with deterministic state channels used by goal scoring.
     */
    protected function decisionContext(AgentState $state, array $context): array
    {
        $stateContext = [
            'states' => $state->activeStates(),
            'rules' => $state->channel(AgentState::RULES),
            'observations' => $state->channel(AgentState::OBSERVATIONS),
            'social' => $state->channel(AgentState::SOCIAL),
            'outputs' => $state->channel(AgentState::OUTPUTS),
        ];

        return $this->mergeContext($stateContext, $context);
    }

    /**
     * Convert goal decision objects for output metadata.
     */
    protected function optionsToArray(array $options): array
    {
        $rows = [];
        foreach ($options as $option) {
            if ($option instanceof GoalDecision) {
                $rows[] = $option->toArray();
            }
        }

        return $rows;
    }

    /**
     * Return the top-ranked option as an array.
     */
    protected function selectedOption(array $options): array
    {
        $first = null;
        foreach ($options as $option) {
            $first = $option;
            break;
        }

        return $first instanceof GoalDecision ? $first->toArray() : [];
    }

    /**
     * Merge context arrays recursively without raw PHP array merge helpers.
     */
    protected function mergeContext(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (Arr::hasKey($base, $key) && Arr::is($base[$key]) && Arr::is($value)) {
                $base[$key] = $this->mergeContext($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
