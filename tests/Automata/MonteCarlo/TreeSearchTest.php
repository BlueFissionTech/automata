<?php

namespace BlueFission\Tests\Automata\MonteCarlo;

use BlueFission\Automata\MonteCarlo\TreeSearch;
use PHPUnit\Framework\TestCase;

class TreeSearchTest extends TestCase
{
    public function testTreeSearchConvergesOnHighestRewardBranch(): void
    {
        $legalActions = function (array $state): array {
            if (($state['depth'] ?? 0) >= 2) {
                return [];
            }

            if (($state['path'] ?? '') === '') {
                return ['slow_safe', 'fast_risky'];
            }

            return ['finish'];
        };

        $transition = function (array $state, string $action): array {
            $path = trim(($state['path'] ?? '') . '/' . $action, '/');

            return [
                'depth' => ($state['depth'] ?? 0) + 1,
                'path' => $path,
            ];
        };

        $isTerminal = function (array $state): bool {
            return ($state['depth'] ?? 0) >= 2;
        };

        $reward = function (array $state): float {
            return match ($state['path'] ?? '') {
                'slow_safe/finish' => 9.0,
                'fast_risky/finish' => 3.0,
                default => 0.0,
            };
        };

        $search = new TreeSearch(80, 1.2, 3, 99);
        $result = $search->search(['depth' => 0, 'path' => ''], $legalActions, $transition, $isTerminal, $reward);

        $this->assertSame('slow_safe', $result->getBestAction());

        $root = $result->getRoot();
        $this->assertGreaterThan(0, $root->getVisits());
        $this->assertCount(2, $root->getChildren());
    }

    public function testTreeSearchBackpropagatesRewardThroughVisitedNodes(): void
    {
        $search = new TreeSearch(12, 0.5, 0, 7);

        $result = $search->search(
            ['terminal' => false],
            function (array $state): array {
                return ($state['terminal'] ?? false) ? [] : ['resolve'];
            },
            function (): array {
                return ['terminal' => true, 'reward' => 4];
            },
            function (array $state): bool {
                return $state['terminal'] ?? false;
            },
            function (array $state): float {
                return (float)($state['reward'] ?? 0);
            }
        );

        $root = $result->getRoot();
        $child = $root->getChildren()[0];

        $this->assertSame(12, $root->getVisits());
        $this->assertSame(12, $child->getVisits());
        $this->assertSame(4.0, $child->getMeanReward());
    }
}
