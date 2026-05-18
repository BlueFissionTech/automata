<?php

namespace BlueFission\Automata\LLM\Agent\State;

use BlueFission\Arr;

class AgentModuleResult
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $this->merge([
            'module' => null,
            'status' => 'completed',
            'decision' => null,
            'writes' => [],
            'confidence' => null,
            'metadata' => [],
        ], $data);
    }

    /**
     * Return the module's proposed decision.
     */
    public function decision(): mixed
    {
        return $this->data['decision'];
    }

    /**
     * Return state writes requested by the module.
     */
    public function writes(): array
    {
        return $this->data['writes'];
    }

    /**
     * Export the result for tests, traces, and downstream hooks.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Merge nested defaults without raw recursive array replacement.
     */
    protected function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (Arr::hasKey($base, $key) && Arr::is($base[$key]) && Arr::is($value)) {
                $base[$key] = $this->merge($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
