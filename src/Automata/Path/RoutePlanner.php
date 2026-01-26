<?php

namespace BlueFission\Automata\Path;

use BlueFission\Obj;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * RoutePlanner
 *
 * Develation-style helper that uses a Graph plus a fitness
 * function to compute best routes and emit events for planned
 * or unreachable routes.
 *
 * Typical usage:
 * - Construct with a Graph and a fitness callable that maps
 *   edge attributes (e.g., time, risk) to a scalar cost.
 * - Call `plan($start, $end)` to get a Route or null.
 * - Listen for:
 *   - `graph.route_planned` to act on planned routes.
 *   - `graph.route_unreachable` to handle failures.
 */
class RoutePlanner extends Obj
{
    use Dispatches;

    protected Graph $graph;

    /** @var callable */
    protected $fitness;

    /**
     * @param Graph    $graph
     * @param callable $fitness function(array $edgeAttributes): int|float
     */
    public function __construct(Graph $graph, callable $fitness)
    {
        parent::__construct();
        $this->graph = $graph;
        $this->fitness = $fitness;
    }

    /**
     * Plan a route between two nodes.
     *
     * This is a thin wrapper over Graph::shortestPath that
     * also computes an aggregate cost and emits events so
     * that callers can hook workflow or logging around route
     * planning.
     *
     * @param string $start
     * @param string $end
     * @return Route|null
     */
    public function plan(string $start, string $end): ?Route
    {
        $path = $this->graph->shortestPath($start, $end, $this->fitness);

        if (empty($path)) {
            $this->dispatch(new Event('graph.route_unreachable', [
                'start' => $start,
                'end' => $end,
            ]));
            return null;
        }

        $cost = 0;
        $fitness = $this->fitness;

        for ($i = 0; $i < count($path) - 1; $i++) {
            $edge = $this->graph->getEdgeAttributes($path[$i], $path[$i + 1]);
            if (!is_array($edge)) {
                continue;
            }
            $edgeCost = $fitness($edge);
            if ($edgeCost < 0) {
                continue;
            }
            $cost += $edgeCost;
        }

        $route = new Route($path, $cost);

        $this->dispatch(new Event('graph.route_planned', [
            'start' => $start,
            'end' => $end,
            'route' => $route,
        ]));

        return $route;
    }
}
