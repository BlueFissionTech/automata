<?php

namespace BlueFission\Automata\LLM\Agent\Integration;

use BlueFission\Arr;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\Comprehension\Scene;
use BlueFission\Automata\Goal\GoalManager;
use BlueFission\Automata\Language\Reader;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\Governance\HumanReviewGate;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallMonitor;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;
use BlueFission\Automata\LLM\Agent\Security\RuntimeLogicValidator;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\Memory\IWorkingMemory;
use BlueFission\Automata\LLM\Agent\ToolCatalog;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\ToolExecutionResult;
use BlueFission\Automata\LLM\Agent\ToolExecutor;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Net\HTTP;
use BlueFission\Obj;

class AgentIntegrationContract extends Obj
{
    public const VERSION = '1.0.0';

    public const CONSUMER_JENSS = 'jenss';
    public const CONSUMER_JENERATOR = 'jenerator';
    public const CONSUMER_LINQR = 'linqr';
    public const CONSUMER_CHAINLINQ = 'chainlinq';

    public const FEATURE_AGENT = 'agent.runtime';
    public const FEATURE_TOOLS = 'agent.tool_contracts';
    public const FEATURE_HOOKS = 'agent.lifecycle_hooks';
    public const FEATURE_SESSION = 'agent.session_scope';
    public const FEATURE_MEMORY = 'agent.memory_context';
    public const FEATURE_HOLOSCENE = 'agent.holoscene_comprehension';
    public const FEATURE_GOVERNANCE = 'agent.governance';
    public const FEATURE_MCP = 'agent.mcp_observability';
    public const FEATURE_ORCHESTRATION = 'agent.orchestration';
    public const FEATURE_STATE_GOALS = 'agent.state_goals';
    public const FEATURE_TELEMETRY = 'agent.cpct_telemetry';
    public const FEATURE_SECURITY = 'agent.runtime_security';

    /**
     * Build the standard Automata integration surface for interpreter adapters.
     */
    public function __construct(array $overrides = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig($this->defaults(), $overrides));
    }

    /**
     * Create the default contract advertised to JenSS and query runtimes.
     */
    public static function standard(array $overrides = []): self
    {
        return new self($overrides);
    }

    /**
     * Return the contract version for compatibility checks.
     */
    public function version(): string
    {
        return (string)$this->field('version');
    }

    /**
     * Return deterministic feature descriptors, optionally filtered by consumer.
     */
    public function features(?string $consumer = null): array
    {
        $features = Arr::make($this->field('features') ?? [])->toArray();

        if (!$consumer) {
            return $features;
        }

        $filtered = [];
        foreach ($features as $name => $feature) {
            $consumers = Arr::make($feature['consumers'] ?? [])->toArray();
            if (Arr::contains($consumers, $consumer, true)) {
                $filtered[$name] = $feature;
            }
        }

        return $filtered;
    }

    /**
     * Return one feature descriptor by stable feature id.
     */
    public function feature(string $name): ?array
    {
        $features = $this->features();

        return Arr::hasKey($features, $name) ? $features[$name] : null;
    }

    /**
     * Determine whether the contract advertises a stable feature id.
     */
    public function supports(string $name): bool
    {
        return Arr::hasKey($this->features(), $name);
    }

    /**
     * Return target-language binding names for one consumer or all consumers.
     */
    public function bindings(?string $consumer = null): array
    {
        $bindings = Arr::make($this->field('bindings') ?? [])->toArray();

        if (!$consumer) {
            return $bindings;
        }

        return Arr::make($bindings[$consumer] ?? [])->toArray();
    }

    /**
     * Return the lifecycle hook names available to adapters.
     */
    public function hooks(): array
    {
        return Arr::make($this->field('hooks') ?? [])->toArray();
    }

    /**
     * Return supported catalog filter keys for interpreter-driven retrieval.
     */
    public function toolCatalogFilters(): array
    {
        return Arr::make($this->field('tool_catalog_filters') ?? [])->toArray();
    }

    /**
     * Return production integration checks that downstream adapters should satisfy.
     */
    public function acceptanceCriteria(): array
    {
        return Arr::make($this->field('acceptance_criteria') ?? [])->toArray();
    }

    /**
     * Encode the contract for storage, transport, or generated fixtures.
     */
    public function toJson(int $flags = 0): string
    {
        return (string)HTTP::jsonEncode($this->toArray());
    }

    /**
     * Define the complete standard contract without binding to a prompt format.
     */
    protected function defaults(): array
    {
        return [
            'name' => 'automata.agent.integration',
            'version' => self::VERSION,
            'owner' => 'automata',
            'consumers' => [
                self::CONSUMER_JENSS,
                self::CONSUMER_JENERATOR,
                self::CONSUMER_LINQR,
                self::CONSUMER_CHAINLINQ,
            ],
            'features' => [
                self::FEATURE_AGENT => [
                    'summary' => 'Agent runtime entry point and configured execution boundary.',
                    'classes' => [Agent::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR],
                    'constructs' => ['agent', 'agent.run', 'agent.configure'],
                    'inputs' => ['prompt', 'template', 'task_id', 'session'],
                    'outputs' => ['reply', 'trace', 'events'],
                ],
                self::FEATURE_TOOLS => [
                    'summary' => 'Deterministic tool definitions, catalog retrieval, execution, and structured results.',
                    'classes' => [ToolDefinition::class, ToolCatalog::class, ToolExecutor::class, ToolExecutionResult::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR, self::CONSUMER_LINQR, self::CONSUMER_CHAINLINQ],
                    'constructs' => ['tool', 'tool.catalog', 'tool.call', 'tool.result'],
                    'inputs' => ['definition', 'catalog_filters', 'arguments', 'permission_context'],
                    'outputs' => ['definition_array', 'prompt_catalog', 'execution_result'],
                ],
                self::FEATURE_HOOKS => [
                    'summary' => 'Lifecycle hooks for deterministic adapter logging, memory capture, telemetry, and governance.',
                    'classes' => [AgentHook::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR, self::CONSUMER_CHAINLINQ],
                    'constructs' => ['on.session_start', 'on.user_prompt_submit', 'on.pre_tool_use', 'on.post_tool_use', 'on.turn_stop'],
                    'inputs' => ['event_name', 'payload'],
                    'outputs' => ['event_payload', 'adapter_side_effect'],
                ],
                self::FEATURE_SESSION => [
                    'summary' => 'Shared session scope for permissions, context, uploaded inputs, tools, and working memory.',
                    'classes' => [AgentSession::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR],
                    'constructs' => ['session', 'session.context', 'session.allow', 'session.memory'],
                    'inputs' => ['session_id', 'context', 'permissions', 'working_memory'],
                    'outputs' => ['scoped_context', 'permission_result', 'memory_handle'],
                ],
                self::FEATURE_MEMORY => [
                    'summary' => 'Memory and context injection through Automata working-memory contracts and lifecycle stores.',
                    'classes' => [AgentSession::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR],
                    'constructs' => ['memory.remember', 'memory.recall', 'memory.inject'],
                    'inputs' => ['label', 'context', 'edges', 'injector'],
                    'outputs' => ['context', 'memory_events'],
                ],
                self::FEATURE_HOLOSCENE => [
                    'summary' => 'Holoscene comprehension for scoped sensory, narrative, scene, and episode context.',
                    'classes' => [Holoscene::class, Scene::class, Reader::class, IWorkingMemory::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR, self::CONSUMER_LINQR, self::CONSUMER_CHAINLINQ],
                    'constructs' => ['holoscene', 'scene', 'episode', 'reader.to_holoscene', 'holoscene.narrate'],
                    'inputs' => ['statements', 'episode_id', 'scene', 'working_memory', 'session_scope'],
                    'outputs' => ['holoscene_snapshot', 'assessment', 'narrative_log', 'working_memory_context'],
                ],
                self::FEATURE_GOVERNANCE => [
                    'summary' => 'Human review, steering, policy gates, and governed task calls for tools, APIs, RPC, and MCP.',
                    'classes' => [TaskCallMonitor::class, HumanReviewGate::class, GovernanceDecision::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR, self::CONSUMER_CHAINLINQ],
                    'constructs' => ['review.request', 'review.decision', 'task.call', 'task.policy'],
                    'inputs' => ['call', 'policy', 'reviewer'],
                    'outputs' => ['decision', 'call_result', 'trace_span'],
                ],
                self::FEATURE_MCP => [
                    'summary' => 'Observed and governed MCP discovery, resource, request, and tool-call surfaces.',
                    'classes' => [MCPClient::class, TaskCallMonitor::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR, self::CONSUMER_CHAINLINQ],
                    'constructs' => ['mcp.server', 'mcp.resource', 'mcp.request', 'mcp.tool'],
                    'inputs' => ['server', 'resource', 'request', 'tool_arguments'],
                    'outputs' => ['mcp_result', 'trace_span', 'governance_decision'],
                ],
                self::FEATURE_ORCHESTRATION => [
                    'summary' => 'Sequential, fan-out, hierarchical, reflexive, and PIANO orchestration patterns.',
                    'classes' => [Orchestrator::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR],
                    'constructs' => ['orchestrate', 'orchestrate.sequential', 'orchestrate.hierarchical', 'orchestrate.piano'],
                    'inputs' => ['pattern', 'workers', 'context', 'session'],
                    'outputs' => ['orchestration_result', 'worker_results', 'trace'],
                ],
                self::FEATURE_STATE_GOALS => [
                    'summary' => 'Behavioral state channels, cognitive controller seams, goals, criteria, and expectations.',
                    'classes' => [AgentState::class, GoalManager::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR],
                    'constructs' => ['state.channel', 'goal', 'criterion', 'expectation', 'decision.option'],
                    'inputs' => ['state', 'goal', 'criteria', 'context'],
                    'outputs' => ['state_snapshot', 'goal_decisions', 'expectation_updates'],
                ],
                self::FEATURE_TELEMETRY => [
                    'summary' => 'Task-scoped CPCT traces for model, tool, MCP, batch, cache, and routing economics.',
                    'classes' => [TaskTrace::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR, self::CONSUMER_CHAINLINQ, self::CONSUMER_LINQR],
                    'constructs' => ['trace.task', 'trace.span', 'trace.cpct', 'trace.routing'],
                    'inputs' => ['task_id', 'span', 'usage', 'outcome'],
                    'outputs' => ['trace_snapshot', 'cpct_report'],
                ],
                self::FEATURE_SECURITY => [
                    'summary' => 'Runtime logic validation and LPCI-oriented sanitization before content re-enters context.',
                    'classes' => [RuntimeLogicValidator::class],
                    'consumers' => [self::CONSUMER_JENSS, self::CONSUMER_JENERATOR],
                    'constructs' => ['security.scan', 'security.validate', 'security.sanitize'],
                    'inputs' => ['content', 'tool_result', 'memory_event'],
                    'outputs' => ['finding', 'sanitized_content', 'blocked_result'],
                ],
            ],
            'bindings' => [
                self::CONSUMER_JENSS => [
                    'agent' => self::FEATURE_AGENT,
                    'tool' => self::FEATURE_TOOLS,
                    'on' => self::FEATURE_HOOKS,
                    'session' => self::FEATURE_SESSION,
                    'memory' => self::FEATURE_MEMORY,
                    'holoscene' => self::FEATURE_HOLOSCENE,
                    'review' => self::FEATURE_GOVERNANCE,
                    'mcp' => self::FEATURE_MCP,
                    'orchestrate' => self::FEATURE_ORCHESTRATION,
                    'goal' => self::FEATURE_STATE_GOALS,
                    'trace' => self::FEATURE_TELEMETRY,
                    'security' => self::FEATURE_SECURITY,
                ],
                self::CONSUMER_JENERATOR => [
                    'runtime.agent' => self::FEATURE_AGENT,
                    'runtime.tool' => self::FEATURE_TOOLS,
                    'runtime.hook' => self::FEATURE_HOOKS,
                    'runtime.session' => self::FEATURE_SESSION,
                    'runtime.holoscene' => self::FEATURE_HOLOSCENE,
                    'runtime.trace' => self::FEATURE_TELEMETRY,
                ],
                self::CONSUMER_LINQR => [
                    'query.tool_catalog' => self::FEATURE_TOOLS,
                    'query.holoscene' => self::FEATURE_HOLOSCENE,
                    'query.trace' => self::FEATURE_TELEMETRY,
                ],
                self::CONSUMER_CHAINLINQ => [
                    'adapter.tool_catalog' => self::FEATURE_TOOLS,
                    'adapter.task_call' => self::FEATURE_GOVERNANCE,
                    'adapter.mcp' => self::FEATURE_MCP,
                    'adapter.holoscene' => self::FEATURE_HOLOSCENE,
                    'adapter.trace' => self::FEATURE_TELEMETRY,
                ],
            ],
            'hooks' => AgentHook::all(),
            'tool_catalog_filters' => [
                ToolCatalog::FILTER_CATEGORY,
                ToolCatalog::FILTER_PERMISSION,
                ToolCatalog::FILTER_TAGS,
                ToolCatalog::FILTER_TAGS_ALL,
                ToolCatalog::FILTER_GROUPS,
                ToolCatalog::FILTER_TAXONOMY,
                ToolCatalog::FILTER_INCLUDE,
                ToolCatalog::FILTER_EXCLUDE,
                ToolCatalog::FILTER_LIMIT,
                ToolCatalog::FILTER_PARALLEL_SAFE,
            ],
            'acceptance_criteria' => [
                'Adapters bind to these feature ids rather than concrete prompt strings.',
                'Language constructs produce deterministic arrays accepted by Automata classes.',
                'Tool and MCP calls emit task trace spans and governance outcomes.',
                'Session scope controls shared context instead of agents sharing full context windows.',
                'Holoscene episodes use scoped working memory and snapshots rather than raw prompt-only memory coupling.',
                'Conformance fixtures cover successful execution, blocked execution, review steering, and trace export.',
            ],
        ];
    }
}
