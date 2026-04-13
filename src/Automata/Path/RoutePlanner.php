<?php

namespace BlueFission\Automata\Path;

use BlueFission\Automata\Adapters\StateAdapter;
use BlueFission\Automata\Support\Evaluates;
use BlueFission\Func;
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
    use Evaluates;

    protected Graph $graph;

    protected Func $fitness;
    protected ?Func $assessor = null;
    protected ?StateAdapter $stateAdapter = null;

    /**
     * @param Graph    $graph
     * @param callable|Func $fitness function(array $edgeAttributes): int|float
     */
    public function __construct(Graph $graph, Func|callable $fitness, mixed $state = null, Func|callable|null $assessor = null)
    {
        parent::__construct();
        $this->graph = $graph;
        $this->fitness = $this->asFunc($fitness);

        if ($state !== null) {
            $this->setState($state);
        }

        if ($assessor !== null) {
            $this->setAssessor($assessor);
        }
    }

    public function setState(mixed $state): self
    {
        $this->stateAdapter = StateAdapter::wrap($state);

        return $this;
    }

    public function state(): ?StateAdapter
    {
        return $this->stateAdapter;
    }

    public function setAssessor(Func|callable $assessor): self
    {
        $this->assessor = $this->asFunc($assessor);

        return $this;
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
    public function plan(string $start, string $end, array $context = []): ?Route
    {
        $path = $this->graph->shortestPath($start, $end, function ($edge) use ($start, $end, $context) {
            return $this->edgeCost($edge, [
                'start' => $start,
                'end' => $end,
            ] + $context);
        });

        if (empty($path)) {
            $this->dispatch(new Event('graph.route_unreachable', [
                'start' => $start,
                'end' => $end,
                'state' => $this->stateAdapter?->snapshot() ?? [],
                'context' => $context,
            ]));
            return null;
        }

        $cost = 0;

        for ($i = 0; $i < count($path) - 1; $i++) {
            $edge = $this->graph->getEdgeAttributes($path[$i], $path[$i + 1]);
            if (!is_array($edge)) {
                continue;
            }
            $edgeCost = $this->edgeCost($edge, [
                'start' => $start,
                'end' => $end,
                'from' => $path[$i],
                'to' => $path[$i + 1],
            ] + $context);
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
            'state' => $this->stateAdapter?->snapshot() ?? [],
            'context' => $context,
        ]));

        return $route;
    }

    protected function edgeCost(array $edge, array $context = []): float|int
    {
        $state = $this->stateAdapter?->snapshot() ?? [];

        if ($this->assessor instanceof Func) {
            $score = $this->invokeFunc($this->assessor, [$edge, $state, $context, $this]);
            if ($score !== null) {
                return $this->numericValue($score);
            }
        }

        $score = $this->invokeFunc($this->fitness, [$edge, $state, $context, $this]);

        return $this->numericValue($score);
    }
}
