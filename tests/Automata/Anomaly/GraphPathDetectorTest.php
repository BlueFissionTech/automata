<?php

namespace BlueFission\Tests\Automata\Anomaly;

use BlueFission\Automata\Anomaly\Strategies\GraphPathDetector;
use BlueFission\Automata\Context;
use BlueFission\Automata\Path\Graph;
use BlueFission\Automata\Path\Node;
use PHPUnit\Framework\TestCase;

class GraphPathDetectorTest extends TestCase
{
    private function graph(): Graph
    {
        $graph = new Graph();

        $graph->addNode(new Node('A', [
            'B' => ['weight' => 2.0],
            'C' => ['weight' => 10.0],
        ]));
        $graph->addNode(new Node('B', [
            'C' => ['weight' => 3.0],
        ]));
        $graph->addNode(new Node('C', []));
        $graph->addNode(new Node('D', []));

        return $graph;
    }

    public function testScoreNormalizesShortestPathCost(): void
    {
        $detector = new GraphPathDetector($this->graph(), null, 10.0);

        $score = $detector->score(['start' => 'A', 'end' => 'C'], new Context());

        $this->assertSame(0.5, $score);
    }

    public function testScoreUsesContextEndpointsAndFlagsUnreachablePath(): void
    {
        $detector = new GraphPathDetector($this->graph(), null, 10.0);

        $score = $detector->score([], new Context([
            'start' => 'A',
            'end' => 'D',
        ]));

        $this->assertSame(1.0, $score);
    }

    public function testScoreReturnsZeroWhenEndpointIsMissing(): void
    {
        $detector = new GraphPathDetector($this->graph(), null, 10.0);

        $score = $detector->score(['start' => 'A'], new Context());

        $this->assertSame(0.0, $score);
    }
}
