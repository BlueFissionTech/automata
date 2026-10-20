<?php

namespace CortexExample\Scenarios;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Response\AgentResponse;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\Automata\Response\{ResponseEnvelope, ResponseFragment};
use BlueFission\Automata\Support\RecordSnapshot;
use Closure;
use LogicException;

/**
 * Process-local receiver fixture around Agent's real governed tool execution.
 *
 * It deliberately retains pending identities and unknown effects. This is not
 * a durable database transaction or proof of production exactly-once execution.
 */
final class EffectHost
{
    public readonly Agent $agent;
    private array $deliveries = [];

    /** The host binds the only permitted world mutation before accepting plans. */
    public function __construct(string $id, callable $effect)
    {
        $this->agent = ScenarioRuntime::agent($id);
        $tool = new class($effect) extends BaseTool {
            private Closure $effect;
            /** The policy cannot replace the registered host implementation. */
            public function __construct(callable $effect) { $this->effect = Closure::fromCallable($effect); }
            /** A thrown result may follow an effect; the receiver must reconcile. */
            public function execute($input): string { return ($this->effect)($input); }
        };
        $this->agent->registerTool('world.apply', $tool, [
            'permission' => 'write', 'requires_approval' => true, 'max_retries' => 0,
        ]);
    }

    /** Prepare a tool proposal plus a receipt-dependent factual confirmation. */
    public function response(string $id, array $proposal): AgentResponse
    {
        return $this->agent->startResponse(new ResponseEnvelope($id, [
            new ResponseFragment(['id' => 'action', 'channel' => 'tool']),
            new ResponseFragment(['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['action']]),
        ]))->resolve('action', $proposal)->resolve('confirmation', ['completed' => true, 'proposal' => $proposal]);
    }

    /** Retried deliveries reuse a terminal receipt; uncertainty never re-executes. */
    public function deliver(array $release, bool $approved): array
    {
        $id = $release['id'];
        $fingerprint = RecordSnapshot::fingerprint($release);
        if (isset($this->deliveries[$id])) {
            $entry = $this->deliveries[$id];
            if ($entry['fingerprint'] !== $fingerprint) { throw new LogicException('Conflicting release identity.'); }
            if ($entry['receipt'] === null) { throw new LogicException('Reconcile unknown effect before further delivery.'); }
            return $entry['receipt'];
        }
        $fragment = $release['fragments'][0];
        if ($fragment['id'] !== 'action' || $fragment['channel'] !== 'tool') {
            throw new LogicException('Receiver accepts only the declared action.');
        }
        $this->deliveries[$id] = ['fingerprint' => $fingerprint, 'receipt' => null];
        $execution = $this->agent->callTool('world.apply', $fragment['payload'], ['approved' => $approved]);
        if (($execution->errorDetails()['code'] ?? null) === 'tool_failed') {
            throw new LogicException('Effect result unknown.');
        }
        $receipt = ['action' => ['successful' => $execution->ok(),
            'evidence' => ['status' => $execution->status()]]];
        $this->deliveries[$id]['receipt'] = $receipt;
        return $receipt;
    }
}
