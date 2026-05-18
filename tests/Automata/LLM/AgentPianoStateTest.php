<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\State\ActionAwareness;
use BlueFission\Automata\LLM\Agent\State\AgentState;
use BlueFission\Automata\LLM\Agent\State\CallableAgentModule;
use BlueFission\Automata\LLM\Agent\State\CognitiveController;
use BlueFission\Automata\Goal\ComparisonOperator;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Goal\GoalManager;
use BlueFission\Automata\Goal\Initiative;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use PHPUnit\Framework\TestCase;

class PianoClientStub implements IClient
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

class AgentPianoStateTest extends TestCase
{
    public function testAgentStateChannelsRemainIsolated(): void
    {
        $state = new AgentState();
        $state->write(AgentState::GOALS, 'primary', 'collect food');
        $state->write(AgentState::RULES, 'tax', 'contribute 20 percent');

        $this->assertSame('collect food', $state->read(AgentState::GOALS, 'primary'));
        $this->assertSame('contribute 20 percent', $state->read(AgentState::RULES, 'tax'));
        $this->assertSame([], $state->channel(AgentState::SOCIAL));
    }

    public function testAgentStateUsesDevelationStateMachineForBehaviorGates(): void
    {
        $state = new AgentState();
        $state->allowInState(AgentState::STATE_ACTING, AgentState::ACTION_USE_TOOL);
        $state->denyInState(AgentState::STATE_ACTING, AgentState::ACTION_SPEAK);
        $state->enter(AgentState::STATE_ACTING);

        $this->assertTrue($state->canPerform(AgentState::ACTION_USE_TOOL));
        $this->assertFalse($state->canPerform(AgentState::ACTION_SPEAK));
        $this->assertTrue($state->is(AgentState::STATE_ACTING));
    }

    public function testCognitiveControllerAppliesBottleneckPriorities(): void
    {
        $state = new AgentState();
        $state->write(AgentState::RULES, 'constitution', 'share resources');
        $state->write(AgentState::GOALS, 'community', 'build shelter');
        $state->write(AgentState::OBSERVATIONS, 'noise', 'ignored after limit');
        $state->write(AgentState::SOCIAL, 'neighbor', ['affinity' => 0.8]);

        $controller = new CognitiveController([AgentState::RULES, AgentState::GOALS], 1);
        $result = $controller->decide($state, ['intent' => 'assign_builder']);
        $bottleneck = $result->toArray()['metadata']['bottleneck'];

        $this->assertArrayHasKey(AgentState::RULES, $bottleneck);
        $this->assertArrayHasKey(AgentState::GOALS, $bottleneck);
        $this->assertArrayNotHasKey(AgentState::SOCIAL, $bottleneck);
        $this->assertSame(['intent' => 'assign_builder'], $state->read(AgentState::OUTPUTS, 'controller_decision'));
    }

    public function testCognitiveControllerUsesAutomataGoalManagerForDeterministicOptions(): void
    {
        $state = new AgentState();
        $initiative = new Initiative(['name' => 'survive', 'weight' => 2]);
        $initiative->addCondition(new Condition([
            'name' => 'food',
            'path' => 'food',
            'operator' => ComparisonOperator::AT_LEAST,
            'expected' => 1,
            'priority' => 3,
            'action' => 'gather-food',
        ]));
        $state->addGoal($initiative);

        $controller = new CognitiveController(goalManager: new GoalManager([$initiative]));
        $result = $controller->decide($state, null, ['food' => 0]);
        $decision = $result->decision();

        $this->assertSame('gather-food', $decision['selected']['action']);
        $this->assertSame('survive', $decision['selected']['goal']);
        $this->assertGreaterThan(0, $decision['selected']['score']);
    }

    public function testActionAwarenessComparesExpectedAndObservedOutcomes(): void
    {
        $state = new AgentState();

        ActionAwareness::expect($state, 'give-pickaxe', 'pickaxe transferred');
        $mismatch = ActionAwareness::observe($state, 'give-pickaxe', 'agent explored');
        ActionAwareness::expect($state, 'gather-food', 'food collected');
        $match = ActionAwareness::observe($state, 'gather-food', 'food collected');

        $this->assertFalse($mismatch['matched']);
        $this->assertTrue($match['matched']);
        $this->assertCount(2, $state->read(AgentState::OBSERVATIONS, 'action_awareness'));
    }

    public function testAgentRunsStatelessModuleAgainstSharedState(): void
    {
        $agent = new Agent(new PianoClientStub());
        $module = new CallableAgentModule('speech', fn (AgentState $state): array => [
            'module' => 'speech',
            'decision' => 'say: gathering food',
            'writes' => [
                ['channel' => AgentState::OUTPUTS, 'key' => 'speech', 'value' => 'gathering food'],
            ],
        ]);

        $result = $agent->runModule($module);

        $this->assertSame('say: gathering food', $result->decision());
        $this->assertSame('gathering food', $agent->agentState()->read(AgentState::OUTPUTS, 'speech'));
    }

    public function testAgentControllerBroadcastsDecisionToState(): void
    {
        $agent = new Agent(new PianoClientStub());
        $agent->agentState()->write(AgentState::GOALS, 'role', 'farmer');

        $result = $agent->cognitiveDecision(['role' => 'farmer', 'action' => 'plant seeds']);

        $this->assertSame(['role' => 'farmer', 'action' => 'plant seeds'], $result->decision());
        $this->assertSame(['role' => 'farmer', 'action' => 'plant seeds'], $agent->agentState()->read(AgentState::OUTPUTS, 'controller_decision'));
        $this->assertSame([['role' => 'farmer', 'action' => 'plant seeds']], $agent->agentState()->channel(AgentState::DECISIONS));
    }
}
