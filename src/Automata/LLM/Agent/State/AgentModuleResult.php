<?php

namespace BlueFission\Automata\LLM\Agent\State;

class AgentModuleResult
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = array_replace_recursive([
            'module' => null,
            'status' => 'completed',
            'decision' => null,
            'writes' => [],
            'confidence' => null,
            'metadata' => [],
        ], $data);
    }

    public function decision(): mixed
    {
        return $this->data['decision'];
    }

    public function writes(): array
    {
        return $this->data['writes'];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
