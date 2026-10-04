<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/HostReceiptFixture.php';

use BlueFission\Automata\Examples\Cortex\HostReceiptFixture;
use BlueFission\Automata\Response\ResponseComposer;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;

$scope = ['tenant_id' => 'fixture-tenant', 'principal_id' => 'fixture-principal',
    'subject_id' => 'fixture-subject',
    'attempt_id' => 'fixture-attempt-1', 'capability' => 'fixture-action',
    'capability_version' => 'v1', 'correlation_id' => 'fixture-trace',
    'causation_id' => 'fixture-request', 'source_revision' => 'fixture-source-v1'];
$journal = new HostReceiptFixture();
$composer = new ResponseComposer(new ResponseEnvelope('fixture-response', [
    new ResponseFragment(['id' => 'action', 'channel' => 'tool']),
    new ResponseFragment(['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['action']]),
], ['trace_id' => $scope['correlation_id']]), new ResponsePolicy());
$composer->resolve('action', ['operation' => 'fixture-only'])
    ->resolve('confirmation', 'The fixture action completed.');
$release = $composer->prepare(1);
$revision = $journal->checkpoint($scope, 0, $composer->snapshot());

// The receiver commits one synthetic effect and its terminal result; the caller loses the acknowledgement.
$effectCount = 1;
$receipt = ['successful' => true, 'evidence' => ['outcome' => 'succeeded', 'receiver' => 'fixture']];
$firstResult = $journal->recordResult($scope, $release['id'], 'action', $receipt);
unset($composer);

$saved = $journal->load($scope);
$restored = ResponseComposer::restore($saved['snapshot']);
$replayed = $restored->prepare(2);
$known = $journal->result($scope, $replayed['id'], 'action');
if ($known !== null) {
    $restored->acknowledge($replayed['id'], ['action' => $known]);
}
$revision = $journal->checkpoint($scope, $saved['revision'], $restored->snapshot());
$next = $restored->prepare(3);
$revision = $journal->checkpoint($scope, $revision, $restored->snapshot());

$staleRejected = false;
try { $journal->checkpoint($scope, $saved['revision'], $restored->snapshot()); }
catch (LogicException) { $staleRejected = true; }
$duplicateSuppressed = !$journal->recordResult($scope, $release['id'], 'action', $receipt);
$unknownNotSuccessful = false;
try {
    $journal->recordResult($scope, $next['id'], 'confirmation',
        ['successful' => true, 'evidence' => ['outcome' => 'unknown']]);
} catch (InvalidArgumentException) { $unknownNotSuccessful = true; }

$checks = [
    'checkpoint_before_effect' => $saved['revision'] === 1,
    'restart_preserves_release' => $replayed === $release,
    'receiver_result_reconciles_lost_ack' => $known === $receipt && $firstResult,
    'effect_not_repeated' => $effectCount === 1 && $duplicateSuppressed,
    'dependent_confirmation_after_receipt' => $next['fragments'][0]['id'] === 'confirmation',
    'stale_writer_rejected' => $staleRejected && $revision === 3,
    'unknown_is_not_terminal_success' => $unknownNotSuccessful
        && $journal->result($scope, $next['id'], 'confirmation') === null,
];
$passed = !in_array(false, $checks, true);
echo json_encode(['experiment' => 'cortex-host-receipt-fixture-v1', 'passed' => $passed,
    'checks' => $checks, 'limits' => [
        'The journal and receiver are process-local test doubles, not durable or authenticated services.',
        'A real host must atomically persist receiver effects and terminal results, enforce principal and tenant scope, and reconcile unknown outcomes without blind re-execution.',
    ]], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
