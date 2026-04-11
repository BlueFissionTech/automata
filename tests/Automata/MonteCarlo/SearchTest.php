<?php

namespace BlueFission\Tests\Automata\MonteCarlo;

use BlueFission\Automata\MonteCarlo\RandomSource;
use BlueFission\Automata\MonteCarlo\Search;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    public function testMonteCarloSearchAggregatesVisitsAndRewards(): void
    {
        $search = new Search(9, 12);

        $result = $search->evaluate(['hold', 'reroute', 'airlift'], function (string $action): float {
            $scores = [
                'hold' => 1.0,
                'reroute' => 5.0,
                'airlift' => 3.0,
            ];

            return $scores[$action];
        });

        $stats = $result->toArray();

        $this->assertSame('reroute', $result->getBestAction());
        $this->assertCount(3, $stats);
        $this->assertSame(3, $stats[0]['visits']);
        $this->assertSame(5.0, $stats[0]['mean_reward']);
        $this->assertSame(15.0, $stats[0]['total_reward']);
    }

    public function testMonteCarloSearchProvidesDeterministicSeededRandomness(): void
    {
        $rollout = function (string $action, RandomSource $random): float {
            $base = [
                'stabilize' => 10.0,
                'deploy' => 20.0,
            ];

            return $base[$action] + $random->nextInt(0, 2);
        };

        $first = (new Search(8, 44))->evaluate(['stabilize', 'deploy'], $rollout)->toArray();
        $second = (new Search(8, 44))->evaluate(['stabilize', 'deploy'], $rollout)->toArray();

        $this->assertSame($first, $second);
    }

    public function testMonteCarloSearchRejectsEmptyActionList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Search(4))->evaluate([], function (): float {
            return 0.0;
        });
    }
}
