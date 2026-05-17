<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\Automata\LLM\Agent\ToolCatalog;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\ToolExecutionResult;
use BlueFission\Automata\LLM\Agent\ToolExecutor;
use BlueFission\Automata\LLM\Agent\Telemetry\CpctReport;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Agent\Memory\IMemoryEventStore;
use BlueFission\Automata\LLM\Agent\Memory\IMemoryInjector;
use BlueFission\Automata\LLM\Agent\Memory\MemoryEvent;
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
    protected ToolCatalog $toolCatalog;
    protected ToolExecutor $toolExecutor;
    protected ?TaskTrace $taskTrace = null;
    protected ?IMemoryEventStore $memoryEventStore = null;
    protected ?IMemoryInjector $memoryInjector = null;
    protected ?string $memorySessionId = null;
    protected int $memorySequence = 0;

    public function __construct($llm) {
        $this->__dispatchesConstruct();

        $this->llm = $llm;
        $this->toolCatalog = new ToolCatalog();
        $this->toolExecutor = new ToolExecutor();
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

    public function registerTool(string $name, ITool $tool, ToolDefinition|array|null $definition = null) {
        $this->tools[$name] = $tool;
        $this->toolCatalog->register($name, $tool, $definition);
        Dev::do('automata.llm.agent.tool_registered', [
            'name' => $name,
            'definition' => $this->toolCatalog->definition($name)?->toArray(),
        ]);
    }

    public function registerToolDefinition(string $name, ToolDefinition|array $definition): void
    {
        $this->toolCatalog->define($name, $definition);
        Dev::do('automata.llm.agent.tool_definition_registered', [
            'name' => $name,
            'definition' => $this->toolCatalog->definition($name)?->toArray(),
        ]);
    }

    public function toolDefinition(string $name): ?ToolDefinition
    {
        return $this->toolCatalog->definition($name);
    }

    public function toolDefinitions(array $filters = []): array
    {
        return $this->toolCatalog->toArray($filters);
    }

    public function toolPrompt(array $filters = []): string
    {
        return $this->toolCatalog->promptList($filters);
    }

    public function callTool(string $name, mixed $input = null, array $context = []): ToolExecutionResult
    {
        if (isset($context['task_id']) && (!$this->taskTrace || $this->taskTrace->taskId() !== $context['task_id'])) {
            $this->startTask((string)$context['task_id']);
        }

        $trace = $this->taskTrace();
        $definition = $this->toolCatalog->definition($name);
        $this->emitMemoryEvent(MemoryEvent::PRE_TOOL_USE, [
            'tool' => $name,
            'input' => $input,
            'definition' => $definition?->toArray(),
        ]);

        $span = $trace->startSpan(TaskTraceSpan::KIND_TOOL, $name, [
            'permission' => $definition?->permission(),
            'parallel_safe' => $definition?->parallelSafe(),
            'batchable' => $definition?->parallelSafe(),
        ]);

        $result = $this->toolExecutor->execute($this->toolCatalog, $name, $input, $context);
        $encodedInput = is_scalar($input) || $input === null ? (string)$input : (string)json_encode($input);
        $encodedOutput = $result->toJson();

        $trace->addSpan($span->finish($result->ok() ? 'completed' : 'failed', [
            'outcome_status' => $result->status(),
            'input_tokens' => $this->estimateTokens($encodedInput),
            'output_tokens' => $this->estimateTokens($encodedOutput),
            'total_tokens' => $this->estimateTokens($encodedInput) + $this->estimateTokens($encodedOutput),
            'batchable' => $definition?->parallelSafe() ?? false,
            'metadata' => [
                'result_status' => $result->status(),
                'definition' => $definition?->toArray(),
            ],
        ]));

        $this->emitMemoryEvent(MemoryEvent::POST_TOOL_USE, [
            'tool' => $name,
            'result' => $result->toArray(),
        ]);

        return $result;
    }

    public function startTask(?string $taskId = null, array $metadata = []): TaskTrace
    {
        $this->taskTrace = new TaskTrace($taskId, $metadata);
        Dev::do('automata.llm.agent.task_started', $this->taskTrace->toArray());

        return $this->taskTrace;
    }

    public function useTaskTrace(TaskTrace $trace): void
    {
        $this->taskTrace = $trace;
    }

    public function taskTrace(): TaskTrace
    {
        if (!$this->taskTrace) {
            $this->startTask();
        }

        return $this->taskTrace;
    }

    public function taskId(): string
    {
        return $this->taskTrace()->taskId();
    }

    public function cpctReport(array $traces = [], array $pricing = [], array $config = []): array
    {
        if (!$traces) {
            $traces = [$this->taskTrace()->toArray()];
        }

        return CpctReport::build($traces, $pricing, $config);
    }

    public function enableMemory(IMemoryEventStore $store, ?IMemoryInjector $injector = null, ?string $sessionId = null): void
    {
        $this->memoryEventStore = $store;
        $this->memoryInjector = $injector;
        $this->memorySessionId = $sessionId ?: TaskTraceSpan::id('session');
        $this->memorySequence = 0;

        $this->emitMemoryEvent(MemoryEvent::SESSION_START, [
            'session_context' => $this->memoryInjector ? $this->memoryInjector->sessionContext($this->memoryContext()) : '',
        ]);
    }

    public function disableMemory(): void
    {
        $this->memoryEventStore = null;
        $this->memoryInjector = null;
        $this->memorySessionId = null;
        $this->memorySequence = 0;
    }

    public function memoryEvents(?string $sessionId = null): array
    {
        if (!$this->memoryEventStore) {
            return [];
        }

        return $this->memoryEventStore->events($sessionId ?? $this->memorySessionId);
    }

    public function stopMemorySession(array $payload = []): void
    {
        $this->emitMemoryEvent(MemoryEvent::STOP, $payload);
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
        $trace = $this->taskTrace();
        $input = $this->applyMemoryContext((string)$input);
        $this->emitMemoryEvent(MemoryEvent::USER_PROMPT_SUBMIT, [
            'prompt' => $input,
        ]);

        $span = $trace->startSpan(TaskTraceSpan::KIND_AGENT, 'execute', [
            'input_token_estimate' => $this->estimateTokens((string)$input),
        ]);

        $toolList = $this->toolCatalog->promptList();
        $toolNames = "'".implode("', '", $this->toolCatalog->names())."'";

        $this->replacements['toolNames'] = $toolNames;
        $this->replacements['toolsList'] = $toolList;
        $this->replacements['input'] = $input;

        // Create a prompt using the template
        $patterns = array_map(function($pattern) {
            return '{' . $pattern . '}';
        }, array_keys($this->replacements));

        $prompt = str_replace($patterns, $this->replacements, $this->template);

        $this->fillIn->setPrompt($prompt);

        $result = $this->fillIn->run();
        $this->recordModelUsageSpans($trace);

        $trace->addSpan($span->finish('completed', [
            'outcome_status' => 'completed',
            'input_tokens' => $this->estimateTokens((string)$input),
            'output_tokens' => $this->estimateTokens(json_encode($result) ?: ''),
            'total_tokens' => $this->estimateTokens((string)$input) + $this->estimateTokens(json_encode($result) ?: ''),
        ]));
        $trace->complete('completed');

        return $result;
    }

    public function output(): string
    {
        return $this->fillIn->output();
    }

    protected function recordModelUsageSpans(TaskTrace $trace): void
    {
        foreach ($this->fillIn->usageLedger() as $entries) {
            foreach ($entries as $entry) {
                $usage = $entry['usage'] ?? [];
                $span = $trace->startSpan(TaskTraceSpan::KIND_MODEL, (string)($entry['element'] ?? 'generation'), [
                    'placeholder' => $entry['placeholder'] ?? null,
                    'profile' => $entry['profile'] ?? null,
                    'attempt' => $entry['attempt'] ?? null,
                    'accepted' => $entry['accepted'] ?? null,
                ]);

                $trace->addSpan($span->finish(($entry['accepted'] ?? true) ? 'completed' : 'failed', [
                    'provider' => $entry['provider'] ?? null,
                    'model' => $entry['model'] ?? null,
                    'input_tokens' => $usage['prompt_tokens'] ?? $usage['estimated_prompt_tokens'] ?? 0,
                    'output_tokens' => $usage['completion_tokens'] ?? $usage['estimated_completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? $usage['estimated_total_tokens'] ?? 0,
                    'metadata' => [
                        'usage' => $usage,
                        'context' => $entry['context'] ?? [],
                    ],
                ]));
            }
        }
    }

    protected function estimateTokens(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        preg_match_all('/\S+/u', $text, $matches);
        return max(count($matches[0] ?? []), (int)ceil(strlen($text) / 4));
    }

    protected function applyMemoryContext(string $input): string
    {
        if (!$this->memoryInjector) {
            return $input;
        }

        $sessionContext = trim($this->memoryInjector->sessionContext($this->memoryContext()));
        $promptContext = trim($this->memoryInjector->promptContext($input, $this->memoryContext()));
        $parts = [];

        if ($sessionContext !== '') {
            $parts[] = "Memory context:\n" . $sessionContext;
        }

        $parts[] = $input;

        if ($promptContext !== '') {
            $parts[] = "Relevant memory:\n" . $promptContext;
        }

        return implode("\n\n", $parts);
    }

    protected function emitMemoryEvent(string $event, array $payload = []): void
    {
        if (!$this->memoryEventStore || !$this->memorySessionId) {
            return;
        }

        $memoryEvent = new MemoryEvent($event, $payload, $this->memoryContext([
            'sequence' => ++$this->memorySequence,
        ]));

        $this->memoryEventStore->append($memoryEvent);
        Dev::do('automata.llm.agent.memory_event', $memoryEvent->toArray());
    }

    protected function memoryContext(array $extra = []): array
    {
        return array_replace([
            'session_id' => $this->memorySessionId,
            'task_id' => $this->taskTrace()->taskId(),
            'client' => 'automata',
        ], $extra);
    }
}
