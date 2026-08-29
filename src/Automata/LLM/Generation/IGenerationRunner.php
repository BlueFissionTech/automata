<?php

namespace BlueFission\Automata\LLM\Generation;

interface IGenerationRunner
{
    public function run(GenerationRunRequest $request): GenerationRunResult;
}
