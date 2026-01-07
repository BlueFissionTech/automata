<?php

namespace BlueFission\Tests\Automata\DecisionTree;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\DecisionTree\DecisionTree;
use BlueFission\Automata\DecisionTree\Node;
use BlueFission\Automata\DecisionTree\DepthFirstTraceMethod;

class DispatchPolicyTest extends TestCase
{
    /**
     * Build a small policy tree for dispatch decisions.
     *
     * Node value shape:
     * - id: string
     * - decision: string
     * - score: numeric (higher is better)
     */
    private function buildPolicyTree(): DecisionTree
    {
        $eval = function (array $value, array $children): float {
            return (float)($value['score'] ?? 0.0);
        };

        $root = new Node([
            'id' => 'root',
            'decision' => 'evaluate_request',
            'score' => 0,
        ], $eval);

        $evacCritical = new Node([
            'id' => 'evac_critical',
            'decision' => 'evacuation',
            'score' => 5,
        ], $eval);

        $supplyCritical = new Node([
            'id' => 'supply_critical',
            'decision' => 'supply',
            'score' => 4,
        ], $eval);

        $deny = new Node([
            'id' => 'deny_low_priority',
            'decision' => 'deny',
            'score' => 1,
        ], $eval);

        $acceptGround = new Node([
            'id' => 'accept_ground',
            'decision' => 'accept_ground_dispatch',
            'score' => 7,
        ], $eval);

        $escalateAir = new Node([
            'id' => 'escalate_air',
            'decision' => 'escalate_to_airlift',
            'score' => 9,
        ], $eval);

        // Build hierarchy:
        $root->addChild($evacCritical);
        $root->addChild($supplyCritical);
        $root->addChild($deny);

        $evacCritical->addChild($acceptGround);
        $evacCritical->addChild($escalateAir);

        $tree = new DecisionTree();
        $tree->setRoot($root);

        return $tree;
    }

    public function testPolicyDeterminismAndDecisionTrace(): void
    {
        $tree = $this->buildPolicyTree();
        $method = new DepthFirstTraceMethod();

        $decision = $tree->decide($method);

        $this->assertIsArray($decision);
        $this->assertSame('escalate_to_airlift', $decision['decision']);

        $traceNodes = $method->getTrace();
        $ids = array_map(static function ($node) {
            /** @var Node $node */
            $val = $node->getValue();
            return $val['id'] ?? null;
        }, $traceNodes);

        $this->assertSame(['root', 'evac_critical', 'escalate_air'], $ids);
    }
}

