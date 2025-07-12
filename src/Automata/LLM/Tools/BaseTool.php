<?php
namespace BlueFission\Automata\LLM\Tools;

class BaseTool implements ITool {
    protected $description = "";
    protected $name = "";
    
    public function execute($input): string
    {
        $result = "Tool response";
        return $result;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }
}