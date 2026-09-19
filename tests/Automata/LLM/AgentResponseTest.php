<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResponseUnusedClient implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply { throw new LogicException('No model expected.'); }
    public function complete($input, $config = []): Reply { throw new LogicException('No model expected.'); }
    public function respond($input, $config = []): Reply { throw new LogicException('No model expected.'); }
}

final class AgentResponseTest extends TestCase
{
    private function agent(string $session = 'session-1', string $task = 'task-1'): Agent
    {
        $agent = new Agent(new ResponseUnusedClient());
        $agent->useSession(new AgentSession($session));
        $agent->startTask($task);
        return $agent;
    }

    private function envelope(): ResponseEnvelope
    {
        return new ResponseEnvelope('response-1', [
            new ResponseFragment(['id' => 'action', 'channel' => 'tool']),
            new ResponseFragment(['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['action']]),
        ]);
    }

    public function testOrchestratedWorkersProduceFragmentsAndReceiptsGateConfirmation(): void
    {
        $agent = $this->agent();
        $response = $agent->startResponse($this->envelope());
        $agent->configureOrchestration(['workers' => [
            'plan' => $response->worker('action', fn () => ['status' => 'completed', 'output' => ['tool' => 'fixture']]),
            'format' => $response->worker('confirmation', fn () => ['status' => 'completed', 'output' => false]),
        ]]);
        $orchestration = $agent->orchestrate();
        $this->assertNull($orchestration->confidence());
        $release = $response->prepare(0);
        $this->assertSame(['action'], array_column($release['fragments'], 'id'));
        $this->assertNull($release['fragments'][0]['confidence']);
        $this->assertSame($release, $response->prepare(1));
        $receipt = ['action' => ['successful' => true, 'evidence' => ['receiver' => 'fixture']]];
        $this->assertTrue($response->acknowledge($release['id'], $receipt));
        $this->assertFalse($response->acknowledge($release['id'], $receipt));
        $this->assertFalse($response->prepare(2)['fragments'][0]['payload']);
        $spans = Arr::make($agent->taskTrace()->spans())->map(fn ($span) => $span->toArray())->val();
        $acks = array_values(array_filter($spans, fn ($span) => $span['name'] === 'response.acknowledge'));
        $this->assertCount(1, $acks);
        $this->assertSame('response-1', $acks[0]['metadata']['response_id']);
        $this->assertSame('task-1', $acks[0]['task_id']);
    }

    public function testFailedWorkerCannotCreateSuccessConfirmation(): void
    {
        $response = $this->agent()->startResponse($this->envelope());
        $response->produce('action', fn () => ['status' => 'failed', 'output' => 'not successful']);
        $response->resolve('confirmation', 'done');
        $this->assertNull($response->prepare(0));
        $this->assertSame('failed', $response->state()['fragments']['action']['status']);
    }

    public function testOrchestrationConfidenceDistinguishesUnknownFromMeasuredZero(): void
    {
        foreach ([[null, null, null], [0.8, null, null], [0.0, 1.0, 0.5]] as [$first, $second, $expected]) {
            $orchestrator = new Orchestrator(['workers' => [
                'first' => fn () => ['output' => 'a', 'confidence' => $first],
                'second' => fn () => ['output' => 'b', 'confidence' => $second],
            ]]);
            $this->assertSame($expected, $orchestrator->run()->confidence());
        }
    }

    public function testCheckpointRestoresPendingReceiptButRejectsOtherSessionAndTask(): void
    {
        $agent = $this->agent();
        $response = $agent->startResponse($this->envelope())->resolve('action', null)->resolve('confirmation', 'done');
        $release = $response->prepare(0);
        $snapshot = json_decode(json_encode($response->snapshot(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true);
        $this->assertSame($release, $this->agent()->restoreResponse($snapshot)->prepare(1));
        foreach ([$this->agent('other'), $this->agent('session-1', 'other')] as $foreign) {
            try { $foreign->restoreResponse($snapshot); $this->fail('Expected scope rejection.'); }
            catch (LogicException $error) { $this->assertStringContainsString('scope', $error->getMessage()); }
        }
    }

    public function testScopeChangeAndCancellationPreventWorkerInvocation(): void
    {
        $agent = $this->agent();
        $response = $agent->startResponse($this->envelope());
        $calls = 0;
        $worker = function () use (&$calls) { ++$calls; return ['status' => 'completed', 'output' => 'x']; };
        $agent->startTask('other');
        try { $response->produce('action', $worker); $this->fail('Expected scope rejection.'); }
        catch (LogicException) { $this->assertSame(0, $calls); }
        $response = $agent->startResponse($this->envelope())->cancel('stopped');
        try { $response->produce('action', $worker); $this->fail('Expected cancellation rejection.'); }
        catch (LogicException) { $this->assertSame(0, $calls); }
    }

    public function testDuplicateAndUnknownWorkersAreRejectedBeforeInvocation(): void
    {
        $response = $this->agent()->startResponse($this->envelope());
        $calls = 0;
        $worker = function () use (&$calls) { ++$calls; return ['status' => 'completed', 'output' => null]; };
        $response->produce('action', $worker);
        foreach (['action', 'missing'] as $id) {
            try { $response->produce($id, $worker); $this->fail('Expected rejection.'); }
            catch (LogicException|InvalidArgumentException) { $this->assertSame(1, $calls); }
        }
    }

    public function testMalformedAndThrowingWorkersFailWithoutInventingConfidence(): void
    {
        foreach ([fn () => ['status' => 'completed'], fn () => ['status' => 'completed', 'output' => 'x', 'confidence' => '1'],
            fn () => throw new RuntimeException('worker fault')] as $worker) {
            $response = $this->agent()->startResponse($this->envelope());
            try { $response->produce('action', $worker); $this->fail('Expected worker failure.'); }
            catch (InvalidArgumentException|RuntimeException) {
                $this->assertSame('failed', $response->state()['fragments']['action']['status']);
                $this->assertNull($response->state()['fragments']['action']['confidence']);
            }
        }
    }

    public function testCancellationDuringProducerDiscardsItsLateOutput(): void
    {
        $response = $this->agent()->startResponse($this->envelope());
        $result = $response->produce('action', function () use ($response) {
            $response->cancel('interrupted');
            return ['status' => 'completed', 'output' => 'late'];
        });
        $this->assertSame('cancelled', $result['status']);
        $this->assertNull($response->prepare(0));
        $this->assertSame('cancelled', $response->state()['fragments']['action']['status']);
    }

    public function testActiveWorkerCannotBeCheckpointedOrReentered(): void
    {
        $response = $this->agent()->startResponse($this->envelope());
        $response->produce('action', function () use ($response) {
            foreach ([fn () => $response->snapshot(), fn () => $response->produce('action', fn () => [])] as $operation) {
                try { $operation(); $this->fail('Expected active worker rejection.'); }
                catch (LogicException) { $this->addToAssertionCount(1); }
            }
            return ['status' => 'completed', 'output' => 'x'];
        });
        $this->assertSame('ready', $response->state()['fragments']['action']['status']);
    }

    public function testTelemetryFailureDoesNotInvalidateProducedOutput(): void
    {
        $agent = $this->agent();
        $agent->useTaskTrace(new class('task-1') extends TaskTrace {
            public function recordTaskCall(string $kind, string $name, array $request = [], array $response = [], array $metadata = []): self
            { throw new RuntimeException('observer failed'); }
        });
        $response = $agent->startResponse($this->envelope());
        $response->produce('action', fn () => ['status' => 'completed', 'output' => 0]);
        $this->assertSame(0, $response->state()['fragments']['action']['payload']);
        $this->assertNotEmpty($response->telemetryErrors());
    }

    public function testCancellationRetainsReceiptAcrossAgentCheckpoint(): void
    {
        $agent = $this->agent();
        $response = $agent->startResponse($this->envelope())->resolve('action', 'command')->resolve('confirmation', 'done');
        $release = $response->prepare(0);
        $response->cancel('stopped');
        $restored = $agent->restoreResponse($response->snapshot());
        $this->assertTrue($restored->acknowledge($release['id'], ['action' => ['successful' => false, 'evidence' => []]]));
        $this->assertFalse($restored->state()['fragments']['action']['receipt']['successful']);
        $this->assertNull($restored->prepare(1));
    }

    public function testMalformedCheckpointAndWorkerScopeChangeAreRejected(): void
    {
        $agent = $this->agent();
        $snapshot = $agent->startResponse($this->envelope())->snapshot();
        foreach ([['schema_version' => 2], ['composer' => []], ['unexpected' => true]] as $change) {
            try { $agent->restoreResponse(array_replace($snapshot, $change)); $this->fail('Expected schema rejection.'); }
            catch (InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
        $response = $agent->startResponse($this->envelope());
        try {
            $response->produce('action', function () use ($agent) {
                $agent->startTask('different');
                return ['status' => 'completed', 'output' => 'stale'];
            });
            $this->fail('Expected scope rejection.');
        } catch (LogicException) {
            $this->assertSame('failed', $response->state()['fragments']['action']['status']);
        }
    }
}
