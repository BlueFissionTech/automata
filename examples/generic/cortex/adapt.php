<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/ConciergeRouteAdapter.php';

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\Learning\ClassificationEvaluator;
use BlueFission\Automata\Learning\Experience;
use BlueFission\Automata\Learning\Outcome;
use BlueFission\Automata\Learning\StrategyOutcomeFeedback;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Strategy\Routing\StrategyDefinition;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyRouter;
use CortexExample\ConciergeRouteAdapter;

['prior' => $prior, 'candidate' => $candidate, 'holdout' => $holdout] = require __DIR__ . '/models.php';
$evaluation = (new ClassificationEvaluator(minimumSamples: 6))->compare($prior, $candidate, $holdout);
$learner = new Intelligence();
$baselineAdapter = new ConciergeRouteAdapter($prior, StrategyDefinition::MODE_DETERMINISTIC);
$candidateAdapter = new ConciergeRouteAdapter($candidate, StrategyDefinition::MODE_LEARNED);
$router = new StrategyRouter([$baselineAdapter, $candidateAdapter], $learner);
$request = static fn (array $overrides = []): StrategyRouteRequest => new StrategyRouteRequest([
    'id' => 'concierge-request', 'subject_id' => 'concierge-demo',
    'capability_id' => 'concierge.intent', 'capability_version' => '1',
    'candidates' => [$prior->identity(), $candidate->identity()],
    'input' => 'confirm booking', 'context_key' => 'concierge', 'trace_id' => 'concierge-trace',
    'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
    'allowed_modes' => [StrategyDefinition::MODE_DETERMINISTIC, StrategyDefinition::MODE_LEARNED],
    // The host explicitly permits adaptive preference across these two modes.
    'deterministic_preferred' => false, ...$overrides,
]);
$authorization = static fn (bool $allowed): AutonomyDecision => new AutonomyDecision([
    'allowed' => $allowed,
    'code' => $allowed ? AutonomyDecision::CODE_ALLOWED : AutonomyDecision::CODE_CAPABILITY_NOT_GRANTED,
    'subject_id' => 'concierge-demo', 'capability_id' => 'concierge.intent', 'capability_version' => '1',
]);
$executions = static fn (): int => $baselineAdapter->executions + $candidateAdapter->executions;
$before = $router->route($request(), $authorization(true));
$bridge = new StrategyOutcomeFeedback($learner);
$episodes = [];
if ($evaluation['recommended']) {
    // Explicit fixture admission: independently labelled benchmark outcomes supply truth.
    foreach (['incumbent', 'candidate'] as $role) {
        $model = $evaluation[$role];
        foreach ($model['predictions'] as $row) {
            $experienceId = $row['experience_id'];
            $outcomeId = $model['version'] . '-' . $row['outcome_id'];
            $episode = Experience::fromStatements($experienceId, [], new Context(['input' => $row['sample']]), [
                'timestamp' => '2026-09-19T12:00:00Z', 'trace_id' => 'evaluation-' . $experienceId,
                'provenance' => ['dataset' => 'synthetic-concierge-v1', 'split' => 'holdout'],
            ])->withOutcome(new Outcome($outcomeId, $experienceId, 'fixture-annotation', $row['correct'],
                ['expected' => $row['label'], 'predicted' => $row['predicted'],
                    'feedback' => ['prediction_accuracy' => $row['correct'] ? 1.0 : 0.0]],
                ['strategy_id' => $model['id'], 'strategy_version' => $model['version'], 'context_key' => 'concierge']));
            $bridge->apply($episode, $outcomeId);
            $episodes[] = [$episode, $outcomeId];
        }
    }
}
$after = $router->route($request(), $authorization(true));
$replayIgnored = 0;
foreach ($episodes as [$episode, $outcomeId]) { $replayIgnored += (int) !$bridge->apply($episode, $outcomeId); }
$deterministic = $router->route($request(['deterministic_preferred' => true]), $authorization(true));
$candidateAdapter->eligible = false;
$ineligible = $router->route($request(), $authorization(true));
$candidateAdapter->eligible = true;
$beforeRestrictions = $executions();
$denied = $router->route($request(), $authorization(false));
$deniedUnexecuted = $executions() === $beforeRestrictions;
$wrongVersion = $router->route($request(['candidates' => [['id' => 'concierge.intent', 'version' => 'unregistered']]]), $authorization(true));
$versionUnexecuted = $executions() === $beforeRestrictions;
$budget = $router->route($request(['limits' => ['max_invocations' => 0]]), $authorization(true));
$checks = [
    'candidate_has_measured_improvement' => $evaluation['recommended'],
    'prior_selected_before_feedback' => $before->selected_strategy['version'] === 'constant-1',
    'candidate_selected_after_feedback' => $after->selected_strategy['version'] === 'bayes-1',
    'new_request_correctly_classified' => $after->output === 'checkin',
    'feedback_replay_ignored' => $replayIgnored === 12 && Arr::count($bridge->receipts()) === 12
        && $learner->strategyPerformance('concierge.intent', 'bayes-1', 'concierge')['feedback_samples'] === 6,
    'deterministic_preference_preserved' => $deterministic->selected_strategy['version'] === 'constant-1',
    'eligibility_preserved' => $ineligible->selected_strategy['version'] === 'constant-1',
    'denied_action_did_not_execute' => !$denied->completed() && $deniedUnexecuted,
    'exact_version_required' => !$wrongVersion->completed() && $versionUnexecuted,
    'invocation_budget_preserved' => !$budget->completed() && $executions() === $beforeRestrictions,
];
$passed = !Arr::has($checks, false, true);
echo json_encode([
    'experiment' => 'cortex-attributed-feedback-v1', 'fixture_kind' => 'synthetic',
    'passed' => $passed, 'checks' => $checks,
    'before' => ['selected' => $before->selected_strategy, 'output' => $before->output],
    'after' => ['selected' => $after->selected_strategy, 'output' => $after->output],
    'restriction_results' => ['denied' => $denied->code, 'wrong_version' => $wrongVersion->code, 'budget' => $budget->code],
    'request_policy' => $request()->toArray(),
    'selection_advice' => $after->selection_advice,
    'feedback_receipts' => $bridge->receipts(),
    'limits' => ['Provider-free synthetic evidence; no live authorization or deployment is performed.',
        'The host admits both models and opts into adaptive cross-mode selection before feedback.',
        'Replay protection and learner state are process-local; durable recovery is not demonstrated.',
        'Only invocation budgets are probed here. Model cost and energy feedback are not measured.'],
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
