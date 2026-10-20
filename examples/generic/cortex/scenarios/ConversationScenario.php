<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Automata\Response\{ResponseEnvelope, ResponseFragment, ResponsePolicy};
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
use BlueFission\Automata\Support\RecordSnapshot;
use Fiber;

/** Scripted user turns exercise evidence waits, interruption and plan revision. */
final class ConversationScenario
{
    /** The oracle is fixed user intent and host context, not generated prose. */
    public function run(): array
    {
        ['training' => $training] = require __DIR__ . '/../fixtures.php';
        $classifier = new NaiveBayesTextClassification();
        $classifier->getPipeline()->train(
            Arr::make($training)->map(static fn ($row) => $row[0])->val(),
            Arr::make($training)->map(static fn ($row) => $row[1])->val()
        );
        $workers = [
            'intent' => static fn ($input) => $classifier->predict($input['root']['message']),
            // Resume data is supplied by this example's host after the user's
            // clarification. No clock sleep or hidden expected label is used.
            'reference' => static fn () => Fiber::suspend('booking_reference_required'),
            'answer' => static fn ($input) => [
                'intent' => $input['predecessors']['intent']['output'],
                'reference' => $input['predecessors']['reference']['output'],
                'accessible' => ($input['predecessors']['accessibility']['output'] ?? false) === true,
            ],
        ];
        $edges = [['intent', 'answer'], ['reference', 'answer']];
        $old = ScenarioRuntime::workflow('conversation-original', $workers, $edges, ['answer'],
            ['message' => 'confirm booking'], ['intent' => 'learned']);
        $old->execute('intent');
        $waiting = new Fiber(fn () => $old->execute('reference'));
        $prompt = $waiting->start();
        $blocked = $old->ready() === [] && $old->result()->outputs() === [];

        $agent = ScenarioRuntime::agent('conversation');
        $response = $agent->startResponse(new ResponseEnvelope('dialogue', [
            new ResponseFragment(['id' => 'clarification', 'channel' => 'text', 'weight' => 1.0]),
            new ResponseFragment(['id' => 'answer', 'channel' => 'text', 'weight' => 3.0,
                'dependencies' => ['clarification']]),
        ]), new ResponsePolicy(['threshold' => 0.25, 'minimum_threshold' => 0.25]));
        $response->resolve('clarification', 'What is your booking reference?');
        $question = $response->prepare(0);
        $response->acknowledge($question['id'], ['clarification' => ['successful' => true, 'evidence' => ['received' => true]]]);
        $noPrematureAnswer = $response->prepare(1) === null;

        // The user supplies a reference AND changes the accessibility constraint.
        // Stop the old graph before admitting a plan with an additional worker.
        $old->cancel();
        $waiting->resume('OLD-REF');
        $workers['reference'] = static fn ($input) => $input['root']['reference'];
        $workers['accessibility'] = static fn ($input) => $input['root']['accessible'];
        $edges[] = ['accessibility', 'answer'];
        $new = ScenarioRuntime::workflow('conversation-revised', $workers, $edges, ['answer'], [
            'message' => 'confirm booking', 'reference' => 'BOOK-42', 'accessible' => true,
        ], ['intent' => 'learned']);
        $result = ScenarioRuntime::finish($new);
        $answer = $result['outputs']['answer'];
        $response->resolve('answer', $answer);
        $released = $response->prepare(2);
        $expected = ['intent' => 'checkin', 'reference' => 'BOOK-42', 'accessible' => true];

        // Weighted release must wait for enough ready content. This separate
        // response has no dependency gate, isolating weight from ordering.
        $weighted = $agent->startResponse(new ResponseEnvelope('weighted', [
            new ResponseFragment(['id' => 'small', 'channel' => 'text', 'weight' => 1.0]),
            new ResponseFragment(['id' => 'large', 'channel' => 'text', 'weight' => 3.0]),
        ]), new ResponsePolicy(['threshold' => 0.75, 'minimum_threshold' => 0.75]));
        $weighted->resolve('small', 'working');
        $weightBlocked = $weighted->prepare(0) === null;
        $weighted->resolve('large', 'answer ready');
        $weightedRelease = $weighted->prepare(1);
        $checks = [
            'asks_for_missing_reference' => $prompt === 'booking_reference_required',
            'join_waits_for_user_evidence' => $blocked && $noPrematureAnswer,
            'old_plan_cannot_publish_answer' => $old->result()->status() === 'cancelled' && $old->result()->outputs() === [],
            'recomposition_changes_graph' => $old->result()->toArray()['plan_fingerprint'] !== $result['plan_fingerprint'],
            'revised_answer_matches_user_constraints' => self::matches($answer, $expected)
                && self::matches($released['fragments'][0]['payload'], $expected),
            'wrong_route_negative_control_rejected' => !self::matches([...$answer, 'intent' => 'luggage'], $expected),
            'stale_reference_negative_control_rejected' => !self::matches([...$answer, 'reference' => 'OLD-REF'], $expected),
            'weighted_readiness_enforced' => $weightBlocked && Arr::size($weightedRelease['fragments']) === 2,
        ];
        return ['scenario' => 'conversation-v1', 'status' => Arr::has($checks, false, true) ? 'FAIL' : 'PASS',
            'checks' => $checks, 'answer' => $answer, 'workflow' => $result,
            'scope' => 'Synthetic conversation; local classifier, cooperative wait and host-admitted replacement graph.'];
    }

    /** Object key order is irrelevant, but every value and its type must match. */
    public static function matches(array $actual, array $expected): bool
    {
        return RecordSnapshot::canonical($actual) === RecordSnapshot::canonical($expected);
    }
}
