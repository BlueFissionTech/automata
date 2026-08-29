<?php

namespace BlueFission\Automata\LLM\Generation;

interface GenerationRunner
{
    public function run(GenerationRunRequest $request): GenerationRunResult;
}
