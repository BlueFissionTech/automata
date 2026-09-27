<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Strategy\Routing\StrategyUsage;

$unknown = new StrategyUsage(['cost' => null, 'invocations' => 1]);
$zero = new StrategyUsage(['cost' => 0.0, 'invocations' => 1]);
$combined = $zero->plus($unknown);

$checks = [
    'unknown_remains_null' => $unknown->toArray()['cost'] === null,
    'zero_remains_measured' => $zero->cost === 0.0,
    'unknown_survives_accumulation' => $combined->cost === null,
    'unknown_survives_invocation_adjustment' => $unknown->withMinimumInvocations()->cost === null,
    'finite_cap_rejects_unknown' => !$unknown->within(['max_cost' => 1.0]),
    'finite_cap_accepts_zero' => $zero->within(['max_cost' => 0.0]),
    'omitted_cost_keeps_compatibility_default' => (new StrategyUsage())->cost === 0.0,
];
$passed = !Arr::has($checks, false, true);

echo json_encode([
    'experiment' => 'strategy-usage-unknown-cost-v1',
    'passed' => $passed,
    'checks' => $checks,
    'unknown' => $unknown->toArray(),
    'zero' => $zero->toArray(),
    'limit' => 'Unknown cost cannot authorize a billable invocation under a finite cap; this fixture makes no reservation or billing claim.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

exit($passed ? 0 : 1);
