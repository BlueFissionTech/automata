<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Orchestration\{OrchestratedAgent, OrchestrationConfig, Orchestrator};

// Completion says that work returned, not that its output has been scored.
// These gates exercise real orchestration and keep absence distinct from zero.
$unknown = (new Orchestrator(['workers' => [
    'unmeasured' => static fn () => 'proposal',
    'measured' => static fn () => ['output' => 'evidence', 'confidence' => 0.8],
]]))->run();
$child = new OrchestratedAgent('child', ['workers' => [
    'unmeasured' => static fn () => ['output' => 'proposal', 'confidence' => null],
]]);
$nested = (new Orchestrator(['workers' => ['child' => $child->worker()]]))->run();
$fallbackCalls = 0;
$hierarchical = static function (?float $score) use (&$fallbackCalls) {
    return (new Orchestrator(['pattern' => OrchestrationConfig::HIERARCHICAL,
        'workers' => ['proposal' => ['handler' => static fn () => 'proposal', 'confidence' => $score]],
        'fallback' => static function () use (&$fallbackCalls) {
            ++$fallbackCalls;
            return ['output' => 'replacement', 'confidence' => 0.9];
        },
    ]))->run();
};
$hierarchical(null);
$unknownFallbackCalls = $fallbackCalls;
$hierarchical(0.0);
$invalid = (new Orchestrator(['workers' => [
    'invalid' => static fn () => ['output' => 'proposal', 'confidence' => '0.8'],
]]))->run();
$known = (new Orchestrator(['workers' => [
    'low' => static fn () => ['output' => 'one', 'confidence' => 0.2],
    'high' => static fn () => ['output' => 'two', 'confidence' => 0.8],
]]))->run();
$checks = [
    'completion_without_certainty' => $unknown->status() === 'completed' && $unknown->confidence() === null,
    'child_preserves_unknown' => $child()['confidence'] === null,
    'parent_preserves_unknown' => $nested->confidence() === null,
    'unknown_does_not_trigger_fallback' => $unknownFallbackCalls === 0,
    'measured_zero_triggers_fallback' => $fallbackCalls === 1,
    'malformed_score_fails' => $invalid->status() === 'failed' && $invalid->confidence() === null,
    'measured_mean_is_preserved' => $known->confidence() === 0.5,
];
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'orchestration-confidence-v1', 'passed' => $passed,
    'checks' => $checks], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
exit($passed ? 0 : 1);
