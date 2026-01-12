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
use BlueFission\DevElation as Dev;
use Exception;

class PromptElement extends EvalElement implements IExecutableElement, IRenderableElement
{
    protected $llm;
    protected array $buffer = [];

    public function setLLM($llm): void
    {
        $llm = Dev::apply('automata.parsing.elements.promptelement.setLLM.1', $llm);
        $this->llm = $llm;
        Dev::do('automata.parsing.elements.promptelement.setLLM.action1', ['element' => $this, 'llm' => $llm]);
    }

    public function getDescription(): string
    {
        $descriptionString = sprintf('Evalute the expression "%s" and generate or recieve a result.', $this->name);

        $this->description = $descriptionString;
        $this->description = Dev::apply('automata.parsing.elements.promptelement.getDescription.1', $this->description);
        Dev::do('automata.parsing.elements.promptelement.getDescription.action1', ['element' => $this, 'description' => $this->description]);

        return $this->description;
    }
}
