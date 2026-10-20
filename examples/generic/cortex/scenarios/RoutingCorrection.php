<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Automata\{Context, Intelligence};
use BlueFission\Automata\Learning\{Experience, Outcome, StrategyOutcomeFeedback};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\Strategy\Routing\{StrategyRouteRequest, StrategyRouter};

/**
 * Admit observed provider errors, then qualify the resulting route preference.
 *
 * Recorded answers are evidence, not new live calls. The local candidate is
 * measured on the same training positions; a separate exhaustive solver scores
 * unseen positions. Neither expected labels nor solver results enter routing.
 */
final class RoutingCorrection
{
    /** The supplied observations must come from the declared five/six-stone cases. */
    public function run(array $observations): array
    {
        $advisor = new Intelligence();
        $feedback = new StrategyOutcomeFeedback($advisor);
        $replayCalls = 0;
        $replay = new ScenarioAdapter('provider.game', static function ($input) use ($observations, &$replayCalls) {
            ++$replayCalls;
            return $observations[$input['remaining']] ?? null;
        }, mode: 'generative');
        $verified = new ScenarioAdapter('verified.game', static fn ($input) => [
            'action' => 'move', 'completed' => false, 'take' => (int) Num::max(1, $input['remaining'] % 4),
        ]);
        $router = new StrategyRouter([$replay, $verified], $advisor);
        $request = static fn (int $pile): StrategyRouteRequest => new StrategyRouteRequest([
            'id' => 'game-correction-' . $pile, 'subject_id' => 'correction-host',
            'capability_id' => 'scenario.propose', 'capability_version' => '1',
            'context_key' => 'game-correction', 'input' => ['remaining' => $pile],
            'candidates' => [['id' => 'provider.game', 'version' => '1'], ['id' => 'verified.game', 'version' => '1']],
            'allowed_modes' => ['generative', 'deterministic'], 'deterministic_preferred' => false,
            'selection_policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
        ]);
        $authorization = new AutonomyDecision(['allowed' => true, 'subject_id' => 'correction-host',
            'capability_id' => 'scenario.propose', 'capability_version' => '1']);
        $before = $router->route($request(5), $authorization);
        foreach ([5, 6] as $pile) {
            foreach (['provider.game', 'verified.game'] as $identity) {
                $proposal = $identity === 'provider.game' ? ($observations[$pile] ?? null)
                    : $verified->execute($request($pile))->output;
                $correct = self::winningProposal($proposal, $pile);
                $id = $identity . '-' . $pile;
                $experience = Experience::fromStatements($id, [], new Context(['remaining' => $pile]))
                    ->withOutcome(new Outcome($id . '-outcome', $id, 'exhaustive-game-oracle', $correct,
                        ['feedback' => ['score' => $correct ? 1.0 : 0.0]],
                        ['strategy_id' => $identity, 'strategy_version' => '1', 'context_key' => 'game-correction']));
                $feedback->apply($experience, $id . '-outcome');
            }
        }
        $callsBeforeHoldout = $replayCalls;
        $holdout = [];
        foreach ([7, 9, 10, 11] as $pile) {
            $route = $router->route($request($pile), $authorization);
            $holdout[] = ['remaining' => $pile, 'selected' => $route->selected_strategy,
                'proposal' => $route->output,
                'correct' => $route->completed() && self::winningProposal($route->output, $pile)];
        }
        $checks = [
            'initial_route_uses_provider_evidence' => $before->selected_strategy['id'] === 'provider.game',
            'heldout_moves_are_winning' => Arr::make($holdout)->filter(static fn ($row) => !$row['correct'])->size() === 0,
            'feedback_changes_future_route' => Arr::make($holdout)
                ->filter(static fn ($row) => $row['selected']['id'] !== 'verified.game')->size() === 0,
            'no_additional_provider_requests' => $callsBeforeHoldout === $replayCalls,
            'only_training_positions_admitted' => Arr::size($feedback->receipts()) === 4,
        ];
        return ['status' => Arr::has($checks, false, true) ? 'FAIL' : 'PASS',
            'checks' => $checks, 'before' => $before->output, 'holdout' => $holdout,
            'scope' => 'Observed provider proposals inform selection; held-out correction uses a separately verified fixed policy.'];
    }

    /** Reject malformed proposals before evaluating strategic value. */
    public static function winningProposal(mixed $proposal, int $pile): bool
    {
        return Arr::is($proposal) && ($proposal['action'] ?? null) === 'move'
            && ($proposal['completed'] ?? null) === false && Num::isInt($proposal['take'] ?? null)
            && Arr::make($proposal)->keys()->diff(['action', 'completed', 'take'])->size() === 0
            && Arr::has(GameOracle::winningMoves($pile), $proposal['take'], true);
    }
}
