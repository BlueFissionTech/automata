<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

interface IMemoryInjector
{
    public function sessionContext(array $context = []): string;
    public function promptContext(string $prompt, array $context = []): string;
}
