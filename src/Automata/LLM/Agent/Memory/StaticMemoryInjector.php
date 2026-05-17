<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

class StaticMemoryInjector implements IMemoryInjector
{
    protected string $sessionContext;
    protected string $promptContext;

    public function __construct(string $sessionContext = '', string $promptContext = '')
    {
        $this->sessionContext = $sessionContext;
        $this->promptContext = $promptContext;
    }

    public function sessionContext(array $context = []): string
    {
        return $this->sessionContext;
    }

    public function promptContext(string $prompt, array $context = []): string
    {
        return $this->promptContext;
    }
}
