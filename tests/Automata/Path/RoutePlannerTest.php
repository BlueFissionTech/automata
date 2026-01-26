<?php

namespace BlueFission\Tests\Automata\Path;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Path\Graph;
use BlueFission\Automata\Path\Node;
use BlueFission\Automata\Path\RoutePlanner;
use BlueFission\Automata\Path\Route;

class RoutePlannerTest extends TestCase
{
    private function buildGraph(): Graph
    {
        $graph = new Graph();

        $hub = new Node('Hub', [
            'Bridge' => ['time' => 20, 'risk' => 4],
            'Highway' => ['time' => 35, 'risk' => 1],
        ]);

        $bridge = new Node('Bridge', [
            'Hospital' => ['time' => 5, 'risk' => 4],
        ]);

        $highway = new Node('Highway', [
            'Hospital' => ['time' => 10, 'risk' => 1],
        ]);

        $hospital = new Node('Hospital', []);

        $graph->addNode($hub);
        $graph->addNode($bridge);
        $graph->addNode($highway);
        $graph->addNode($hospital);

        return $graph;
    }

    public function testPlannerReturnsRouteObjectWithPathAndCost(): void
    {
        $graph = $this->buildGraph();

        $fitness = function (array $edge): int {
            $time = $edge['time'] ?? 0;
            $risk = $edge['risk'] ?? 0;

            return $time + $risk * 20;
        };

        $planner = new RoutePlanner($graph, $fitness);

        $route = $planner->plan('Hub', 'Hospital');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame(['Hub', 'Highway', 'Hospital'], $route->getPath());
        $this->assertGreaterThan(0, $route->getCost());
    }

    public function testPlannerReturnsNullWhenUnreachable(): void
    {
        $graph = new Graph();

        $a = new Node('A', ['B' => ['time' => 5, 'risk' => 1]]);
        $b = new Node('B', []);
        $c = new Node('C', []);

        $graph->addNode($a);
        $graph->addNode($b);
        $graph->addNode($c);

        $fitness = function (array $edge): int {
            return ($edge['time'] ?? 0) + ($edge['risk'] ?? 0) * 10;
        };

        $planner = new RoutePlanner($graph, $fitness);

        $route = $planner->plan('A', 'C');

        $this->assertNull($route);
    }
}

