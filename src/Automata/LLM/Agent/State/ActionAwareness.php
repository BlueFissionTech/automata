<?php

namespace BlueFission\Automata\LLM\Agent\State;

class ActionAwareness
{
    /**
     * Record what an action is expected to change before it runs.
     */
    public static function expect(AgentState $state, string $actionId, mixed $expected): void
    {
        $state->write(AgentState::EXPECTATIONS, $actionId, [
            'expected' => $expected,
            'observed' => null,
            'matched' => null,
        ]);
    }

    /**
     * Compare an observed outcome with the prior expectation and log the result.
     */
    public static function observe(AgentState $state, string $actionId, mixed $observed): array
    {
        $record = $state->read(AgentState::EXPECTATIONS, $actionId, [
            'expected' => null,
            'observed' => null,
            'matched' => null,
        ]);

        $record['observed'] = $observed;
        $record['matched'] = $record['expected'] === $observed;
        $state->write(AgentState::EXPECTATIONS, $actionId, $record);
        $state->append(AgentState::OBSERVATIONS, [
            'action_id' => $actionId,
            'expected' => $record['expected'],
            'observed' => $observed,
            'matched' => $record['matched'],
        ], 'action_awareness');

        return $record;
    }
}
