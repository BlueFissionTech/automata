<?php
namespace BlueFission\Automata\LLM\Tools;

interface ITool {
    public function execute($input): string;
    public function name(): string;
    public function description(): string;
}