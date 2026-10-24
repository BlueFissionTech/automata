<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Automata\{Context, Intelligence};
use BlueFission\Automata\Learning\{Experience, Outcome, StrategyOutcomeFeedback};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Strategy\Routing\{StrategyRouteRequest, StrategyRouter};
use RuntimeException;

/**
 * Paired, deterministic outcome experiment for adaptive strategy selection.
 *
 * Candidate policies are fixed in advance. What learns is the preference between
 * them, using actual game outcomes admitted through StrategyOutcomeFeedback.
 * This does not claim discovery of game rules or learning a new policy.
 */
final class GameScenario
{
    public const TRAINING_PILES = [5, 6, 7, 9, 10, 11, 13, 14, 15];
    public const HOLDOUT_PILES = [17, 18, 19, 21, 22, 23, 25, 26, 27];
    public const SEEDS = [0, 1, 2];
    public const MINIMUM_IMPROVEMENT = 0.20;

    /** Train only on the declared split; evaluate each pair from frozen state. */
    public function run(): array
    {
        $learner = new Intelligence();
        $feedback = new StrategyOutcomeFeedback($learner);
        $training = [];
        $replayed = 0;
        foreach (['take.one', 'take.complement'] as $policy) {
            foreach (self::TRAINING_PILES as $pile) {
                foreach (self::SEEDS as $seed) {
                    $id = 'train-' . $policy . '-' . $pile . '-' . $seed;
                    // Training explores each admitted policy explicitly. Routing
                    // observations do not substitute for independently scored wins.
                    $episode = $this->play($pile, $seed, $id, new Intelligence(), [$policy], false);
                    $assessment = GameOracle::evaluate($episode);
                    if (!$assessment['valid']) { throw new RuntimeException('Invalid training transcript.'); }
                    $experience = Experience::fromStatements($id, [], new Context([
                        'initial' => $pile, 'opponent_seed' => $seed,
                    ]), ['provenance' => ['dataset' => 'take-away-v1', 'split' => 'training']])
                        ->withOutcome(new Outcome($id . '-outcome', $id, 'game-transcript-oracle',
                            $assessment['won'],
                            ['feedback' => ['score' => $assessment['won'] ? 1.0 : 0.0]],
                            ['strategy_id' => $policy, 'strategy_version' => '1', 'context_key' => 'take-away-v1']));
                    $feedback->apply($experience, $id . '-outcome');
                    $replayed += (int) !$feedback->apply($experience, $id . '-outcome');
                    $training[] = $episode;
                }
            }
        }
        $pairs = [];
        foreach (self::HOLDOUT_PILES as $pile) {
            foreach (self::SEEDS as $seed) {
                $id = 'holdout-' . $pile . '-' . $seed;
                // Clone the fitted advisor for each evaluation episode. Neither
                // holdout labels nor another held-out episode can train this one.
                $pairs[] = [
                    'baseline' => $this->play($pile, $seed, $id . '-baseline', new Intelligence(), ['take.one'], false),
                    'adaptive' => $this->play($pile, $seed, $id . '-adaptive', clone $learner,
                        ['take.one', 'take.complement'], true),
                ];
            }
        }
        $baseline = $this->winRate(Arr::make($pairs)->map(static fn ($pair) => $pair['baseline'])->val());
        $adaptive = $this->winRate(Arr::make($pairs)->map(static fn ($pair) => $pair['adaptive'])->val());
        $improvement = Num::minus($adaptive, $baseline);
        $invalidPairs = Arr::make($pairs)->filter(static fn ($pair): bool =>
            !GameOracle::evaluate($pair['baseline'])['valid'] || !GameOracle::evaluate($pair['adaptive'])['valid'])->size();

        // Mutate accepted transcripts, then use exactly the same evaluator.
        // This catches tests that merely trust the scenario's "passed" flag.
        $illegal = $pairs[0]['adaptive'];
        $illegal['moves'][0]['take'] = 4;
        $fabricated = $pairs[0]['adaptive'];
        $fabricated['winner'] = $fabricated['winner'] === 'agent' ? 'opponent' : 'agent';
        $checks = [
            'all_transcripts_valid' => $invalidPairs === 0,
            'heldout_improvement_at_least_twenty_points' => $improvement >= self::MINIMUM_IMPROVEMENT,
            'all_feedback_replays_ignored' => $replayed === Arr::size($training),
            'illegal_move_control_rejected' => !GameOracle::evaluate($illegal)['valid'],
            'invented_winner_control_rejected' => !GameOracle::evaluate($fabricated)['valid'],
            'holdout_not_admitted_as_feedback' => Arr::size($feedback->receipts()) === Arr::size($training),
        ];
        return [
            'scenario' => 'take-away-v1', 'status' => Arr::has($checks, false, true) ? 'FAIL' : 'PASS',
            'checks' => $checks, 'training_episodes' => Arr::size($training),
            'heldout_pairs' => Arr::size($pairs), 'baseline_win_rate' => $baseline,
            'adaptive_win_rate' => $adaptive, 'improvement' => $improvement,
            'training' => $training, 'pairs' => $pairs,
            'scope' => 'Synthetic fixed-opponent benchmark; learned selection among fixed policies, not rule discovery.',
        ];
    }

    /** Execute bounded host turns; only the router chooses agent proposals. */
    private function play(int $initial, int $seed, string $id, Intelligence $advisor, array $candidates, bool $adaptive): array
    {
        $router = new StrategyRouter([
            new ScenarioAdapter('take.one', static fn (): int => 1),
            // DevElation Num has no integer-remainder operation. Preserve exact
            // integer semantics for this game rule, then use Num for bounds.
            new ScenarioAdapter('take.complement', static fn ($input): int =>
                (int) Num::max(1, $input['remaining'] % 4)),
        ], $advisor);
        $remaining = $initial;
        $moves = [];
        $actor = 'agent';
        $winner = null;
        for ($turn = 0; $turn < $initial && $remaining > 0; ++$turn) {
            $selected = null;
            if ($actor === 'agent') {
                $result = $router->route(new StrategyRouteRequest([
                    'id' => $id . '-' . $turn, 'subject_id' => 'game-player',
                    'capability_id' => 'scenario.propose', 'capability_version' => '1',
                    'context_key' => 'take-away-v1',
                    'candidates' => Arr::make($candidates)->map(static fn ($candidate) =>
                        ['id' => $candidate, 'version' => '1'])->val(),
                    'input' => ['remaining' => $remaining],
                    'selection_policy' => $adaptive ? StrategyRouteRequest::SELECTION_ADAPTIVE : StrategyRouteRequest::SELECTION_DECLARED,
                ]), new AutonomyDecision([
                    'allowed' => true, 'subject_id' => 'game-player',
                    'code' => AutonomyDecision::CODE_ALLOWED,
                    'capability_id' => 'scenario.propose', 'capability_version' => '1',
                ]));
                if (!$result->completed()) { throw new RuntimeException('Game route did not complete: ' . $result->code); }
                $take = $result->output;
                $selected = $result->selected_strategy;
            } else {
                // The opponent is fixed by the manifest, independent of policy
                // identity and evaluator labels. Seeds are paired across runs.
                $take = (int) Num::min($remaining, 1 + (($seed + $turn) % 3));
            }
            if (!Num::isInt($take) || $take < 1 || $take > 3 || $take > $remaining) {
                throw new RuntimeException('Policy proposed an illegal move.');
            }
            $after = (int) Num::minus($remaining, $take);
            $moves[] = ['actor' => $actor, 'before' => $remaining, 'take' => $take,
                'after' => $after, 'selected' => $selected];
            $remaining = $after;
            if ($remaining === 0) { $winner = $actor; }
            $actor = $actor === 'agent' ? 'opponent' : 'agent';
        }
        return ['id' => $id, 'initial' => $initial, 'seed' => $seed, 'moves' => $moves, 'winner' => $winner];
    }

    /** Count observed wins only after replay validation; never use policy labels. */
    private function winRate(array $episodes): float
    {
        $wins = Arr::make($episodes)->filter(static fn ($episode): bool => GameOracle::evaluate($episode)['won'])->size();
        return (float) Num::divide($wins, Arr::size($episodes));
    }
}
