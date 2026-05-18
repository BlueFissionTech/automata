<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\LLM\Agent\Telemetry\CpctHook;
use BlueFission\Automata\LLM\Agent\Telemetry\CpctReport;
use BlueFission\Automata\LLM\Agent\Telemetry\TaskTrace;
use BlueFission\DevElation as Dev;
use BlueFission\Net\HTTP;

Dev::up();

Dev::action(CpctHook::MODEL_USAGE_CAPTURED, function (string $task_id, array $usage, mixed ...$payload): void {
    print "model usage captured for {$task_id}: " . HTTP::jsonEncode($usage) . "\n";
});

$trace = new TaskTrace('support-ticket-42', ['surface' => 'support']);
$trace->recordModelUsage('answer-draft', [
    'prompt_tokens' => 1200,
    'completion_tokens' => 300,
    'cache_hit_tokens' => 800,
    'cache_write_tokens' => 100,
    'uncached_input_tokens' => 300,
], [
    'provider' => 'example',
    'model' => 'default',
    'batchable' => false,
    'estimated_cost' => 0.004,
]);
$trace->recordBatchUsage('nightly-quality-judge', 600, true, [
    'provider' => 'example',
    'model' => 'default',
]);
$trace->recordRoutingCandidate('answer-draft', 'default-mini', 0.0015, true);
$trace->complete('completed');

$report = CpctReport::build([$trace], [
    'default' => [
        'input' => 0.003,
        'output' => 0.006,
        'cache_hit_input' => 0.0003,
        'cache_write_input' => 0.003,
        'batch_multiplier' => 0.5,
    ],
], [
    'target_cost' => 0.01,
]);

print HTTP::jsonEncode($report) . "\n";
