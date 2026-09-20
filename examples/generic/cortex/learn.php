<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Statement;
use BlueFission\Automata\Learning\{CallbackTrainingAdapter, ClassificationEvaluator, Experience, ExperienceRecomposer, InMemoryExperienceStore, LearningCoordinator, ModelCandidate, ModelLifecycle, Outcome, TrainingBatch, TrainingExample, TrainingPolicy, TrainingTrigger};
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\Automata\Response\{ResponseEnvelope, ResponseFragment};
use BlueFission\Automata\Strategy\{IStrategy, NaiveBayesTextClassification};

['training' => $rows, 'holdout' => $holdoutRows] = require __DIR__ . '/fixtures.php';
$fixed = new class implements IStrategy {
    public function predict($input) { return 'directions'; }
    public function train(array $samples, array $labels, float $testSize = 0.2) { throw new LogicException('Never train the incumbent.'); }
    public function accuracy(): float { return 0.0; }
    public function saveModel(string $path): bool { return false; }
    public function loadModel(string $path): bool { return false; }
};
$prior = new ModelCandidate('concierge.intent', 'constant-1', $fixed, new TrainingBatch('concierge.intent', '1', []));
$coordinator = new LearningCoordinator($prior, new TrainingPolicy(minimumExamples: 6, minimumNewExamples: 12));
$lifecycle = new ModelLifecycle($prior, new ClassificationEvaluator(minimumSamples: 6));
$store = new InMemoryExperienceStore();
$adapter = new CallbackTrainingAdapter('concierge.intent', '1', static fn (Experience $experience): iterable =>
    Arr::make($experience->outcomes())
        ->filter(static fn (Outcome $outcome): bool => $outcome->successful() && Str::is($outcome->observations()['intent'] ?? null))
        ->map(static fn (Outcome $outcome): TrainingExample => new TrainingExample($experience->id(), $outcome->id(),
            $experience->toArray()['context']['data']['utterance'], $outcome->observations()['intent']))
        ->values()->val());
$factoryCalls = $trainCalls = 0;
$factory = static function () use (&$factoryCalls): IStrategy { ++$factoryCalls; return new NaiveBayesTextClassification(); };
$train = static function (IStrategy $strategy, TrainingBatch $batch) use (&$trainCalls): void {
    ++$trainCalls;
    // Explicit trainer bridge: use every projected row; evaluation owns a separate holdout.
    $strategy->getPipeline()->train($batch->samples(), $batch->labels());
};
$approve = static fn () => GovernanceDecision::approved('Synthetic local fixture only.');
$checks = [];
foreach ($rows as $i => [$input, $label]) {
    $statement = new Statement();
    $statement->assign(['subject' => 'guest', 'behavior' => 'requests', 'object' => $input]);
    $id = 'concierge-' . $i;
    $store->save(Experience::fromStatements($id, [$statement], new Context(['utterance' => $input]),
        ['provenance' => ['fixture' => 'cortex-concierge-v1', 'split' => 'training']])
        ->withOutcome(new Outcome('label-' . $i, $id, 'fixture-annotation', true, ['intent' => $label])));
    if ($i === 5) {
        $partial = (new ExperienceRecomposer())->compose($store->experiences(), $adapter);
        $deferred = $coordinator->train('insufficient', 'bayes-1', $partial, new TrainingTrigger(), $factory, $train, $approve);
        $checks['accumulation_defers_training'] = $deferred->status() === 'deferred' && $factoryCalls === 0 && $trainCalls === 0;
    }
}
$store->save(Experience::fromStatements('pending', [], new Context(['utterance' => 'unreviewed'])));
$batch = (new ExperienceRecomposer())->compose($store->experiences(), $adapter);
$checks['only_observed_labels_enter_training'] = Arr::count($batch->samples()) === 12 && !Arr::has($batch->samples(), 'unreviewed', true);
$denied = $coordinator->train('denied', 'bayes-1', $batch, new TrainingTrigger(), $factory, $train, static fn () => GovernanceDecision::denied());
$checks['training_denial_never_constructs_or_trains'] = $denied->status() === 'denied' && $factoryCalls === 0 && $trainCalls === 0;
$result = $coordinator->train('approved', 'bayes-1', $batch, new TrainingTrigger(), $factory, $train, $approve);
$checks['isolated_candidate_trained_once'] = $result->status() === 'trained' && $factoryCalls === 1 && $trainCalls === 1
    && $result->candidate()->strategy() !== $prior->strategy();
$checks['training_does_not_activate'] = $lifecycle->active() === $prior;
$checks['retry_reuses_training_result'] = $coordinator->train('approved', 'bayes-1', $batch, new TrainingTrigger(), $factory, $train, $approve) === $result && $trainCalls === 1;
$checks['unchanged_evidence_does_not_retrain'] = $coordinator->train('unchanged', 'bayes-2', $batch, new TrainingTrigger(), $factory, $train, $approve)->status() === 'deferred' && $trainCalls === 1;
$holdout = new TrainingBatch('concierge.intent', '1', Arr::make($holdoutRows)
    ->map(static fn (array $row, int $i): TrainingExample => new TrainingExample('holdout-' . $i, 'held-label-' . $i, $row[0], $row[1]))->values()->val());
$deniedActivation = $lifecycle->promote('activation-denied', 0, $result->candidate(), $holdout, static fn () => GovernanceDecision::denied());
$checks['activation_requires_separate_approval'] = !$deniedActivation['applied'] && $lifecycle->active() === $prior;

$client = new class implements IClient {
    public function generate($input, $config = [], ?callable $callback = null): Reply { throw new LogicException('No provider calls.'); }
    public function complete($input, $config = []): Reply { throw new LogicException('No provider calls.'); }
    public function respond($input, $config = []): Reply { throw new LogicException('No provider calls.'); }
};
$agent = new Agent($client);
$agent->useSession(new AgentSession('learning-loop'));
$agent->startTask('experience-to-response');
$tool = new class extends BaseTool {
    public array $effects = [];
    public function execute($input): string { $this->effects[] = $input; return 'simulated dispatch'; }
};
$agent->registerTool('dispatch', $tool, ['permission' => 'write', 'requires_approval' => true, 'max_retries' => 0]);
$respond = static function (string $id) use ($agent, $lifecycle) {
    $response = $agent->startResponse(new ResponseEnvelope($id, [
        new ResponseFragment(['id' => 'action', 'channel' => 'tool']),
        new ResponseFragment(['id' => 'confirmation', 'channel' => 'text', 'dependencies' => ['action']]),
    ]));
    $response->produce('action', static fn () => ['status' => 'completed', 'output' => [
        'intent' => $lifecycle->active()->strategy()->predict('confirm booking'), 'model' => $lifecycle->active()->identity()]]);
    return $response->resolve('confirmation', 'Dispatch completed.');
};
$before = $respond('before')->prepare(0);
$activation = $lifecycle->promote('activation-approved', 0, $result->candidate(), $holdout, $approve);
$checks['held_out_candidate_earns_activation'] = $activation['applied'] && $activation['evaluation']['candidate']['accuracy'] === 1.0;
$deniedResponse = $respond('denied-effect');
$blocked = $deniedResponse->prepare(0);
$execution = $agent->callTool('dispatch', $blocked['fragments'][0]['payload'], ['approved' => false]);
$deniedResponse->acknowledge($blocked['id'], ['action' => ['successful' => $execution->ok(), 'evidence' => ['status' => $execution->status()]]]);
$checks['model_approval_cannot_authorize_tool'] = !$execution->ok() && $tool->effects === [] && $deniedResponse->prepare(1) === null;
$response = $respond('after');
$after = $response->prepare(0);
$checks['new_experience_changes_future_agent_plan'] = $before['fragments'][0]['payload']['intent'] === 'directions'
    && $after['fragments'][0]['payload']['intent'] === 'checkin';
$checks['confirmation_waits_for_receipt'] = Arr::count($after['fragments']) === 1 && $response->prepare(1) === $after;
$execution = $agent->callTool('dispatch', $after['fragments'][0]['payload'], ['approved' => true]);
$response->acknowledge($after['id'], ['action' => ['successful' => $execution->ok(), 'evidence' => ['status' => $execution->status()]]]);
$confirmation = $response->prepare(2);
$checks['approved_effect_unlocks_confirmation'] = $execution->ok() && Arr::count($tool->effects) === 1 && $confirmation['fragments'][0]['id'] === 'confirmation';
$response->acknowledge($confirmation['id'], ['confirmation' => ['successful' => true, 'evidence' => ['receiver' => 'fixture']]]);
$rollback = $lifecycle->rollback('rollback', 1, $approve);
$checks['rollback_restores_future_plan'] = $rollback['applied'] && $respond('rolled-back')->prepare(0)['fragments'][0]['payload']['intent'] === 'directions';
$checks['historical_training_retry_cannot_reactivate'] = $coordinator->train('approved', 'bayes-1', $batch, new TrainingTrigger(), $factory, $train, $approve) === $result
    && $trainCalls === 1 && $lifecycle->active() === $prior;
$passed = !Arr::has($checks, false, true);
echo json_encode(['experiment' => 'cortex-learning-loop-v1', 'passed' => $passed, 'checks' => $checks,
    'training' => $result->toArray(), 'activation' => $activation, 'before' => $before, 'after' => $after,
    'effects' => $tool->effects, 'trace' => $agent->taskTrace()->toArray(),
    'limits' => ['Synthetic corpus and tools; no provider calls, spend or real effects.',
        'Synchronous training; host callbacks own resource enforcement and model isolation.',
        'Process-local receipts and model references; no durable worker recovery or deployment.']],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
