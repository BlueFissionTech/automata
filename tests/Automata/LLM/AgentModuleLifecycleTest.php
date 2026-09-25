<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Agent\State\AgentModuleLifecycle;
use BlueFission\Automata\LLM\Agent\State\AgentModuleLifecycleResult;
use BlueFission\Automata\LLM\Agent\State\AgentModuleRunRequest;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Automata\LLM\Agent\State\CallableAgentModule;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use PHPUnit\Framework\TestCase;

final class AgentModuleLifecycleClientStub implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        return $this->reply();
    }

    public function complete($input, $config = []): Reply
    {
        return $this->reply();
    }

    public function respond($input, $config = []): Reply
    {
        return $this->reply();
    }

    private function reply(): Reply
    {
        $reply = new Reply();
        $reply->addMessage('ok', true);

        return $reply;
    }
}

final class AgentModuleLifecycleTest extends TestCase
{
    public function testContractPublishesSupportedAndUnsupportedSemantics(): void
    {
        $contract = AgentModuleLifecycle::contract();

        $this->assertSame('1.0.0', $contract['version']);
        $this->assertContains('pre_invocation_cancellation', $contract['supported']);
        $this->assertContains('exception_normalization', $contract['supported']);
        $this->assertContains('in_flight_cancellation', $contract['unsupported']);
        $this->assertContains('progressive_output', $contract['unsupported']);
        $this->assertSame('host', $contract['effect_authorization_owner']);
        $this->assertSame('host', $contract['idempotency_owner']);
    }

    public function testApprovedModuleAppliesWritesAndPreservesLineage(): void
    {
        $agent = new Agent(new AgentModuleLifecycleClientStub());
        $module = new CallableAgentModule('planner', static fn (): array => [
            'module' => 'planner',
            'decision' => 'proceed',
            'writes' => [[
                'channel' => AgentState::DECISIONS,
                'key' => 'plan',
                'value' => 'proceed',
            ]],
        ]);
        $request = new AgentModuleRunRequest(GovernanceDecision::approved(), [
            'run_id' => 'run-107',
            'task_id' => 'task-107',
            'trace_id' => 'trace-107',
            'correlation_id' => 'correlation-107',
            'causation_id' => 'causation-107',
        ]);

        $result = $agent->runModuleLifecycle($module, $request);

        $this->assertSame(AgentModuleLifecycleResult::COMPLETED, $result->status());
        $this->assertSame('proceed', $result->decision());
        $this->assertSame('proceed', $agent->agentState()->read(AgentState::DECISIONS, 'plan'));
        $this->assertSame('trace-107', $result->lineage()['trace_id']);
        $this->assertNull($result->execution()['termination']['requested']);
        $this->assertNull($result->execution()['effects']['attributed_after_terminal']);
    }

    public function testDeniedModuleDoesNotInvokeOrApplyWrites(): void
    {
        $calls = 0;
        $state = new AgentState();
        $module = new CallableAgentModule('denied', static function () use (&$calls): array {
            $calls++;
            return ['writes' => [['channel' => AgentState::OUTPUTS, 'key' => 'unsafe', 'value' => true]]];
        });
        $request = new AgentModuleRunRequest(GovernanceDecision::denied('scope denied'), [
            'run_id' => 'run-denied',
        ]);

        $result = (new AgentModuleLifecycle())->run($module, $state, $request);

        $this->assertSame(AgentModuleLifecycleResult::DENIED, $result->status());
        $this->assertSame(0, $calls);
        $this->assertFalse($result->execution()['invoked']);
        $this->assertSame('authorization_denied', $result->diagnostics()[0]['code']);
        $this->assertSame([], $state->channel(AgentState::OUTPUTS));
    }

    public function testCancellationBeforeInvocationIsConfirmedWithoutCallingModule(): void
    {
        $calls = 0;
        $module = new CallableAgentModule('cancelled', static function () use (&$calls): array {
            $calls++;
            return [];
        });
        $request = new AgentModuleRunRequest(GovernanceDecision::approved(), [
            'run_id' => 'run-cancelled',
            'cancellation_requested' => true,
        ]);

        $result = (new AgentModuleLifecycle())->run($module, new AgentState(), $request);

        $this->assertSame(AgentModuleLifecycleResult::CANCELLED, $result->status());
        $this->assertSame(0, $calls);
        $this->assertTrue($result->execution()['termination']['requested']);
        $this->assertTrue($result->execution()['termination']['confirmed_stopped']);
        $this->assertSame('pre_invocation', $result->execution()['termination']['mechanism']);
    }

    public function testUnsupportedLifecycleRequestFailsBeforeInvocation(): void
    {
        $calls = 0;
        $module = new CallableAgentModule('stream', static function () use (&$calls): array {
            $calls++;
            return [];
        });
        $request = new AgentModuleRunRequest(GovernanceDecision::approved(), [
            'run_id' => 'run-stream',
            'requested_features' => ['progressive_output', 'in_flight_cancellation'],
        ]);

        $result = (new AgentModuleLifecycle())->run($module, new AgentState(), $request);

        $this->assertSame(AgentModuleLifecycleResult::UNSUPPORTED, $result->status());
        $this->assertSame(0, $calls);
        $this->assertSame(
            ['progressive_output', 'in_flight_cancellation'],
            $result->diagnostics()[0]['features']
        );
        $this->assertFalse($result->execution()['invoked']);
    }

    public function testExceptionsAreNormalizedWithoutLeakingMessages(): void
    {
        $module = new CallableAgentModule('failure', static function (): array {
            throw new \RuntimeException('private runtime details');
        });
        $request = new AgentModuleRunRequest(GovernanceDecision::approved(), ['run_id' => 'run-failed']);

        $result = (new AgentModuleLifecycle())->run($module, new AgentState(), $request);

        $this->assertSame(AgentModuleLifecycleResult::FAILED, $result->status());
        $this->assertSame('module_exception', $result->diagnostics()[0]['code']);
        $this->assertSame(\RuntimeException::class, $result->diagnostics()[0]['exception_type']);
        $this->assertStringNotContainsString('private runtime details', json_encode($result->toArray()));
    }

    public function testDurationLimitIsMeasuredAfterSynchronousCompletion(): void
    {
        $ticks = [10.000, 10.026];
        $clock = static function () use (&$ticks): float {
            return array_shift($ticks);
        };
        $module = new CallableAgentModule('bounded', static fn (): array => [
            'decision' => 'late',
            'writes' => [['channel' => AgentState::OUTPUTS, 'key' => 'late', 'value' => true]],
        ]);
        $request = new AgentModuleRunRequest(GovernanceDecision::approved(), [
            'run_id' => 'run-limit',
            'limits' => ['max_duration_ms' => 25],
        ]);

        $result = (new AgentModuleLifecycle($clock))->run($module, new AgentState(), $request);

        $this->assertSame(AgentModuleLifecycleResult::RESOURCE_LIMITED, $result->status());
        $this->assertSame(26, $result->execution()['duration_ms']);
        $this->assertFalse($result->execution()['termination']['requested']);
        $this->assertTrue($result->execution()['termination']['confirmed_stopped']);
        $this->assertSame('observed_after_completion', $result->execution()['termination']['mechanism']);
        $this->assertSame('duration_limit_exceeded', $result->diagnostics()[0]['code']);
        $this->assertSame('completed', $result->toArray()['upstream_status']);
    }
}
