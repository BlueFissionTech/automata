<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\{OrchestratedAgent, Orchestrator};

// These in-memory callbacks expose execution order independently of status.
// No host effect is authorized merely because an orchestration completed.
$checks = [];
$receipts = [];
foreach (['failed', 'uncertain'] as $status) {
    $calls = [];
    $result = (new Orchestrator(['workers' => [
        'prerequisite' => static function () use (&$calls, $status): array {
            $calls[] = 'prerequisite';
            return ['status' => $status, 'output' => ['receipt' => 'observed']];
        },
        'dependent' => static function () use (&$calls): string { $calls[] = 'dependent'; return 'unexpected'; },
    ]]))->run()->toArray();
    $checks[$status . '_stops_dependents'] = $calls === ['prerequisite'];
    $checks[$status . '_status_preserved'] = $result['status'] === $status;
    $checks[$status . '_receipt_and_skip_retained'] = $result['worker_results'][0]['output']['receipt'] === 'observed'
        && ($result['metadata']['skipped_workers'] ?? null) === ['dependent'];
    $receipts[$status] = $result;
}
$textFalse = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 1,
    'producer' => static fn () => ['output' => 'draft'],
    'verifier' => static fn () => ['output' => ['passed' => 'false']],
]))->run();
$checks['text_false_cannot_certify'] = $textFalse->status() === 'failed';
$verifyCalls = 0;
$uncertain = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 3,
    'producer' => static fn () => ['status' => 'uncertain', 'output' => 'draft'],
    'verifier' => static function () use (&$verifyCalls): array { ++$verifyCalls; return ['output' => ['passed' => true]]; },
]))->run()->toArray();
$checks['uncertain_producer_is_not_retried'] = $verifyCalls === 0
    && $uncertain['status'] === 'uncertain' && $uncertain['iterations'] === 1;
$unverified = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 3,
    'producer' => static fn () => ['output' => 'draft'],
    'verifier' => static fn () => ['status' => 'uncertain', 'output' => ['passed' => true]],
]))->run()->toArray();
$checks['uncertain_verifier_cannot_certify'] = $unverified['status'] === 'uncertain'
    && $unverified['iterations'] === 1 && $unverified['metadata']['passed'] === false;
$revised = (new Orchestrator(['pattern' => 'reflexive', 'max_iterations' => 2,
    'producer' => static fn ($context) => ['output' => $context['feedback'] ?? 'draft'],
    'verifier' => static fn ($context) => ['output' => [
        'passed' => $context['output'] === 'revised', 'feedback' => 'revised',
    ]],
]))->run()->toArray();
$checks['boolean_verification_accepts_revision'] = $revised['status'] === 'completed'
    && $revised['iterations'] === 2 && $revised['output'] === 'revised';
$child = new OrchestratedAgent('child', ['workers' => [
    'worker' => static fn () => ['status' => 'uncertain', 'output' => 'receipt'],
]]);
$checks['nested_status_preserved'] = $child()['status'] === 'uncertain';
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'orchestration-prerequisites-v1', 'passed' => $passed,
    'checks' => $checks, 'receipts' => $receipts], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
