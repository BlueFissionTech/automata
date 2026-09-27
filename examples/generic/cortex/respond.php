<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Response\ResponseComposer;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;

['candidate' => $candidate] = require __DIR__ . '/models.php';
$intent = $candidate->strategy()->predict('confirm booking');
$compose = static fn (string $id, array $fragments, float $threshold): ResponseComposer => new ResponseComposer(
    new ResponseEnvelope($id, Arr::make($fragments)->map(
        static fn (array $record): ResponseFragment => new ResponseFragment($record))->values()->val(),
        ['trace_id' => 'concierge-response-trace', 'experience_id' => 'concierge-request']),
    new ResponsePolicy(['threshold' => $threshold, 'minimum_threshold' => $threshold]));
$sink = new class {
    public int $effects = 0;
    public array $deliveries = [];
    private array $receipts = [];
    public function deliver(array $release): array
    {
        if (isset($this->receipts[$release['id']])) { return $this->receipts[$release['id']]; }
        $receipts = [];
        foreach ($release['fragments'] as $fragment) {
            // A bounded in-memory fixture, not a live booking or dispatch service.
            if ($fragment['channel'] === 'tool') { ++$this->effects; }
            $receipts[$fragment['id']] = ['successful' => true, 'evidence' => ['receiver' => 'fixture-sink']];
        }
        $this->deliveries[] = $release;
        return $this->receipts[$release['id']] = $receipts;
    }
};
$composer = $compose('concierge-response-1', [
    ['id' => 'authorization', 'channel' => 'state', 'weight' => 0, 'blocking' => true],
    ['id' => 'acknowledgement', 'channel' => 'text', 'weight' => 3],
    ['id' => 'dispatch', 'channel' => 'tool', 'weight' => 4],
    ['id' => 'confirmation', 'channel' => 'text', 'weight' => 3, 'dependencies' => ['dispatch']],
], 0.3)->resolve('acknowledgement', ['text' => 'I can help with ' . $intent . '.'])
    ->resolve('confirmation', ['text' => 'The simulated service request completed.']);
$blocked = $composer->prepare(0) === null;
$first = $composer->resolve('authorization', ['fixture_policy' => 'admitted'])->prepare(10);
$composer->acknowledge($first['id'], $sink->deliver($first));
$action = $composer->resolve('dispatch', ['intent' => $intent, 'operation' => 'simulate_service_request'])->prepare(20);
$checkpoint = json_encode($composer->snapshot(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
$sink->deliver($action); // The receiver completes the effect, then the caller loses its acknowledgement.
unset($composer);
$restored = ResponseComposer::restore(json_decode($checkpoint, true, 512, JSON_THROW_ON_ERROR));
$replayed = $restored->prepare(21);
$restored->acknowledge($replayed['id'], $sink->deliver($replayed));
$confirmation = $restored->prepare(22);
$restored->acknowledge($confirmation['id'], $sink->deliver($confirmation));

$cancelled = $compose('cancelled-response', [
    ['id' => 'fast', 'channel' => 'text'], ['id' => 'slow', 'channel' => 'audio'],
], 0.5)->resolve('fast', 'A partial answer.');
$inflight = $cancelled->prepare(0);
$cancelled->cancel('caller_cancelled');
$cancelled = ResponseComposer::restore($cancelled->snapshot());
$cancelled->acknowledge($inflight['id'], $sink->deliver($inflight));
$fallback = $compose('fallback-response', [
    ['id' => 'lookup', 'channel' => 'text', 'deadline_ms' => 5,
        'fallback' => ['payload' => 'Lookup unavailable. Please ask the desk.']],
    ['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['lookup']],
], 0.5)->resolve('confirmation', 'Lookup succeeded.');
$fallbackRelease = $fallback->prepare(5);
$fallback->acknowledge($fallbackRelease['id'], $sink->deliver($fallbackRelease));
$fragmentIds = static fn (array $release): array => Arr::make($release['fragments'])
    ->map(static fn (array $fragment): string => $fragment['id'])->values()->val();
$checks = [
    'existing_classifier_drives_intent' => $intent === 'checkin',
    'blocking_requirement_enforced' => $blocked,
    'early_response_is_partial' => $fragmentIds($first) === ['authorization', 'acknowledgement'],
    'action_is_separate_release' => $fragmentIds($action) === ['dispatch'],
    'pending_delivery_id_survives_restart' => $replayed === $action,
    'lost_ack_replay_does_not_repeat_effect' => $sink->effects === 1,
    'confirmation_follows_success_receipt' => $fragmentIds($confirmation) === ['confirmation'],
    'acknowledged_content_not_repeated' => $restored->prepare(23) === null,
    'cancellation_stops_new_releases' => $cancelled->prepare(1) === null,
    'unfinished_fragment_cancelled' => $cancelled->state()['fragments']['slow']['status'] === 'cancelled',
    'deadline_uses_declared_fallback' => $fallbackRelease['fragments'][0]['resolution'] === 'fallback',
    'fallback_does_not_claim_original_success' => $fallback->prepare(6) === null,
];
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'cortex-progressive-response-v1', 'passed' => $passed, 'checks' => $checks,
    'deliveries' => $sink->deliveries, 'final_state' => $restored->state(),
    'limits' => ['Synthetic effects only; no real booking, dispatch, provider or authorization service.',
        'The receiver retains deduplication state across the simulated caller restart.',
        'Hosts must persist trusted checkpoints and receiver receipts transactionally for durable delivery.',
        'Direct Agent, worker, TaskTrace and concurrent persistence integration remain subsequent work.']],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
