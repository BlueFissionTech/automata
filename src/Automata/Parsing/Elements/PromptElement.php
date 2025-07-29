<?php
namespace BlueFission\Automata\Parsing\Elements;

use BlueFission\Parsing\Elements\EvalElement;
use BlueFission\Parsing\Contracts\IRenderableElement;
use BlueFission\Parsing\Contracts\IExecutableElement;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use Exception;

class PromptElement extends EvalElement implements IExecutableElement, IRenderableElement
{
    protected $llm;
    protected array $buffer = [];

    public function setLLM($llm): void
    {
        $this->llm = $llm;
    }
}