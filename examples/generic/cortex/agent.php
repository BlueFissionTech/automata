<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;
use BlueFission\Automata\Support\RecordSnapshot;

// The Agent exercises its real orchestration, tool governance and TaskTrace paths.
$client = new class implements IClient {
    public function generate($input, $config = [], ?callable $callback = null): Reply { throw new LogicException('No provider calls allowed.'); }
    public function complete($input, $config = []): Reply { throw new LogicException('No provider calls allowed.'); }
    public function respond($input, $config = []): Reply { throw new LogicException('No provider calls allowed.'); }
};
$agent = new Agent($client);
$agent->useSession(new AgentSession('cortex-fixture-session'));
$agent->startTask('cortex-fixture-task');
$tool = new class extends BaseTool {
    public int $effects = 0;
    public bool $loseResult = false;
    public function execute($input): string
    {
        ++$this->effects;
        if ($this->loseResult) { throw new RuntimeException('Simulated result loss after the effect.'); }
        return 'simulated dispatch completed';
    }
};
$agent->registerTool('dispatch', $tool, ['permission' => 'write', 'requires_approval' => true, 'max_retries' => 0]);
$scope = ['tenant_id' => 'tenant-a', 'actor_id' => 'actor-a', 'receiver_id' => 'fixture-receiver',
    'session_id' => $agent->session()->id(), 'task_id' => $agent->taskId(), 'schema_version' => 1];
$currentScope = $scope;
$enabled = true;
$approved = true;
$ledger = [];
$deliveries = [];
// Host-owned fixture ledger, deliberately retained across the caller's simulated restart.
$deliver = function (array $release, array $binding) use ($agent, $scope, &$currentScope, &$enabled, &$approved, &$ledger, &$deliveries): array {
    if (RecordSnapshot::canonical($binding) !== RecordSnapshot::canonical($scope)
        || RecordSnapshot::canonical($binding) !== RecordSnapshot::canonical($currentScope)
        || $binding['session_id'] !== $agent->session()->id() || $binding['task_id'] !== $agent->taskId()) {
        throw new LogicException('Receiver scope or version mismatch.');
    }
    $id = $release['id'];
    $digest = RecordSnapshot::fingerprint($release);
    if (isset($ledger[$id]) && $ledger[$id]['digest'] !== $digest) { throw new LogicException('Conflicting delivery identity.'); }
    $ledger[$id] ??= ['digest' => $digest, 'receipts' => [], 'uncertain' => []];
    foreach ($release['fragments'] as $fragment) {
        $fragmentId = $fragment['id'];
        if (isset($ledger[$id]['receipts'][$fragmentId])) { continue; }
        if (isset($ledger[$id]['uncertain'][$fragmentId])) { throw new LogicException('Reconcile existing effect; do not retry.'); }
        $successful = true;
        $evidence = ['binding' => $binding, 'release_id' => $id, 'fragment_id' => $fragmentId];
        if ($fragment['channel'] === 'tool') {
            if ($fragment['resolution'] !== 'primary' || ($fragment['payload']['tool'] ?? null) !== 'dispatch') {
                throw new LogicException('Fixture receiver rejects unknown operations.');
            }
            $ledger[$id]['uncertain'][$fragmentId] = true;
            $result = $agent->callTool('dispatch', $fragment['payload']['input'] ?? null,
                ['approved' => $enabled && $approved, 'task_id' => $binding['task_id']]);
            if (($result->errorDetails()['code'] ?? null) === 'tool_failed') {
                throw new LogicException('Tool outcome unknown; receipt not terminal.');
            }
            $successful = $result->ok();
            $evidence['tool_status'] = $result->status();
            unset($ledger[$id]['uncertain'][$fragmentId]);
        }
        $ledger[$id]['receipts'][$fragmentId] = ['successful' => $successful, 'evidence' => $evidence];
        $deliveries[] = ['release_id' => $id, 'fragment_id' => $fragmentId, 'successful' => $successful];
    }
    return $ledger[$id]['receipts'];
};
$start = static function (string $id) use ($agent) {
    return $agent->startResponse(new ResponseEnvelope($id, [
        new ResponseFragment(['id' => 'dispatch', 'channel' => 'tool']),
        new ResponseFragment(['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['dispatch']]),
    ]));
};
['candidate' => $candidate] = require __DIR__ . '/models.php';
$response = $agent->startResponse(new ResponseEnvelope('agent-response', [
    new ResponseFragment(['id' => 'greeting', 'channel' => 'text']),
    new ResponseFragment(['id' => 'dispatch', 'channel' => 'tool']),
    new ResponseFragment(['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['dispatch']]),
]), new ResponsePolicy(['threshold' => 0.3, 'minimum_threshold' => 0.3]));
$response->produce('greeting', static fn () => ['status' => 'completed', 'output' => 'I am checking the request.']);
$early = $response->prepare(0);
$response->acknowledge($early['id'], $deliver($early, $scope));
$earlyWithoutEffect = $tool->effects === 0;
$agent->configureOrchestration(['workers' => [
    'intent' => $response->worker('dispatch', static fn () => ['status' => 'completed',
        'output' => ['tool' => 'dispatch', 'input' => ['intent' => $candidate->strategy()->predict('confirm booking')]]]),
    'confirmation' => $response->worker('confirmation', static fn () => ['status' => 'completed', 'output' => 'Dispatch completed.']),
]]);
$orchestration = $agent->orchestrate();
if ($orchestration->status() !== 'completed') {
    throw new RuntimeException('Fixture orchestration failed: ' . json_encode($orchestration->toArray(), JSON_THROW_ON_ERROR));
}
$release = $response->prepare(1);
$checkpoint = $response->snapshot();
$receipt = $deliver($release, $scope); // Caller loses this acknowledgement, but the receiver retains it.
$response = $agent->restoreResponse($checkpoint);
$replayed = $response->prepare(2);
$response->acknowledge($replayed['id'], $deliver($replayed, $scope));
$confirmation = $response->prepare(3);
$response->acknowledge($confirmation['id'], $deliver($confirmation, $scope));
$checks = [
    'early_output_precedes_plan_and_effect' => $early['fragments'][0]['id'] === 'greeting' && $earlyWithoutEffect,
    'classifier_worker_produces_tool_plan' => $release['fragments'][0]['payload']['input']['intent'] === 'checkin',
    'unknown_worker_confidence_remains_null' => $release['fragments'][0]['confidence'] === null && $orchestration->confidence() === null,
    'pending_identity_survives_agent_restore' => $replayed === $release,
    'governed_tool_effect_not_repeated_after_lost_ack' => $tool->effects === 1,
    'confirmation_after_successful_tool_receipt' => $confirmation['fragments'][0]['id'] === 'confirmation',
];
foreach (['tenant_id', 'actor_id', 'receiver_id', 'schema_version'] as $field) {
    $bad = $scope;
    $bad[$field] = $field === 'schema_version' ? 2 : 'wrong';
    try { $deliver($release, $bad); $checks['reject_' . $field] = false; }
    catch (LogicException) { $checks['reject_' . $field] = $tool->effects === 1; }
}
foreach (['denied', 'revoked'] as $scenario) {
    $probe = $start($scenario)->resolve('dispatch', ['tool' => 'dispatch'])->resolve('confirmation', 'Must not appear.');
    $prepared = $probe->prepare(0);
    if ($scenario === 'denied') { $approved = false; }
    else { $approved = true; $enabled = false; } // Refreshed eligibility after preparation.
    $probe->acknowledge($prepared['id'], $deliver($prepared, $scope));
    $checks[$scenario . '_prevents_effect_and_confirmation'] = $tool->effects === 1 && $probe->prepare(1) === null;
}
$enabled = $approved = true;
$cancelled = $start('cancel-before-send')->resolve('dispatch', ['tool' => 'dispatch'])->resolve('confirmation', 'done')->cancel('host_cancelled');
$checks['cancel_before_send_has_no_delivery'] = $cancelled->prepare(0) === null && $tool->effects === 1;
$inflight = $start('cancel-inflight')->resolve('dispatch', ['tool' => 'dispatch'])->resolve('confirmation', 'done');
$pending = $inflight->prepare(0);
$terminal = $deliver($pending, $scope);
$inflight->cancel('host_cancelled')->acknowledge($pending['id'], $terminal);
$checks['cancel_after_send_keeps_receipt_and_stops_confirmation'] = $inflight->prepare(1) === null
    && RecordSnapshot::canonical($inflight->state()['fragments']['dispatch']['receipt']) === RecordSnapshot::canonical($terminal['dispatch']);
$uncertain = $start('uncertain-tool')->resolve('dispatch', ['tool' => 'dispatch'])->resolve('confirmation', 'done');
$pending = $uncertain->prepare(0);
$tool->loseResult = true;
$before = $tool->effects;
for ($attempt = 0; $attempt < 2; ++$attempt) {
    try { $deliver($pending, $scope); } catch (LogicException) { /* Remains pending for receiver reconciliation. */ }
}
$checks['unknown_result_never_retries_effect'] = $tool->effects === $before + 1;
$checks['unknown_result_cannot_unlock_confirmation'] = $uncertain->prepare(1) === $pending
    && $uncertain->state()['fragments']['dispatch']['receipt'] === null;
$spans = Arr::make($agent->taskTrace()->spans())->map(static fn ($span): array => $span->toArray())->val();
$names = Arr::make($spans)->map(static fn (array $span): string => $span['name'])->values()->val();
$checks['trace_contains_workers_tool_release_and_receipts'] = Arr::has($names, 'response.produce', true)
    && Arr::has($names, 'dispatch', true) && Arr::has($names, 'response.prepare', true) && Arr::has($names, 'response.acknowledge', true);
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'cortex-agent-response-v1', 'passed' => $passed, 'checks' => $checks,
    'effects' => $tool->effects, 'deliveries' => $deliveries, 'trace' => $agent->taskTrace()->toArray(),
    'limits' => ['Synthetic tools only; no provider calls or real operational effects.',
        'Host scope checks and receiver deduplication are fixture code, not a new authorization service.',
        'Receiver state survives the caller restart in memory; no durable or concurrent guarantee.',
        'An uncertain effect requires trusted receiver reconciliation; this fixture never automatically retries.']],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
