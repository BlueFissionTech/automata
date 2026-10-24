<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/CompoundCortex.php';

use BlueFission\Arr;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Examples\Cortex\CompoundCortex;

// Pixels are synthetic binary sensor frames, not images of people. Vocabulary,
// thresholds and graph shapes belong to this fixture, not the library contracts.
$frame = [0, 1, 0, 1, 1, 1, 0, 1, 0];
$input = static fn (string $text, array $pixels = []): array => ['segments' => [
    ['type' => 'text', 'payload' => $text, 'meta' => ['mime' => 'text/plain']],
    ...($pixels === [] ? [] : [['type' => 'video', 'payload' => $pixels, 'meta' => ['mime' => 'video/example-binary']]]),
]];
$agent = new CompoundCortex();
$baseline = $agent->run('baseline', $input('help with this signal', $frame));
// Independent fixture review requires both textual intent and the visible marker
// to survive the response. Execution completion alone never supplies this reward.
$grade = static fn (array $run): bool => $run['output']['intent'] === 'assist' && $run['output']['media_confirmed'] === true;
foreach (['help inspect signal', 'help explain marker', 'help check frame'] as $i => $text) {
    $session = $agent->run('training-' . $i, $input($text, $frame));
    $agent->feedback($session, $grade($session) ? FeedbackSignal::positive(1) : FeedbackSignal::negative(1));
}
$heldout = $agent->run('heldout', $input('help identify this bright pattern', [1, 1, 0, 0, 1, 1, 0, 1, 0]));
$textOnly = $agent->run('text-only', $input('help explain this request'));
$unknownInput = $input('help identify a marker', $frame);
$unknownInput['segments'][] = ['type' => 'telemetry', 'payload' => '0', 'meta' => ['mime' => 'application/example']];
$unknown = $agent->run('unseen-family', $unknownInput);
$blank = $agent->run('blank-frame', $input('help inspect a blank frame', [0, 0, 0, 0, 0, 0, 0, 0, 0]));
$neutral = $agent->run('neutral-review', $input('help inspect this beacon', $frame));
$agent->feedback($neutral, new FeedbackSignal(0));
$noisy = $agent->run('contradictory-review', $input('help explain this beacon', $frame));
$agent->feedback($noisy, FeedbackSignal::negative(1));
$afterNoise = $agent->run('after-noise', $input('help read the marker', $frame));
$denied = $agent->run('denied', $input('help show signal', $frame), allowResponse: false);
$restored = (new CompoundCortex())->restore($agent->checkpoint());
$recovered = $restored->run('recovered', $input('help inspect the beacon', $frame));
$passes = $heldout['passes'];
// Recount actual attempt receipts, rather than deriving authorization from a
// successful output or the learned route. Denied responses are checked separately.
$grants = [];
foreach ($passes as $pass) {
    foreach ($pass['result']['nodes'] as $node) {
        foreach ($node['attempts'] as $attempt) { $grants[] = $attempt['authorization']; }
    }
}
$denial = $denied['passes'][2]['result']['nodes']['answer']['attempts'][0]['authorization'];
$checks = [
    'approved_attempts_report_allowed_codes' => Arr::size($grants) === 8 && Arr::make($grants)->filter(
        static fn (array $grant): bool => $grant['allowed'] !== true || $grant['code'] !== AutonomyDecision::CODE_ALLOWED
    )->size() === 0,
    'denied_response_reports_denial_code' => $denial['allowed'] === false && $denial['code'] === AutonomyDecision::CODE_CAPABILITY_NOT_GRANTED,
    'real_input_and_sense_observe_each_segment' => $heldout['sensory_completions'] === 2,
    'engine_retains_compound_segments' => $heldout['analysis']['gestalt']['segment_types'] === ['text' => 1, 'video' => 1],
    'initial_graph_joins_all_sensor_observations' => $passes[0]['result']['outputs']['categorize'] === ['urgent' => true, 'visible' => true],
    'new_observations_change_signature' => $passes[0]['signature']['key'] !== $passes[1]['signature']['key'],
    'response_has_another_semantic_signature' => $passes[1]['signature']['key'] !== $passes[2]['signature']['key'],
    'additional_branches_are_appended' => Arr::size($passes[1]['plan']['nodes']) === 4,
    'join_waits_for_both_appended_workers' => $passes[1]['schedule'][1]['nodes'] === ['text.sentiment', 'media.marker']
        && $passes[1]['schedule'][1]['ready_while_waiting'] === [] && $passes[1]['schedule'][1]['ready_after_first'] === [],
    'joined_context_reaches_response' => isset($passes[1]['result']['outputs']['compose']['details']['text.sentiment'], $passes[1]['result']['outputs']['compose']['details']['media.marker']),
    'initial_route_exposes_the_reviewed_defect' => !$grade($baseline) && $baseline['selected']['id'] === 'response.quick',
    'new_session_uses_learned_workflow_node' => $grade($heldout) && $heldout['selected']['id'] === 'response.careful',
    'equivalent_features_share_learning' => $heldout['context_key'] === $baseline['context_key'],
    'text_only_keeps_its_anchor' => $textOnly['selected']['id'] === 'response.quick',
    'text_only_recomposes_a_smaller_graph' => Arr::size($textOnly['passes'][1]['plan']['nodes']) === 3,
    'unseen_modality_family_keeps_its_anchor' => $unknown['selected']['id'] === 'response.quick' && $unknown['context_key'] !== $heldout['context_key'],
    'blank_sensor_does_not_inherit_visible_marker' => $blank['output']['media_confirmed'] === false && !isset($blank['passes'][1]['result']['outputs']['compose']['details']['media.marker']),
    'different_topologies_have_different_versions' => $blank['passes'][1]['plan']['version'] !== $heldout['passes'][1]['plan']['version'],
    'one_contradictory_review_does_not_overturn_learned_route' => $grade($afterNoise) && $afterNoise['selected']['id'] === 'response.careful',
    'learned_preference_does_not_grant_authority' => $denied['selected'] === null && $denied['response_calls'] === 0,
    'checkpoint_preserves_composed_route_preference' => $grade($recovered),
    'raw_inputs_are_not_the_route_key' => $baseline['analysis']['segments'][0]['payload'] !== $heldout['analysis']['segments'][0]['payload'],
];
$passed = !Arr::has($checks, false, true);
echo json_encode(['passed' => $passed, 'checks' => $checks, 'heldout' => $heldout,
    'scope' => 'Synthetic binary sensor data and naive fixture features. Demonstrates real Input/Sense/Engine processing, per-pass graph recomposition and learned workflow-node routing. Does not qualify general vision, model-node retraining, statistical generalization or durable workers.'],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION) . PHP_EOL;
exit($passed ? 0 : 1);
