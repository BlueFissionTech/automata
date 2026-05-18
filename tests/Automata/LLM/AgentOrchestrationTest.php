<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestrationConfig;
use BlueFission\Automata\LLM\Agent\Orchestration\OrchestratedAgent;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;
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
        $orchestrator = new Orchestrator([
            'pattern' => OrchestrationConfig::SEQUENTIAL,
            'workers' => [
                'parse' => fn (array $context): array => ['output' => ['field' => 'amount']],
                'format' => fn (array $context): array => ['output' => ['formatted' => $context['parse']['field'] . ':100']],
            ],
        ]);

        $result = $orchestrator->run(['document' => 'invoice'])->toArray();

        $this->assertSame('sequential', $result['pattern']);
        $this->assertSame('amount:100', $result['output']['format']['formatted']);
        $this->assertCount(2, $result['worker_results']);
    }

    public function testFanOutMergeCollectsConflicts(): void
    {
        $orchestrator = new Orchestrator([
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
        $orchestrator = new Orchestrator([
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
        $orchestrator = new Orchestrator([
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

    public function testPianoPatternBroadcastsControllerDecisionToWorkers(): void
    {
        $orchestrator = new Orchestrator([
            'pattern' => OrchestrationConfig::PIANO,
            'supervisor' => fn (): array => ['output' => ['goal' => 'gather evidence'], 'confidence' => 0.9],
            'workers' => [
                'speech' => fn (array $context): array => [
                    'output' => ['spoken' => $context['controller_decision']['goal']],
                    'confidence' => 0.85,
                ],
                'action' => fn (array $context): array => [
                    'output' => ['action' => $context['controller_decision']['goal']],
                    'confidence' => 0.8,
                ],
            ],
        ]);

        $result = $orchestrator->run(['state' => ['energy' => 1]])->toArray();

        $this->assertSame('piano', $result['pattern']);
        $this->assertSame('gather evidence', $result['output']['spoken']);
        $this->assertSame('gather evidence', $result['output']['action']);
    }

    public function testNestedOrchestrationCanActAsBlackBoxAgent(): void
    {
        $innerMind = new OrchestratedAgent('villager_inner_mind', [
            'pattern' => OrchestrationConfig::HIERARCHICAL,
            'supervisor' => fn (): array => ['output' => ['workers' => ['lead', 'memory_counselor', 'policy_counselor']]],
            'workers' => [
                'lead' => fn (array $context): array => [
                    'output' => [
                        'proposal' => 'respond_to_' . $context['perceptions']['need'],
                        'private_seen' => Arr::hasKey($context, 'private_plan'),
                    ],
                ],
                'memory_counselor' => fn (array $context): array => [
                    'output' => ['memory' => $context['shared_context']['memory']],
                ],
                'policy_counselor' => fn (array $context): array => [
                    'output' => ['constraint' => $context['shared_context']['rule']],
                ],
            ],
        ], [
            'include' => ['perceptions', 'shared_context'],
        ]);

        $society = new Orchestrator([
            'pattern' => OrchestrationConfig::PIANO,
            'supervisor' => fn (): array => ['output' => ['goal' => 'coordinate society']],
            'workers' => [
                'villager' => $innerMind,
                'broadcast' => fn (array $context): array => [
                    'output' => ['broadcast' => $context['controller_decision']['goal']],
                ],
            ],
        ]);

        $result = $society->run([
            'perceptions' => ['need' => 'food'],
            'shared_context' => ['memory' => 'hungry', 'rule' => 'share tools'],
            'private_plan' => 'not in child scope',
        ])->toArray();

        $this->assertSame('respond_to_food', $result['output']['villager']['proposal']);
        $this->assertSame('hungry', $result['output']['villager']['memory']);
        $this->assertSame('share tools', $result['output']['villager']['constraint']);
        $this->assertFalse($result['output']['villager']['private_seen']);
        $this->assertArrayNotHasKey('proposal', $result['output']);
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
