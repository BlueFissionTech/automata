<?php

namespace BlueFission\Automata\Path;

use BlueFission\Obj;
use BlueFission\DevElation as Dev;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;

/**
 * RouteAllocator
 *
 * Generic, greedy allocator that assigns flow from assets to
 * demands along routes in a Graph while respecting per-edge
 * capacity constraints.
 *
 * This class is domain-agnostic; assets and demands are
 * represented as simple arrays with required keys:
 * - asset:  ['id' => string|int, 'origin' => string, 'capacity' => float]
 * - demand: ['id' => string|int, 'node' => string, 'amount' => float, 'priority' => float]
 *
 * Edge capacities are provided as:
 * - ['from|to' => float]
 */
class RouteAllocator extends Obj
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
     * Allocate flows from assets to demands.
     *
     * @param array<int,array> $assets
     * @param array<int,array> $demands
     * @param array<string,float> $edgeCapacities keyed as "from|to"
     * @return array<int,array> allocations with keys:
     *         asset_id, demand_id, path, amount
     */
    public function allocate(array $assets, array $demands, array $edgeCapacities): array
    {
        // Allow filters/actions to adjust or observe allocation inputs.
        $assets         = Dev::apply('automata.path.routeallocator.allocate.1', $assets);
        $demands        = Dev::apply('automata.path.routeallocator.allocate.2', $demands);
        $edgeCapacities = Dev::apply('automata.path.routeallocator.allocate.3', $edgeCapacities);
        Dev::do('automata.path.routeallocator.allocate.action1', [
            'assets'         => $assets,
            'demands'        => $demands,
            'edgeCapacities' => $edgeCapacities,
        ]);

        $fitness = $this->fitness;
        $allocations = [];
        $used = []; // edgeKey => usedAmount

        // Sort demands by priority descending.
        usort($demands, static function ($a, $b) {
            $pa = (float)($a['priority'] ?? 0.0);
            $pb = (float)($b['priority'] ?? 0.0);
            return $pb <=> $pa;
        });

        foreach ($demands as &$demand) {
            $remainingDemand = (float)($demand['amount'] ?? 0.0);
            if ($remainingDemand <= 0.0) {
                continue;
            }

            foreach ($assets as &$asset) {
                $remainingAsset = (float)($asset['capacity'] ?? 0.0);
                if ($remainingAsset <= 0.0) {
                    continue;
                }

                $start = (string)($asset['origin'] ?? '');
                $end = (string)($demand['node'] ?? '');
                if ($start === '' || $end === '') {
                    continue;
                }

                $path = $this->graph->shortestPath($start, $end, $fitness);
                if (empty($path)) {
                    continue;
                }

                // Compute residual capacity along the path.
                $residual = $this->pathResidualCapacity($path, $edgeCapacities, $used);
                if ($residual <= 0.0) {
                    continue;
                }

                $amount = min($remainingDemand, $remainingAsset, $residual);
                if ($amount <= 0.0) {
                    continue;
                }

                // Record allocation.
                $allocations[] = [
                    'asset_id' => $asset['id'] ?? null,
                    'demand_id' => $demand['id'] ?? null,
                    'path' => $path,
                    'amount' => $amount,
                ];

                // Update usage and remaining amounts.
                $this->applyAllocationUsage($path, $amount, $used);
                $remainingDemand -= $amount;
                $remainingAsset -= $amount;

                $demand['amount'] = $remainingDemand;
                $asset['capacity'] = $remainingAsset;

                $this->dispatch(new Event('graph.route_allocation', [
                    'asset' => $asset,
                    'demand' => $demand,
                    'path' => $path,
                    'amount' => $amount,
                ]));

                if ($remainingDemand <= 0.0) {
                    break;
                }
            }
        }

        $allocations = Dev::apply('automata.path.routeallocator.allocate.4', $allocations);
        Dev::do('automata.path.routeallocator.allocate.action2', ['allocations' => $allocations]);

        return $allocations;
    }

    /**
     * Compute residual capacity along a path.
     *
     * @param array<int,string>   $path
     * @param array<string,float> $edgeCapacities
     * @param array<string,float> $used
     */
    protected function pathResidualCapacity(array $path, array $edgeCapacities, array $used): float
    {
        $minResidual = null;

        for ($i = 0; $i < count($path) - 1; $i++) {
            $from = $path[$i];
            $to = $path[$i + 1];
            $key = $this->edgeKey($from, $to);

            $capacity = (float)($edgeCapacities[$key] ?? 0.0);
            $already = (float)($used[$key] ?? 0.0);
            $residual = max(0.0, $capacity - $already);

            if ($minResidual === null || $residual < $minResidual) {
                $minResidual = $residual;
            }
        }

        return $minResidual ?? 0.0;
    }

    /**
     * Apply allocation usage to per-edge usage map.
     *
     * @param array<int,string>   $path
     * @param float               $amount
     * @param array<string,float> $used
     */
    protected function applyAllocationUsage(array $path, float $amount, array &$used): void
    {
        for ($i = 0; $i < count($path) - 1; $i++) {
            $from = $path[$i];
            $to = $path[$i + 1];
            $key = $this->edgeKey($from, $to);
            $used[$key] = ($used[$key] ?? 0.0) + $amount;
        }
    }

    protected function edgeKey(string $from, string $to): string
    {
        return $from . '|' . $to;
    }
}

