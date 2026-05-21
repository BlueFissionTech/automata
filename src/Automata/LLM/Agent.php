<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Automata\LLM\Tools\ITool;
use BlueFission\Automata\LLM\Agent\ToolCatalog;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\ToolExecutionResult;
use BlueFission\Automata\LLM\Agent\ToolExecutor;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\Governance\HumanReviewGate;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallMonitor;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallPolicy;
use BlueFission\Automata\LLM\Agent\Telemetry\CpctReport;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\MCP\Tools\MCPDiscoveryTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPResourceTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPToolCallTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRequestTool;
use BlueFission\Automata\LLM\MCP\Tools\MCPRegisterServerTool;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\DevElation as Dev;
use BlueFission\Net\HTTP;
use BlueFission\Str;
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
    protected ?TaskCallMonitor $callMonitor = null;
    protected ?HumanReviewGate $humanReviewGate = null;

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

        Dev::do(AgentHook::SESSION_START, [
            'agent' => static::class,
        ]);
    }

    /**
     * Replace the prompt template used by the agent loop.
     */
    public function setTemplate(string $template) {
        $this->template = $template;
    }

    /**
     * Register an executable tool and its model-facing contract.
     */
    public function registerTool(string $name, ITool $tool, ToolDefinition|array|null $definition = null) {
        $this->tools[$name] = $tool;
        $this->toolCatalog->register($name, $tool, $definition);
        Dev::do('automata.llm.agent.tool_registered', [
            'name' => $name,
            'definition' => $this->toolCatalog->definition($name)?->toArray(),
        ]);
    }

    /**
     * Register or replace a tool definition without replacing the executable tool.
     */
    public function registerToolDefinition(string $name, ToolDefinition|array $definition): void
    {
        $this->toolCatalog->define($name, $definition);
        Dev::do('automata.llm.agent.tool_definition_registered', [
            'name' => $name,
            'definition' => $this->toolCatalog->definition($name)?->toArray(),
        ]);
    }

    /**
     * Retrieve a named tool definition.
     */
    public function toolDefinition(string $name): ?ToolDefinition
    {
        return $this->toolCatalog->definition($name);
    }

    /**
     * Retrieve tool definitions after applying catalog filters.
     */
    public function toolDefinitions(array $filters = []): array
    {
        return $this->toolCatalog->toArray($filters);
    }

    /**
     * Render filtered tool definitions for prompt context.
     */
    public function toolPrompt(array $filters = []): string
    {
        return $this->toolCatalog->promptList($filters);
    }

    /**
     * Call a tool through validation, permission, retry, and circuit-breaker handling.
     */
    public function callTool(string $name, mixed $input = null, array $context = []): ToolExecutionResult
    {
        if (Arr::hasKey($context, 'task_id') && (!$this->taskTrace || $this->taskTrace->taskId() !== $context['task_id'])) {
            $this->startTask((string)$context['task_id']);
        }

        $trace = $this->taskTrace();
        $definition = $this->toolCatalog->definition($name);
        if ($this->mcpClient) {
            $this->mcpClient->useTaskTrace($trace);
        }

        if ($this->callMonitor) {
            $this->callMonitor->useTrace($trace);
        }

        if ($definition?->requiresApproval() && (($context['approved'] ?? false) !== true) && $this->humanReviewGate) {
            $decision = $this->humanReviewGate->request([
                'task_id' => $trace->taskId(),
                'kind' => TaskTraceSpan::KIND_TOOL,
                'name' => $name,
                'request' => [
                    'input' => $input,
                ],
                'metadata' => [
                    'permission' => $definition->permission(),
                    'definition' => $definition->toArray(),
                ],
            ]);

            if ($decision->isSteered()) {
                $input = $this->steeredToolInput($input, $decision);
            }

            if (!$decision->allowsExecution()) {
                $trace->recordTaskCall(
                    TaskTraceSpan::KIND_REVIEW,
                    'tool_approval:' . $name,
                    ['input' => $input],
                    [
                        'ok' => false,
                        'status' => $decision->status(),
                        'error' => [
                            'code' => $decision->isPending() ? 'human_review_required' : 'approval_denied',
                            'message' => $decision->message(),
                        ],
                    ],
                    [
                        'permission' => $definition->permission(),
                        'decision' => $decision->toArray(),
                    ]
                );

                return ToolExecutionResult::error(
                    $decision->isPending() ? 'human_review_required' : 'approval_denied',
                    $decision->message() ?: 'Tool execution was not approved.',
                    [
                        'tool' => $name,
                        'permission' => $definition->permission(),
                        'decision' => $decision->toArray(),
                    ],
                    [],
                    ToolExecutionResult::STATUS_PERMISSION_DENIED
                );
            }

            $context['approved'] = true;
            $context['approval_decision'] = $decision->toArray();
        }

        $span = $trace->startSpan(TaskTraceSpan::KIND_TOOL, $name, [
            'permission' => $definition?->permission(),
            'parallel_safe' => $definition?->parallelSafe(),
            'batchable' => $definition?->parallelSafe(),
        ]);

        $result = $this->toolExecutor->execute($this->toolCatalog, $name, $input, $context);
        $encodedInput = $this->stringifyForTelemetry($input);
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

        return $result;
    }

    public function startTask(?string $taskId = null, array $metadata = []): TaskTrace
    {
        $this->taskTrace = new TaskTrace($taskId, $metadata);
        if ($this->callMonitor) {
            $this->callMonitor->useTrace($this->taskTrace);
        }
        if ($this->mcpClient) {
            $this->mcpClient->useTaskTrace($this->taskTrace);
        }
        Dev::do('automata.llm.agent.task_started', $this->taskTrace->toArray());

        return $this->taskTrace;
    }

    public function useTaskTrace(TaskTrace $trace): void
    {
        $this->taskTrace = $trace;
        if ($this->callMonitor) {
            $this->callMonitor->useTrace($trace);
        }
        if ($this->mcpClient) {
            $this->mcpClient->useTaskTrace($trace);
        }
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

    /**
     * Attach a task-call monitor for MCP, RPC, API, and related boundaries.
     */
    public function useCallMonitor(TaskCallMonitor $monitor): void
    {
        $this->callMonitor = $monitor;
        $this->callMonitor->useTrace($this->taskTrace());
        if ($this->humanReviewGate) {
            $this->callMonitor->useHumanReviewGate($this->humanReviewGate);
        }
        if ($this->mcpClient) {
            $this->mcpClient->useCallMonitor($this->callMonitor);
        }
    }

    /**
     * Return the monitor used for governed external task calls.
     */
    public function callMonitor(): TaskCallMonitor
    {
        if (!$this->callMonitor) {
            $this->callMonitor = new TaskCallMonitor($this->taskTrace());
        }

        return $this->callMonitor;
    }

    /**
     * Attach a human approval and steering utility to tools and task calls.
     */
    public function useHumanReviewGate(HumanReviewGate $gate, TaskCallPolicy|array|null $policy = null): void
    {
        $this->humanReviewGate = $gate;
        if (!$this->callMonitor || $policy) {
            $this->callMonitor = new TaskCallMonitor($this->taskTrace(), $policy ?? [], $gate);
        } else {
            $this->callMonitor->useHumanReviewGate($gate);
        }

        if ($this->mcpClient) {
            $this->mcpClient->useCallMonitor($this->callMonitor);
        }
    }

    /**
     * Register MCP tools behind the same tool contract boundary.
     */
    public function registerMcpClient(MCPClient $client): void
    {
        $this->mcpClient = $client;
        $this->mcpClient->useTaskTrace($this->taskTrace());
        if ($this->callMonitor) {
            $this->mcpClient->useCallMonitor($this->callMonitor);
        }

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
            'tool_count' => Arr::count($tools),
        ]);
    }

    /**
     * Run the prompt loop with registered tool names and prompt contracts.
     */
    public function execute($input) {
        $trace = $this->taskTrace();
        $span = $trace->startSpan(TaskTraceSpan::KIND_AGENT, 'execute', [
            'input_token_estimate' => $this->estimateTokens((string)$input),
        ]);

        $toolList = $this->toolCatalog->promptList();
        $toolNames = $this->formatToolNames($this->toolCatalog->names());

        $this->replacements['toolNames'] = $toolNames;
        $this->replacements['toolsList'] = $toolList;
        $this->replacements['input'] = $input;

        Dev::do(AgentHook::USER_PROMPT_SUBMIT, [
            'input' => $input,
            'tool_names' => $this->toolCatalog->names(),
        ]);

        // Create a prompt using the template
        $patterns = [];
        foreach (Arr::keys($this->replacements) as $pattern) {
            $patterns[] = '{' . $pattern . '}';
        }

        $prompt = str_replace($patterns, $this->replacements, $this->template);

        $this->fillIn->setPrompt($prompt);

        $result = $this->fillIn->run();
        $this->recordModelUsageSpans($trace);
        $encodedResult = $this->stringifyForTelemetry($result);

        $trace->addSpan($span->finish('completed', [
            'outcome_status' => 'completed',
            'input_tokens' => $this->estimateTokens((string)$input),
            'output_tokens' => $this->estimateTokens($encodedResult),
            'total_tokens' => $this->estimateTokens((string)$input) + $this->estimateTokens($encodedResult),
        ]));
        $trace->complete('completed');

        Dev::do(AgentHook::TURN_STOP, [
            'input' => $input,
            'reply' => $result,
            'task_id' => $trace->taskId(),
        ]);

        return $result;
    }

    /**
     * Record model usage reported by FillIn placeholders as child spans.
     */
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

    /**
     * Estimate token volume when a provider has not returned exact usage.
     */
    protected function estimateTokens(string $text): int
    {
        $text = Str::trim($text);
        if ($text === '') {
            return 0;
        }

        $words = [];
        foreach (Arr::make(Str::split($text, ' '))->toArray() as $word) {
            $word = Str::trim((string)$word);
            if ($word !== '') {
                $words[] = $word;
            }
        }

        return max(Arr::make($words)->count(), (int)ceil(Str::len($text) / 4));
    }

    /**
     * Render tool names in the grammar format expected by FillIn.
     */
    protected function formatToolNames(array $names): string
    {
        $output = '';
        foreach ($names as $name) {
            $quoted = "'" . $name . "'";
            $output .= $output === '' ? $quoted : ', ' . $quoted;
        }

        return $output;
    }

    /**
     * Serialize telemetry payloads through DevElation-friendly value objects where possible.
     */
    protected function stringifyForTelemetry(mixed $value): string
    {
        if ($value instanceof Reply) {
            return HTTP::jsonEncode($value->messages()->toArray());
        }

        if (Arr::is($value)) {
            return HTTP::jsonEncode($value);
        }

        return (string)$value;
    }

    /**
     * Apply human steering changes to the input when the input is array-shaped.
     */
    protected function steeredToolInput(mixed $input, GovernanceDecision $decision): mixed
    {
        if (Arr::is($input)) {
            return ToolDefinition::mergeConfig($input, $decision->payload());
        }

        $payload = $decision->payload();
        if (Arr::hasKey($payload, 'input')) {
            return $payload['input'];
        }

        return $input;
    }
}
