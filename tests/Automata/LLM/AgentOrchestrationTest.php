<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Orchestration\AgentOrchestrator;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTraceSpan;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use PHPUnit\Framework\TestCase;

class OrchestrationClientStub implements IClient
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

class AgentOrchestrationTest extends TestCase
{
    public function testSequentialPipelinePassesAccumulatedContext(): void
    {
        $orchestrator = new AgentOrchestrator([
            'pattern' => OrchestrationConfig::SEQUENTIAL,
            'workers' => [
                'parse' => fn (array $context): array => ['output' => ['field' => 'amount']],
                'format' => fn (array $context): array => ['output' => ['formatted' => $context['parse']['field'] . ':100']],
            ],
        ]);

        $result = $orchestrator->run(['document' => 'invoice'])->toArray();

        $this->assertSame('sequential', $result['pattern']);
        $this->assertSame('amount:100', $result['output']['format']['formatted']);
        $this->assertSame(2, count($result['worker_results']));
    }

    public function testFanOutMergeCollectsConflicts(): void
    {
        $orchestrator = new AgentOrchestrator([
            'pattern' => OrchestrationConfig::FAN_OUT,
            'workers' => [
                'field_a' => fn (): array => ['output' => ['amount' => 100, 'currency' => 'USD']],
                'field_b' => fn (): array => ['output' => ['amount' => 120, 'date' => '2026-05-17']],
            ],
        ]);

        $result = $orchestrator->run()->toArray();

        $this->assertSame([100, 120], $result['output']['amount']);
        $this->assertArrayHasKey('amount', $result['conflicts']);
        $this->assertSame('USD', $result['output']['currency']);
    }

    public function testHierarchicalPatternEscalatesLowConfidenceWorker(): void
    {
        $orchestrator = new AgentOrchestrator([
            'pattern' => OrchestrationConfig::HIERARCHICAL,
            'confidence_threshold' => 0.8,
            'supervisor' => fn (): array => ['output' => ['workers' => ['cheap']]],
            'workers' => [
                'cheap' => fn (): array => ['output' => ['answer' => 'draft'], 'confidence' => 0.5],
            ],
            'fallback' => fn (array $context): array => [
                'output' => ['answer' => 'verified'],
                'confidence' => 0.95,
                'metadata' => ['failed_worker' => $context['failed_worker']['name']],
            ],
        ]);

        $result = $orchestrator->run(['question' => 'x'])->toArray();

        $this->assertSame('hierarchical', $result['pattern']);
        $this->assertSame('verified', $result['output']['answer'][1]);
        $this->assertSame('fallback', $result['worker_results'][2]['name']);
    }

    public function testReflexivePatternStopsWhenVerifierPasses(): void
    {
        $attempt = 0;
        $orchestrator = new AgentOrchestrator([
            'pattern' => OrchestrationConfig::REFLEXIVE,
            'max_iterations' => 3,
            'producer' => function (array $context) use (&$attempt): array {
                $attempt++;
                return ['output' => ['draft' => 'version-' . $attempt], 'confidence' => 0.7 + ($attempt * 0.1)];
            },
            'verifier' => fn (array $context): array => [
                'output' => [
                    'passed' => ($context['iteration'] ?? 1) >= 2,
                    'feedback' => 'tighten evidence',
                ],
                'confidence' => 0.9,
            ],
        ]);

        $result = $orchestrator->run(['task' => 'review'])->toArray();

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['iterations']);
        $this->assertSame('version-2', $result['output']['draft']);
    }

    public function testAgentOrchestrationRecordsTraceSpan(): void
    {
        $agent = new Agent(new OrchestrationClientStub());
        $agent->startTask('task-orchestrate');
        $agent->configureOrchestration([
            'pattern' => OrchestrationConfig::FAN_OUT,
            'workers' => [
                'a' => fn (): array => ['output' => ['a' => 1]],
                'b' => fn (): array => ['output' => ['b' => 2]],
            ],
        ]);

        $result = $agent->orchestrate(['input' => true]);
        $trace = $agent->taskTrace()->toArray();

        $this->assertSame('completed', $result->status());
        $this->assertSame(TaskTraceSpan::KIND_ORCHESTRATION, $trace['spans'][0]['kind']);
        $this->assertSame('fan_out', $trace['spans'][0]['name']);
    }
}
