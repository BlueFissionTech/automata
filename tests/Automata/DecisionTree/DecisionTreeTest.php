<?php

namespace BlueFission\Tests\Automata\DecisionTree;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\DecisionTree\DepthFirstMethod;
use BlueFission\Automata\DecisionTree\BreadthFirstMethod;
use BlueFission\Automata\DecisionTree\LeafOnlyBestMethod;
use BlueFission\Automata\DecisionTree\IMethod;
use BlueFission\Automata\DecisionTree\INode;

class DecisionTreeTest extends TestCase
{
    /**
     * Build a simple logistics decision tree:
     * root: choose route from hub to hospital
     * children: different route options with time and risk.
     */
    private function buildSimpleLogisticsTree(): DecisionTree
    {
        // Evaluation: higher is better; penalize time and risk.
        $eval = function (array $value, array $children): int {
            $base = 100;
            $timePenalty = $value['time_minutes'] ?? 0;
            // Heavier penalty for risk to strongly prefer safer routes.
            $riskPenalty = ($value['risk_level'] ?? 0) * 20;

            return $base - $timePenalty - $riskPenalty;
        };

        // Root represents "no route chosen yet" and should be worse
        // than any concrete routed option.
        $root = new Node(
            [
                'id' => 'decision_root',
                'description' => 'Select route from hub to Hospital A',
                'time_minutes' => 999,
                'risk_level' => 9,
            ],
            $eval
        );

        $routeFastHighRisk = new Node(
            [
                'id' => 'route_fast_high_risk',
                'path' => ['Hub', 'Bridge-1', 'Hospital A'],
                'time_minutes' => 20,
                'risk_level' => 4, // flooded approach
            ],
            $eval
        );

        $routeSlowLowRisk = new Node(
            [
                'id' => 'route_slow_low_risk',
                'path' => ['Hub', 'Highway-Loop', 'Hospital A'],
                'time_minutes' => 35,
                'risk_level' => 1,
            ],
            $eval
        );

        $routeMediumRisk = new Node(
            [
                'id' => 'route_medium_risk',
                'path' => ['Hub', 'Service-Road', 'Hospital A'],
                'time_minutes' => 25,
                'risk_level' => 2,
            ],
            $eval
        );

        $root->addChild($routeFastHighRisk);
        $root->addChild($routeSlowLowRisk);
        $root->addChild($routeMediumRisk);

        $tree = new DecisionTree();
        $tree->setRoot($root);

        return $tree;
    }

    public function testDepthFirstSelectsBestScoringRoute(): void
    {
        $tree = $this->buildSimpleLogisticsTree();
        $method = new DepthFirstMethod();

        $best = $tree->decide($method);

        $this->assertIsArray($best);
        $this->assertArrayHasKey('id', $best);
        $this->assertEquals('route_slow_low_risk', $best['id'], 'Depth-first should choose the lowest risk viable route even if slower.');
    }

    public function testNodeEvaluationCanUseChildrenInformation(): void
    {
        $eval = function (array $value, array $children): int {
            $base = $value['base_score'] ?? 0;
            $childCount = count($children);

            return $base + $childCount;
        };

        $parent = new Node(['id' => 'parent', 'base_score' => 10], $eval);
        $childA = new Node(['id' => 'childA', 'base_score' => 0], $eval);
        $childB = new Node(['id' => 'childB', 'base_score' => 0], $eval);

        $parent->addChild($childA);
        $parent->addChild($childB);

        $this->assertSame(12, $parent->evaluate(), 'Parent evaluation should account for children count.');
    }

    public function testDepthFirstHandlesSingleNodeTree(): void
    {
        $eval = function (array $value, array $children): int {
            return $value['score'] ?? 0;
        };

        $root = new Node(['id' => 'only', 'score' => 42], $eval);

        $tree = new DecisionTree();
        $tree->setRoot($root);

        $method = new DepthFirstMethod();
        $best = $tree->decide($method);

        $this->assertEquals(['id' => 'only', 'score' => 42], $best);
    }

    public function testCustomMethodInterfaceAllowsAlternativeTraversal(): void
    {
        $tree = $this->buildSimpleLogisticsTree();

        // A trivial method that always returns the root's value, regardless of children.
        $method = new class implements IMethod {
            public function traverse(INode $root): array
            {
                return $root->getValue();
            }
        };

        $result = $tree->decide($method);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('decision_root', $result['id']);
    }

    public function testBreadthFirstMatchesDepthFirstOnSimpleTree(): void
    {
        $tree = $this->buildSimpleLogisticsTree();

        $depthFirst = new DepthFirstMethod();
        $breadthFirst = new BreadthFirstMethod();

        $bestDepth = $tree->decide($depthFirst);
        $bestBreadth = $tree->decide($breadthFirst);

        $this->assertEquals($bestDepth['id'], $bestBreadth['id']);
    }

    public function testLeafOnlyBestIgnoresNonLeafScores(): void
    {
        $eval = function (array $value, array $children): int {
            return $value['score'] ?? 0;
        };

        // Root has high score but is not a leaf.
        $root = new Node(['id' => 'root', 'score' => 100], $eval);
        $leafLow = new Node(['id' => 'leaf_low', 'score' => 10], $eval);
        $leafHigh = new Node(['id' => 'leaf_high', 'score' => 50], $eval);

        $root->addChild($leafLow);
        $root->addChild($leafHigh);

        $tree = new DecisionTree();
        $tree->setRoot($root);

        $method = new LeafOnlyBestMethod();
        $best = $tree->decide($method);

        $this->assertEquals('leaf_high', $best['id'], 'Leaf-only method should ignore root score and pick best leaf.');
    }
}
