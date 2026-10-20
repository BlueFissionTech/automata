<?php

namespace CortexExample\Scenarios;

use BlueFission\Arr;
use BlueFission\Num;
use InvalidArgumentException;

/**
 * Replay the host's public game transcript without consulting either policy.
 *
 * Normal play: players alternate removing one to three stones; the player
 * removing the last stone wins. A report is accepted only if every transition
 * and the declared winner follow from the transcript. A policy never receives
 * the expected winner or access to this evaluator.
 */
final class GameOracle
{
    /**
     * Independent exhaustive solver for small normal-play positions.
     * It uses reachable states, not the candidate policy's modulo rule.
     */
    public static function winningMoves(int $remaining): array
    {
        if ($remaining < 1 || $remaining > 128) { throw new InvalidArgumentException('Solver bound is 1..128 stones.'); }
        $winning = [false];
        $choices = [];
        for ($stones = 1; $stones <= $remaining; ++$stones) {
            $choices = [];
            for ($take = 1; $take <= 3 && $take <= $stones; ++$take) {
                if (!$winning[(int) Num::minus($stones, $take)]) { $choices[] = $take; }
            }
            $winning[$stones] = $choices !== [];
        }
        return $choices;
    }

    /** Reject illegal moves, invented completion and inconsistent world state. */
    public static function evaluate(array $episode): array
    {
        if (!Num::isInt($episode['initial'] ?? null) || ($episode['initial'] ?? 0) < 1
            || $episode['initial'] > 128 || !Arr::is($episode['moves'] ?? null)
            || !Arr::check($episode['moves'], 'array_is_list')
            || !Arr::has(['agent', 'opponent'], $episode['winner'] ?? null, true)) {
            return ['valid' => false, 'won' => false];
        }
        $remaining = $episode['initial'];
        $expectedActor = 'agent';
        $winner = null;
        $valid = Num::isInt($remaining) && $remaining > 0;
        foreach ($episode['moves'] as $move) {
            $take = $move['take'] ?? null;
            if (!$valid || $winner !== null || !Num::isInt($take)
                || $take < 1 || $take > 3 || $take > $remaining
                || ($move['actor'] ?? null) !== $expectedActor
                || ($move['before'] ?? null) !== $remaining) {
                $valid = false;
                break;
            }
            $remaining = (int) Num::minus($remaining, $take);
            if (($move['after'] ?? null) !== $remaining) {
                $valid = false;
                break;
            }
            if ($remaining === 0) { $winner = $expectedActor; }
            $expectedActor = $expectedActor === 'agent' ? 'opponent' : 'agent';
        }
        $valid = $valid && $winner !== null
            && $episode['winner'] === $winner
            && Arr::size($episode['moves']) <= $episode['initial'];
        return ['valid' => $valid, 'won' => $valid && $winner === 'agent'];
    }
}
