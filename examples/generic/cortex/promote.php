<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Learning\ClassificationEvaluator;
use BlueFission\Automata\Learning\ModelLifecycle;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;

['prior' => $prior, 'candidate' => $candidate, 'worse' => $worse, 'holdout' => $holdout]
    = require __DIR__ . '/models.php';
$lifecycle = new ModelLifecycle($prior, new ClassificationEvaluator(minimumSamples: 6, minimumAccuracy: 1.0));
$before = $lifecycle->active()->strategy()->predict('confirm booking');
$denied = $lifecycle->promote('denied', 0, $candidate, $holdout, static fn () => GovernanceDecision::denied());
$deniedRetained = $lifecycle->active() === $prior;
$promoted = $lifecycle->promote('approved', 0, $candidate, $holdout,
    static fn () => GovernanceDecision::approved('Synthetic fixture approval.', ['review_id' => 'demo-1']));
$after = $lifecycle->active()->strategy()->predict('confirm booking');
$regression = $lifecycle->promote('regression', 1, $worse, $holdout,
    static fn () => throw new LogicException('A regression cannot reach authorization.'));
$regressionRetained = $lifecycle->active() === $candidate;
$rolledBack = $lifecycle->rollback('rollback', 1, static fn () => GovernanceDecision::approved('Fixture rollback.'));
$restored = $lifecycle->active()->strategy()->predict('confirm booking');
$replayed = $lifecycle->promote('approved', 0, $candidate, $holdout,
    static fn () => throw new LogicException('Historical replay cannot authorize again.'));
$staleRejected = false;
try { $lifecycle->promote('stale', 0, $candidate, $holdout, static fn () => GovernanceDecision::approved()); }
catch (LogicException) { $staleRejected = true; }
$checks = [
    'denial_retains_incumbent' => !$denied['applied'] && $deniedRetained,
    'measured_candidate_activated' => $promoted['applied'] && $promoted['evaluation']['recommended'],
    'actual_prediction_changed' => $before === 'directions' && $after === 'checkin',
    'regression_retains_candidate' => !$regression['applied'] && $regressionRetained,
    'authorized_rollback_restores_instance' => $rolledBack['applied'] && $lifecycle->active() === $prior,
    'actual_prediction_restored' => $restored === $before,
    'replay_returns_historical_receipt' => $replayed === $promoted && $lifecycle->revision() === 2,
    'stale_revision_rejected_after_rollback' => $staleRejected,
];
$passed = !Arr::has($checks, false, true);
echo json_encode([
    'experiment' => 'cortex-model-lifecycle-v1', 'fixture_kind' => 'synthetic',
    'passed' => $passed, 'checks' => $checks, 'active' => $lifecycle->active()->identity(),
    'revision' => $lifecycle->revision(), 'receipts' => $lifecycle->receipts(),
    'limits' => ['Process-local reference activation only; no deployment, durable recovery or concurrent ownership.',
        'Host must keep model instances immutable and prediction/approval callbacks free of side effects.',
        'Synthetic approval is not production policy, tool authority or evidence of production model quality.'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
