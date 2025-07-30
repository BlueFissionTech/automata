<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Parsing\Parser;
use BlueFission\Parsing\Registry\TagRegistry;
use BlueFission\Parsing\Registry\RendererRegistry;
use BlueFission\Parsing\Registry\ExecutorRegistry;
use BlueFission\Parsing\Registry\PreparerRegistry;
use BlueFission\Parsing\Contracts\IRenderableElement;
use BlueFission\Parsing\Contracts\IExecutableElement;
use BlueFission\Parsing\Element;
use BlueFission\Automata\Parsing\Elements\PromptElement;
use BlueFission\Automata\Parsing\Preparers\LLMPreparer;
use BlueFission\Automata\Parsing\Preparers\ToolPreparer;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Obj;

class FillIn implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchConstruct;
    }

    protected $llm;
    protected $template;
    protected $parser;
    protected array $tools = [];
    protected array $vars = [];
    protected string $output = '';

    public function __construct($llm, string $prompt)
    {
        $this->__dispatchConstruct();

        $this->llm = $llm;
        $this->setPrompt($prompt);
    }

    public function setPrompt(string $prompt): void
    {
        TagRegistry::registerDefaults();
        RendererRegistry::registerDefaults();
        ExecutorRegistry::registerDefaults();
        PreparerRegistry::registerDefaults();
        PreparerRegistry::register(new LLMPreparer($this), [PromptElement::class]);

        $this->parser = new Parser($prompt);
        $this->parser->setVariables($this->vars);
        $this->echo($this->parser, [Event::STARTED, Event::SENT, Event::ERROR, Event::RECEIVED, Event::COMPLETE]);
        $this->template = $prompt;
    }

    public function setVariables(array $vars): void
    {
        $this->vars = $vars;
        $this->parser->setVariables($vars);
    }

    public function addTool(string $name, $tool): void
    {
        $this->tools[$name] = $tool;
    }

    public function getLLM()
    {
        return $this->llm;
    }

    public function run(array $config = []): array
    {
        $this->dispatch(Event::STARTED, new Meta(when: State::RUNNING, src: $this));

        $output = $this->parser->render();

        $this->dispatch(Event::COMPLETE, new Meta(when: State::RUNNING, data: [
            'output' => $this->output
        ], src: $this));

        return $this->parser->root()->getAllVariables();
    }

    public function output(): string
    {
        return $this->output;
    }
}
