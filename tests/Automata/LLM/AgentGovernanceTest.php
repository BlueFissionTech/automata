<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\Governance\HumanReviewGate;
use BlueFission\Automata\LLM\Agent\Governance\TaskCallMonitor;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\MCP\IMCPTransport;
use BlueFission\Automata\LLM\MCP\MCPClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\Net\HTTP;
use BlueFission\Obj;
use PHPUnit\Framework\TestCase;

class GovernanceClientStub implements IClient
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

class GovernanceEchoTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'governance_echo';
        $this->description = 'Echoes governed input.';
    }

    public function execute($input): string
    {
        return Arr::is($input) ? (string)HTTP::jsonEncode($input) : (string)$input;
    }
}

class GovernanceMcpTransportStub implements IMCPTransport
{
    public array $last = [];

    public function send(array $server, array $payload): array
    {
        $this->last = [
            'server' => $server,
            'payload' => $payload,
        ];

        return [
            'server' => $server['name'] ?? 'unknown',
            'payload' => $payload,
        ];
    }
}

class AgentGovernanceTest extends TestCase
{
    public function testTaskCallMonitorRecordsReviewedMcpCall(): void
    {
        $trace = new TaskTrace('task-governed-mcp');
        $gate = new HumanReviewGate(fn (array $call): GovernanceDecision => GovernanceDecision::approved('reviewed'));
        $monitor = new TaskCallMonitor($trace, [
            'review_kinds' => [TaskTraceSpan::KIND_MCP],
        ], $gate);

        $result = $monitor->observe(
            TaskTraceSpan::KIND_MCP,
            'alpha.tools/list',
            fn (array $request): array => ['method' => $request['method'], 'ok' => true],
            ['method' => 'tools/list']
        );

        $spans = $trace->toArray()['spans'];

        $this->assertTrue($result['ok']);
        $this->assertSame(GovernanceDecision::STATUS_APPROVED, $result['decision']['status']);
        $this->assertCount(1, $gate->requests());
        $this->assertSame(TaskTraceSpan::KIND_MCP, $spans[0]['kind']);
        $this->assertSame(GovernanceDecision::STATUS_APPROVED, $spans[0]['metadata']['governance_status']);
    }

    public function testTaskCallMonitorBlocksDeniedCallBeforeExecution(): void
    {
        $trace = new TaskTrace('task-governed-block');
        $monitor = new TaskCallMonitor($trace, [
            'blocked_names' => ['danger'],
        ]);
        $executed = false;

        $result = $monitor->observe(
            TaskTraceSpan::KIND_API,
            'danger',
            function () use (&$executed): array {
                $executed = true;
                return ['ok' => true];
            }
        );

        $spans = $trace->toArray()['spans'];

        $this->assertFalse($result['ok']);
        $this->assertFalse($executed);
        $this->assertSame(GovernanceDecision::STATUS_DENIED, $result['status']);
        $this->assertSame('governance_denied', $result['error']['code']);
        $this->assertSame('failed', $spans[0]['status']);
    }

    public function testMcpClientUsesTaskCallMonitor(): void
    {
        $trace = new TaskTrace('task-governed-client');
        $transport = new GovernanceMcpTransportStub();
        $client = new MCPClient($transport);
        $client->registerServer('alpha', 'http://localhost:3333');
        $client->useCallMonitor(new TaskCallMonitor($trace, [
            'review_kinds' => [TaskTraceSpan::KIND_MCP],
        ], new HumanReviewGate(fn (): GovernanceDecision => GovernanceDecision::approved('approved'))));

        $result = $client->listTools('alpha');
        $spans = $trace->toArray()['spans'];

        $this->assertSame('tools/list', $result['payload']['method']);
        $this->assertSame('tools/list', $transport->last['payload']['method']);
        $this->assertSame(TaskTraceSpan::KIND_MCP, $spans[0]['kind']);
        $this->assertSame('alpha.tools/list', $spans[0]['name']);
    }

    public function testAgentHumanReviewGateCanSteerCriticalToolInput(): void
    {
        $agent = new Agent(new GovernanceClientStub());
        $agent->useHumanReviewGate(new HumanReviewGate(
            fn (): GovernanceDecision => GovernanceDecision::steered(['value' => 'steered'], 'approved with steering')
        ));
        $agent->registerTool('refund', new GovernanceEchoTool(), [
            'permission' => ToolDefinition::PERMISSION_CRITICAL,
            'requires_approval' => true,
        ]);

        $result = $agent->callTool('refund', ['value' => 'original']);

        $this->assertTrue($result->ok());
        $this->assertSame('{"value":"steered"}', $result->payload()['output']);
    }

    public function testAgentHookConstantsExposeGovernanceNames(): void
    {
        $this->assertContains(AgentHook::PRE_TASK_CALL, AgentHook::taskCalls());
        $this->assertContains(AgentHook::POST_TASK_CALL, AgentHook::taskCalls());
        $this->assertContains(AgentHook::HUMAN_REVIEW_REQUEST, AgentHook::all());
        $this->assertContains(AgentHook::HUMAN_REVIEW_DECISION, AgentHook::all());
    }

    public function testGovernanceDecisionUsesObjectFields(): void
    {
        $decision = GovernanceDecision::steered(['value' => 'changed'], 'reviewed');

        $this->assertInstanceOf(Obj::class, $decision);
        $this->assertSame(GovernanceDecision::STATUS_STEERED, $decision->field('status'));
        $this->assertSame('reviewed', $decision->message());
        $this->assertSame(['value' => 'changed'], $decision->payload());
    }
}
