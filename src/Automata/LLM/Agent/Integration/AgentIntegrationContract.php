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
use BlueFission\Automata\LLM\Agent\Lanes\AgentLane;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureManager;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureProfile;
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
    public const VERSION = '1.1.0';

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
    public const FEATURE_LANE_PRESSURE = 'agent.lane_pressure';

    public const TEMPLATE_AGENT = 'agent';
    public const TEMPLATE_TOOL = 'tool';
    public const TEMPLATE_HOOK = 'hook';
    public const TEMPLATE_SESSION = 'session';
    public const TEMPLATE_MEMORY = 'memory';
    public const TEMPLATE_HOLOSCENE = 'holoscene';
    public const TEMPLATE_GOVERNANCE = 'governance';
    public const TEMPLATE_MCP = 'mcp';
    public const TEMPLATE_ORCHESTRATION = 'orchestration';
    public const TEMPLATE_GOAL = 'goal';
    public const TEMPLATE_TRACE = 'trace';
    public const TEMPLATE_SECURITY = 'security';
    public const TEMPLATE_LANES = 'lanes';

    /**
     * Build the standard Automata integration surface for adapter contracts.
     */
    public function __construct(array $overrides = [])
    {
        parent::__construct();
        $this->assign(ToolDefinition::mergeConfig($this->defaults(), $overrides));
    }

    /**
     * Create the default upstream contract template for Automata agent features.
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
     * Return deterministic feature descriptors, optionally filtered by feature id.
     */
    public function features(?array $featureIds = null): array
    {
        $features = Arr::make($this->field('features') ?? [])->toArray();

        if (!$featureIds) {
            return $features;
        }

        $filtered = [];
        foreach (Arr::make($featureIds)->toArray() as $featureId) {
            if (Arr::hasKey($features, $featureId)) {
                $filtered[$featureId] = $features[$featureId];
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
     * Return the template other libraries can use to publish adapter contracts upstream.
     */
    public function contractTemplate(): array
    {
        return Arr::make($this->field('contract_template') ?? [])->toArray();
    }

    /**
     * Return neutral construct-to-feature hints for adapter-owned bindings.
     */
    public function bindingTemplate(?string $construct = null): array
    {
        $template = Arr::make($this->field('binding_template') ?? [])->toArray();

        if (!$construct) {
            return $template;
        }

        return Arr::make($template[$construct] ?? [])->toArray();
    }

    /**
     * Return neutral binding hints for callers that still use the older method name.
     */
    public function bindings(?string $construct = null): array
    {
        return $this->bindingTemplate($construct);
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
     * Return production integration checks that adapter contracts should satisfy.
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
            'features' => [
                self::FEATURE_AGENT => [
                    'summary' => 'Agent runtime entry point and configured execution boundary.',
                    'classes' => [Agent::class],
                    'constructs' => ['agent', 'agent.run', 'agent.configure'],
                    'inputs' => ['prompt', 'template', 'task_id', 'session'],
                    'outputs' => ['reply', 'trace', 'events'],
                ],
                self::FEATURE_TOOLS => [
                    'summary' => 'Deterministic tool definitions, catalog retrieval, execution, and structured results.',
                    'classes' => [ToolDefinition::class, ToolCatalog::class, ToolExecutor::class, ToolExecutionResult::class],
                    'constructs' => ['tool', 'tool.catalog', 'tool.call', 'tool.result'],
                    'inputs' => ['definition', 'catalog_filters', 'arguments', 'permission_context'],
                    'outputs' => ['definition_array', 'prompt_catalog', 'execution_result'],
                ],
                self::FEATURE_HOOKS => [
                    'summary' => 'Lifecycle hooks for deterministic adapter logging, memory capture, telemetry, and governance.',
                    'classes' => [AgentHook::class],
                    'constructs' => ['on.session_start', 'on.user_prompt_submit', 'on.pre_tool_use', 'on.post_tool_use', 'on.turn_stop'],
                    'inputs' => ['event_name', 'payload'],
                    'outputs' => ['event_payload', 'adapter_side_effect'],
                ],
                self::FEATURE_SESSION => [
                    'summary' => 'Shared session scope for permissions, context, uploaded inputs, tools, and working memory.',
                    'classes' => [AgentSession::class],
                    'constructs' => ['session', 'session.context', 'session.allow', 'session.memory'],
                    'inputs' => ['session_id', 'context', 'permissions', 'working_memory'],
                    'outputs' => ['scoped_context', 'permission_result', 'memory_handle'],
                ],
                self::FEATURE_MEMORY => [
                    'summary' => 'Memory and context injection through Automata working-memory contracts and lifecycle stores.',
                    'classes' => [AgentSession::class],
                    'constructs' => ['memory.remember', 'memory.recall', 'memory.inject'],
                    'inputs' => ['label', 'context', 'edges', 'injector'],
                    'outputs' => ['context', 'memory_events'],
                ],
                self::FEATURE_HOLOSCENE => [
                    'summary' => 'Holoscene comprehension for scoped sensory, narrative, scene, and episode context.',
                    'classes' => [Holoscene::class, Scene::class, Reader::class, IWorkingMemory::class],
                    'constructs' => ['holoscene', 'scene', 'episode', 'reader.to_holoscene', 'holoscene.narrate'],
                    'inputs' => ['statements', 'episode_id', 'scene', 'working_memory', 'session_scope'],
                    'outputs' => ['holoscene_snapshot', 'assessment', 'narrative_log', 'working_memory_context'],
                ],
                self::FEATURE_GOVERNANCE => [
                    'summary' => 'Human review, steering, policy gates, and governed task calls for tools, APIs, RPC, and MCP.',
                    'classes' => [TaskCallMonitor::class, HumanReviewGate::class, GovernanceDecision::class],
                    'constructs' => ['review.request', 'review.decision', 'task.call', 'task.policy'],
                    'inputs' => ['call', 'policy', 'reviewer'],
                    'outputs' => ['decision', 'call_result', 'trace_span'],
                ],
                self::FEATURE_MCP => [
                    'summary' => 'Observed and governed MCP discovery, resource, request, and tool-call surfaces.',
                    'classes' => [MCPClient::class, TaskCallMonitor::class],
                    'constructs' => ['mcp.server', 'mcp.resource', 'mcp.request', 'mcp.tool'],
                    'inputs' => ['server', 'resource', 'request', 'tool_arguments'],
                    'outputs' => ['mcp_result', 'trace_span', 'governance_decision'],
                ],
                self::FEATURE_ORCHESTRATION => [
                    'summary' => 'Sequential, fan-out, hierarchical, reflexive, and PIANO orchestration patterns.',
                    'classes' => [Orchestrator::class],
                    'constructs' => ['orchestrate', 'orchestrate.sequential', 'orchestrate.hierarchical', 'orchestrate.piano'],
                    'inputs' => ['pattern', 'workers', 'context', 'session'],
                    'outputs' => ['orchestration_result', 'worker_results', 'trace'],
                ],
                self::FEATURE_STATE_GOALS => [
                    'summary' => 'Behavioral state channels, cognitive controller seams, goals, criteria, and expectations.',
                    'classes' => [AgentState::class, GoalManager::class],
                    'constructs' => ['state.channel', 'goal', 'criterion', 'expectation', 'decision.option'],
                    'inputs' => ['state', 'goal', 'criteria', 'context'],
                    'outputs' => ['state_snapshot', 'goal_decisions', 'expectation_updates'],
                ],
                self::FEATURE_TELEMETRY => [
                    'summary' => 'Task-scoped CPCT traces for model, tool, MCP, batch, cache, and routing economics.',
                    'classes' => [TaskTrace::class],
                    'constructs' => ['trace.task', 'trace.span', 'trace.cpct', 'trace.routing'],
                    'inputs' => ['task_id', 'span', 'usage', 'outcome'],
                    'outputs' => ['trace_snapshot', 'cpct_report'],
                ],
                self::FEATURE_SECURITY => [
                    'summary' => 'Runtime logic validation and LPCI-oriented sanitization before content re-enters context.',
                    'classes' => [RuntimeLogicValidator::class],
                    'constructs' => ['security.scan', 'security.validate', 'security.sanitize'],
                    'inputs' => ['content', 'tool_result', 'memory_event'],
                    'outputs' => ['finding', 'sanitized_content', 'blocked_result'],
                ],
                self::FEATURE_LANE_PRESSURE => [
                    'summary' => 'Provider-neutral semantic, operational, and execution lane pressure assessment.',
                    'classes' => [AgentLane::class, LanePressureManager::class, LanePressureProfile::class],
                    'constructs' => ['lane.semantic', 'lane.operational', 'lane.execution', 'lane.pressure', 'lane.profile.long_horizon'],
                    'inputs' => ['lane_metrics', 'task_context', 'readiness_profile'],
                    'outputs' => ['dominant_lane', 'overall_level', 'recommendations'],
                ],
            ],
            'contract_template' => [
                'name' => 'family.adapter.contract',
                'direction' => 'adapter-to-upstream-runtime',
                'required_fields' => ['name', 'version', 'owner', 'target', 'feature_bindings', 'acceptance_criteria'],
                'feature_binding_shape' => [
                    'construct' => '<local language or adapter construct>',
                    'feature' => '<stable Automata feature id>',
                    'inputs' => '<adapter-owned input mapping>',
                    'outputs' => '<adapter-owned output mapping>',
                ],
                'boundary' => 'Automata owns runtime feature ids and class contracts; adapter libraries own syntax, query language, ingestion, and generated bindings.',
            ],
            'binding_template' => [
                self::TEMPLATE_AGENT => ['feature' => self::FEATURE_AGENT, 'constructs' => ['agent', 'agent.run', 'agent.configure']],
                self::TEMPLATE_TOOL => ['feature' => self::FEATURE_TOOLS, 'constructs' => ['tool', 'tool.catalog', 'tool.call', 'tool.result']],
                self::TEMPLATE_HOOK => ['feature' => self::FEATURE_HOOKS, 'constructs' => ['on.session_start', 'on.pre_tool_use', 'on.post_tool_use']],
                self::TEMPLATE_SESSION => ['feature' => self::FEATURE_SESSION, 'constructs' => ['session', 'session.context', 'session.allow']],
                self::TEMPLATE_MEMORY => ['feature' => self::FEATURE_MEMORY, 'constructs' => ['memory.remember', 'memory.recall', 'memory.inject']],
                self::TEMPLATE_HOLOSCENE => ['feature' => self::FEATURE_HOLOSCENE, 'constructs' => ['holoscene', 'scene', 'episode']],
                self::TEMPLATE_GOVERNANCE => ['feature' => self::FEATURE_GOVERNANCE, 'constructs' => ['review.request', 'review.decision', 'task.call']],
                self::TEMPLATE_MCP => ['feature' => self::FEATURE_MCP, 'constructs' => ['mcp.server', 'mcp.resource', 'mcp.tool']],
                self::TEMPLATE_ORCHESTRATION => ['feature' => self::FEATURE_ORCHESTRATION, 'constructs' => ['orchestrate', 'orchestrate.hierarchical', 'orchestrate.piano']],
                self::TEMPLATE_GOAL => ['feature' => self::FEATURE_STATE_GOALS, 'constructs' => ['state.channel', 'goal', 'criterion', 'expectation']],
                self::TEMPLATE_TRACE => ['feature' => self::FEATURE_TELEMETRY, 'constructs' => ['trace.task', 'trace.span', 'trace.cpct']],
                self::TEMPLATE_SECURITY => ['feature' => self::FEATURE_SECURITY, 'constructs' => ['security.scan', 'security.validate', 'security.sanitize']],
                self::TEMPLATE_LANES => ['feature' => self::FEATURE_LANE_PRESSURE, 'constructs' => ['lane.semantic', 'lane.operational', 'lane.execution', 'lane.pressure', 'lane.profile.long_horizon']],
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
