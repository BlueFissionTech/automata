<?php

namespace BlueFission\Automata\GraphTheory;

use BlueFission\Arr;

/**
 * Graph
 *
 * Simple weighted graph with Dijkstra-style shortest path, using
 * Develation's Arr as the underlying storage.
 */
class Graph
{
    /** @var Arr<string, array<string, array>> */
    protected Arr $_graph;

    /** @var Arr<string, Node> */
    protected Arr $_nodes;

    public function __construct(array $graph = [])
    {
        $this->_graph = new Arr($graph);
        $this->_nodes = new Arr([]);
    }

    public function addNode(Node $node): void
    {
        $name = $node->getName();
        $this->_nodes->set($name, $node);
        $this->_graph->set($name, $node->getEdges());
    }

    /**
     * Get the attributes for an edge between two nodes, if present.
     *
     * @param string $from
     * @param string $to
     * @return array|null
     */
    public function getEdgeAttributes(string $from, string $to): ?array
    {
        $edges = $this->_graph[$from] ?? null;
        if (!is_array($edges)) {
            return null;
        }

        return $edges[$to] ?? null;
    }

    /**
     * Compute shortest path using a fitness function over edge attributes.
     *
     * @param string   $start
     * @param string   $end
     * @param callable $fitnessFunction function(array $edgeAttributes): int|float
     * @return array<string> Node names in order from start to end, or [] if no path.
     */
    public function shortestPath(string $start, string $end, callable $fitnessFunction): array
    {
        $distances = [];
        $previous = [];

        $nodesArray = $this->_nodes->val();
        $unvisited = new Arr([]);

        foreach ($nodesArray as $name => $node) {
            $distances[$name] = PHP_INT_MAX;
            $previous[$name] = null;
            $unvisited->set($name, true);
        }

        if (!array_key_exists($start, $distances) || !array_key_exists($end, $distances)) {
            return [];
        }

        $distances[$start] = 0;

        while ($unvisited->count() > 0) {
            $unvisitedArray = $unvisited->val();

            $closest = null;
            $closestDistance = PHP_INT_MAX;

            foreach ($unvisitedArray as $name => $_) {
                if ($distances[$name] < $closestDistance) {
                    $closestDistance = $distances[$name];
                    $closest = $name;
                }
            }

            if ($closest === null) {
                break;
            }

            if ($closest === $end) {
                $path = [];
                $current = $end;

                while ($current !== null) {
                    $path[] = $current;
                    $current = $previous[$current] ?? null;
                }

                return array_reverse($path);
            }

            if ($distances[$closest] === PHP_INT_MAX) {
                break;
            }

            $edges = $this->_graph[$closest] ?? [];

            foreach ($edges as $neighbor => $value) {
                if (!array_key_exists($neighbor, $unvisitedArray)) {
                    continue;
                }

                $edgeCost = $fitnessFunction($value);
                if ($edgeCost < 0) {
                    continue;
                }

                $alt = $distances[$closest] + $edgeCost;

                if ($alt < ($distances[$neighbor] ?? PHP_INT_MAX)) {
                    $distances[$neighbor] = $alt;
                    $previous[$neighbor] = $closest;
                }
            }

            $unvisited->delete($closest);
        }

        return [];
    }
}
