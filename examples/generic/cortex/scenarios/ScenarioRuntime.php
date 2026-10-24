<?php

namespace CortexExample\Scenarios;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\Path\{Graph, Node};
use BlueFission\Automata\Strategy\CompositeStrategy;
use BlueFission\Automata\Strategy\Routing\StrategyRouter;
use BlueFission\Automata\Strategy\Workflow\{StrategyWorkflow, StrategyWorkflowRun};
use LogicException;

/** Small example host: all routing, scheduling and response state is Automata's. */
final class ScenarioRuntime
{
    /** Unexpected model traffic fails closed in deterministic runs. */
    public static function agent(string $id): Agent
    {
        $client = new class implements IClient {
            /** Reject accidental network-dependent generation in offline tests. */
            public function generate($input, $config = [], ?callable $callback = null): Reply
            {
                throw new LogicException('Deterministic scenario cannot call a provider.');
            }
            /** Completion is subject to the same deterministic-run boundary. */
            public function complete($input, $config = []): Reply
            {
                throw new LogicException('Deterministic scenario cannot call a provider.');
            }
            /** Conversation proposals are produced by explicitly bound workers. */
            public function respond($input, $config = []): Reply
            {
                throw new LogicException('Deterministic scenario cannot call a provider.');
            }
        };
        $agent = new Agent($client);
        $agent->useSession(new AgentSession($id . '-session'));
        $agent->startTask($id . '-task');
        return $agent;
    }

    /**
     * Admit a fresh immutable graph. Recomposition means a new host-admitted
     * plan, never mutating a running graph or automatically granting tools.
     */
    public static function workflow(string $id, array $workers, array $edges, array $outputs, array $input, array $modes = []): StrategyWorkflowRun
    {
        $graph = new Graph();
        $adapters = [];
        foreach ($workers as $name => $worker) {
            $graph->addNode(new Node($name, null, [], ['workflow' => [
                'strategy_id' => $name, 'strategy_version' => '1',
                'capability_id' => 'scenario.propose', 'capability_version' => '1',
                'allowed_modes' => [$modes[$name] ?? 'deterministic'],
            ]]));
            $adapters[] = new ScenarioAdapter($name, $worker, mode: $modes[$name] ?? 'deterministic');
        }
        foreach ($edges as [$from, $to]) { $graph->connect($from, $to); }
        $plan = new StrategyWorkflow($id, '1', $graph, $outputs);
        $authorize = static fn ($request): AutonomyDecision => new AutonomyDecision([
            'allowed' => true, 'subject_id' => $request['subject_id'],
            'code' => AutonomyDecision::CODE_ALLOWED,
            'capability_id' => 'scenario.propose', 'capability_version' => '1',
        ]);
        return (new CompositeStrategy($plan, new StrategyRouter($adapters), $authorize,
            'scenario-host', maximumDispatches: 16))->start($input, $id, $id);
    }

    /** A hard host ceiling complements the library dispatch budget. */
    public static function finish(StrategyWorkflowRun $run): array
    {
        for ($step = 0; $step < 16 && ($ready = $run->ready()) !== []; ++$step) {
            $run->execute($ready[0]);
        }
        return $run->result()->toArray();
    }
}
