<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{AnchoredRouteAdvisor, Experience, Outcome};
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;

// Names and values below belong to this fixture. The advisor does not encode a
// modality catalog, a fixed number of routes or a game-specific reward scale.
$definitions = [['id' => 'inspect', 'version' => '1', 'weight' => 0.6]];
$advisor = new AnchoredRouteAdvisor($definitions, rewardScale: 5.0, minimumSamples: 2);
$record = static function (string $id, bool $executed, ?FeedbackSignal $reward) use ($advisor): void {
    $experience = Experience::fromStatements($id, [], new Context())
        ->withOutcome(new Outcome('review', $id, 'synthetic-review', $executed, [], [
            'strategy_id' => 'inspect', 'strategy_version' => '1', 'context_key' => 'compound.signature.v1',
        ]));
    $advisor->learn($experience, 'review', $reward);
};
$score = static fn (string $context): float => $advisor->advise(
    new StrategyRouteRequest(['context_key' => $context]), $definitions
)->score('inspect', '1');
$record('neutral', false, new FeedbackSignal(0));
$record('unrated', true, null);
$neutral = $score('compound.signature.v1');
$record('negative-one', true, FeedbackSignal::negative(5));
$sparse = $score('compound.signature.v1');
$record('negative-two', true, FeedbackSignal::negative(5));
$learned = $score('compound.signature.v1');
$checks = [
    'neutral_and_absent_preserve_anchor' => $neutral === 0.6,
    'sparse_feedback_preserves_anchor' => $sparse === 0.6,
    'successful_execution_can_receive_negative_reward' => $learned < 0.6,
    'unseen_signature_keeps_its_anchor' => $score('new.signature.v1') === 0.6,
    'no_implicit_global_learning' => $score('global') === 0.6,
];
$passed = !Arr::has($checks, false, true);
echo json_encode(['passed' => $passed, 'checks' => $checks, 'receipts' => $advisor->receipts(),
    'scope' => 'Reward semantics only. Compound sensing, pattern composition, restart and local-model training remain separate qualification.'],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
