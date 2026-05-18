<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\ToolCatalog;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Agent\ToolExecutionResult;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\Net\HTTP;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ToolContractClientStub implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $reply = new Reply();
        $reply->addMessage('generated', true);
        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('completed', true);
        return $reply;
    }

    public function respond($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('responded', true);
        return $reply;
    }
}

class EchoContractTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'echo';
        $this->description = 'Echoes normalized input.';
    }

    public function execute($input): string
    {
        return Arr::is($input) ? (string)HTTP::jsonEncode($input) : 'echo:' . (string)$input;
    }
}

class FailingContractTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'failing';
        $this->description = 'Always fails.';
    }

    public function execute($input): string
    {
        throw new RuntimeException('simulated failure');
    }
}

class AgentToolContractsTest extends TestCase
{
    public function testLegacyToolRegistrationCreatesPromptDefinition(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('echo', new EchoContractTool());

        $definitions = $agent->toolDefinitions();

        $this->assertArrayHasKey('echo', $definitions);
        $this->assertSame('Echoes normalized input.', $definitions['echo']['description']);
        $this->assertStringContainsString('echo: Echoes normalized input.', $agent->toolPrompt());
        $this->assertStringContainsString('Permission: read', $agent->toolPrompt());
    }

    public function testToolInputContractNormalizesAndExecutesObjectInput(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('search', new EchoContractTool(), [
            'name' => 'search',
            'purpose' => 'Search current context.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['query'],
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
            ],
            'output_schema' => [
                'type' => 'object',
                'properties' => [
                    'output' => ['type' => 'string'],
                ],
            ],
        ]);

        $result = $agent->callTool('search', 'weather today');

        $this->assertTrue($result->ok());
        $this->assertSame(ToolExecutionResult::STATUS_SUCCESS, $result->status());
        $this->assertSame('{"query":"weather today"}', $result->payload()['output']);
    }

    public function testToolInputContractReportsEnumValidationErrors(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('search', new EchoContractTool(), [
            'input_schema' => [
                'type' => 'object',
                'required' => ['query', 'sort'],
                'properties' => [
                    'query' => ['type' => 'string'],
                    'sort' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                ],
            ],
        ]);

        $result = $agent->callTool('search', ['query' => 'cost', 'sort' => 'sideways']);

        $this->assertFalse($result->ok());
        $this->assertSame(ToolExecutionResult::STATUS_VALIDATION_ERROR, $result->status());
        $message = '';
        foreach ($result->errorDetails()['details']['errors'] as $error) {
            $message .= $message === '' ? $error : "\n" . $error;
        }

        $this->assertStringContainsString('input.sort must be one of', $message);
    }

    public function testCriticalToolRequiresApproval(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('refund', new EchoContractTool(), [
            'permission' => ToolDefinition::PERMISSION_CRITICAL,
            'requires_approval' => true,
        ]);

        $denied = $agent->callTool('refund', 'order-1');
        $approved = $agent->callTool('refund', 'order-1', ['approved' => true]);

        $this->assertSame(ToolExecutionResult::STATUS_PERMISSION_DENIED, $denied->status());
        $this->assertTrue($approved->ok());
    }

    public function testToolCatalogFiltersPromptByTags(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('web_search', new EchoContractTool(), [
            'purpose' => 'Search current web information.',
            'tags' => ['current', 'read'],
            'groups' => ['retrieval'],
            'taxonomy' => [
                'domain' => ['web'],
                'freshness' => ['current'],
            ],
            'decision_boundary' => 'current or time-sensitive facts',
            'negative_guidance' => 'questions answerable from stable local context',
            'parallel_safe' => true,
        ]);
        $agent->registerTool('note_write', new EchoContractTool(), [
            'purpose' => 'Write a note.',
            'tags' => ['write'],
            'permission' => ToolDefinition::PERMISSION_WRITE,
        ]);

        $prompt = $agent->toolPrompt([ToolCatalog::FILTER_TAGS => ['current']]);

        $this->assertStringContainsString('web_search', $prompt);
        $this->assertStringContainsString('Do not use when:', $prompt);
        $this->assertStringNotContainsString('note_write', $prompt);
    }

    public function testToolCatalogFiltersByGroupsAndTaxonomy(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('memory_search', new EchoContractTool(), [
            'purpose' => 'Search working memory.',
            'tags' => ['memory', 'read'],
            'groups' => ['agent.lifecycle'],
            'taxonomy' => [
                'domain' => ['memory'],
                'lifecycle' => ['user_prompt_submit'],
                'risk' => ['read_only'],
            ],
        ]);
        $agent->registerTool('external_write', new EchoContractTool(), [
            'purpose' => 'Write externally.',
            'groups' => ['external'],
            'taxonomy' => [
                'domain' => ['external'],
                'risk' => ['write'],
            ],
        ]);

        $definitions = $agent->toolDefinitions([
            ToolCatalog::FILTER_GROUPS => ['agent.lifecycle'],
            ToolCatalog::FILTER_TAXONOMY => [
                'risk' => ['read_only'],
            ],
        ]);

        $this->assertArrayHasKey('memory_search', $definitions);
        $this->assertArrayNotHasKey('external_write', $definitions);
    }

    public function testToolDefinitionUsesConfigurableContract(): void
    {
        $definition = new ToolDefinition([
            'name' => 'search',
            'category' => 'retrieval',
            'tags' => ['memory', 'memory'],
        ]);

        $definition->config('category', 'knowledge');

        $this->assertSame('knowledge', $definition->category());
        $this->assertSame(['memory'], $definition->tags());
    }

    public function testAgentHookConstantsExposeLifecycleNames(): void
    {
        $this->assertContains(AgentHook::SESSION_START, AgentHook::all());
        $this->assertContains(AgentHook::USER_PROMPT_SUBMIT, AgentHook::all());
        $this->assertContains(AgentHook::PERMISSION_REQUEST, AgentHook::toolUse());
        $this->assertContains(AgentHook::PRE_TOOL_USE, AgentHook::toolUse());
        $this->assertContains(AgentHook::POST_TOOL_USE, AgentHook::toolUse());
        $this->assertContains(AgentHook::TURN_STOP, AgentHook::all());
    }

    public function testRepeatedToolFailureOpensCircuit(): void
    {
        $agent = new Agent(new ToolContractClientStub());
        $agent->registerTool('failing', new FailingContractTool(), [
            'failure_threshold' => 1,
            'max_retries' => 0,
        ]);

        $first = $agent->callTool('failing', 'input');
        $second = $agent->callTool('failing', 'input');

        $this->assertSame(ToolExecutionResult::STATUS_ERROR, $first->status());
        $this->assertSame(ToolExecutionResult::STATUS_CIRCUIT_OPEN, $second->status());
    }
}
