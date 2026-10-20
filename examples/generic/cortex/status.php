<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\Orchestrator;

// The host ledger records actual invocations independently of aggregate output.
// These callbacks are simulations: no receipt authorizes an external effect.
$checks = [];
$rows = [];
$expected = ['completed' => 'completed', 'failed' => 'partial',
    'uncertain' => 'uncertain', 'timed_out' => 'partial', 'cancelled' => 'partial',
    'in_progress' => 'partial', 'partial' => 'partial', 'future_status' => 'uncertain'];
foreach (['fan_out', 'hierarchical', 'piano'] as $pattern) {
    foreach ($expected as $status => $aggregate) {
        $calls = [];
        $workers = [];
        foreach (['subject', 'peer'] as $name) {
            $workers[$name] = static function () use (&$calls, $name, $status): array {
                $calls[] = $name;
                return ['status' => $name === 'subject' ? $status : 'completed',
                    'output' => [$name => 'receipt:' . $name], 'confidence' => null];
            };
        }
        $result = (new Orchestrator(['pattern' => $pattern, 'workers' => $workers]))->run()->toArray();
        $subject = Arr::make($result['worker_results'])
            ->filter(static fn (array $worker): bool => $worker['name'] === 'subject')
            ->values()->toArray()[0];
        $key = $pattern . '/' . $status;
        $checks[$key . '/aggregate'] = $result['status'] === $aggregate;
        $checks[$key . '/independent_peers'] = $calls === ['subject', 'peer'];
        $checks[$key . '/evidence'] = $subject['status'] === $status
            && $subject['output']['subject'] === 'receipt:subject';
        $checks[$key . '/unknown_confidence'] = $result['confidence'] === null;
        $rows[$key] = ['aggregate' => $result['status'], 'worker' => $subject, 'calls' => $calls];
    }
}

// Planning is a prerequisite; unlike peers, blocked controllers prevent dispatch.
foreach (['hierarchical', 'piano'] as $pattern) {
    foreach (['failed', 'uncertain', 'pending'] as $status) {
        $calls = 0;
        $result = (new Orchestrator(['pattern' => $pattern,
            'supervisor' => static fn (): array => ['status' => $status,
                'output' => ['workers' => ['child'], 'receipt' => 'controller']],
            'workers' => ['child' => static function () use (&$calls): string {
                ++$calls; return 'unexpected';
            }],
        ]))->run()->toArray();
        $key = $pattern . '/controller/' . $status;
        $checks[$key . '/no_dispatch'] = $calls === 0;
        $checks[$key . '/incomplete'] = $result['status'] !== 'completed';
        $checks[$key . '/receipt'] = $result['worker_results'][0]['output']['receipt'] === 'controller';
    }
}
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'orchestration-status-v1', 'passed' => $passed,
    'checks' => $checks, 'evidence' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
