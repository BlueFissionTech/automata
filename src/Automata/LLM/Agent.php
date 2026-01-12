<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\Tools\MCPDiscoveryTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPResourceTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPToolCallTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRequestTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRegisterServerTool;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\DevElation as Dev;
// https://bootcamp.uxdesign.cc/a-comprehensive-and-hands-on-guide-to-autonomous-agents-with-gpt-b58d54724d50
class Agent implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchesConstruct;
    }

    protected $tools = [];
    protected $llm;
    protected $template;
    protected $fillIn;
    protected $replacements = [];
    protected ?MCPClient $mcpClient = null;

    public function __construct($llm) {
        $this->__dispatchesConstruct();

        $this->llm = $llm;
        $this->template = "Answer the following question as best you can.
        {#var tools = [{toolNames}]}
        {#var isComplete = 'no'}
        {#var completion = ['yes', 'no']}
        {#var actions = ['assign', 'repeat', 'meeting', 'deliver']}
        {input}
        You have access to the following tools: 
            {toolsList}
        The exact question to answer: {=problem}
        Assessemnt of the problem: {=assessment}

        {#each [iterations:'5', glue:', ']}
        {#if(isComplete=='no')}
        The best tool for the task: {=tool [stop: ',', options: tools]}
        The command to give the tool: {=command}
        {#use tool [tool, command, outcome]}
        Outcome of the tool use: {=outcome}
        Observation of the outcome: {=observation}
        Have we answered the question?: {=isComplete [stop: ',', options: completion]
        {#endif}
        {#if(isComplete=='yes')}
            The final conclusion: {=conclusion}
        {#endif}
        {#endeach}
        The next steps to follow: {=nextSteps [stop: ',', options: actions]}";

        $this->fillIn = new FillIn($this->llm, $this->template);
        $this->echo($this->fillIn, [
            Event::STARTED,
            Event::COMPLETE,
            Event::SENT,
            Event::RECEIVED,
            Event::CHANGE,
        ]);
    }

    public function setTemplate(string $template) {
        $this->template = $template;
    }

    public function registerTool(string $name, ITool $tool) {
        $this->tools[$name] = $tool;
    }

    public function registerMcpClient(MCPClient $client): void
    {
        $this->mcpClient = $client;

        $tools = [
            new MCPRegisterServerTool($client),
            new MCPDiscoveryTool($client),
            new MCPResourceTool($client),
            new MCPToolCallTool($client),
            new MCPRequestTool($client),
        ];

        foreach ($tools as $tool) {
            $this->registerTool($tool->name(), $tool);
        }

        Dev::do('automata.llm.agent.mcp_registered', [
            'tool_count' => count($tools),
        ]);
    }

    public function execute($input) {
        $toolList = '';
        foreach ($this->tools as $name => $tool) {
            $toolList .= "$name: {$tool->description()}\n";
        }

        $toolNames = "'".implode("', '", array_keys($this->tools))."'";

        $this->replacements['toolNames'] = $toolNames;
        $this->replacements['toolsList'] = $toolList;
        $this->replacements['input'] = $input;

        // Create a prompt using the template
        $patterns = array_map(function($pattern) {
            return '{' . $pattern . '}';
        }, array_keys($this->replacements));

        $prompt = str_replace($patterns, $this->replacements, $this->template);

        $this->fillIn->setPrompt($prompt);

        return $this->fillIn->run();
    }
}
