<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use BlueFission\Automata\Anomaly\Detector;
use BlueFission\Automata\Context;
use BlueFission\Data\Graph\Graph;
use BlueFission\DevElation as Dev;

class GraphPathDetector extends Detector
{
    protected Graph $graph;
    protected $fitness;
    protected float $maxCost;

    public function __construct(Graph $graph, ?callable $fitness = null, float $maxCost = 0.0, float $threshold = 0.5)
    {
        parent::__construct($threshold);
        $this->graph = $graph;
        $this->fitness = $fitness ?? function (array $attributes): float {
            return isset($attributes['weight']) ? (float)$attributes['weight'] : 1.0;
        };
        $this->maxCost = $maxCost;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function score($input, Context $context, array $options = []): float
    {
        $start = $options['start'] ?? ($input['start'] ?? $context->get('start'));
        $end = $options['end'] ?? ($input['end'] ?? $context->get('end'));

        if (!$start || !$end) {
            return 0.0;
        }

        $fitness = $this->fitness;
        $path = $this->graph->shortestPath((string)$start, (string)$end, $fitness);
        if (empty($path)) {
            return 1.0;
        }

        $cost = 0.0;
        for ($i = 0; $i < count($path) - 1; $i++) {
            $edge = $this->graph->getEdgeAttributes($path[$i], $path[$i + 1]);
            if (!is_array($edge)) {
                continue;
            }
            $cost += $fitness($edge);
        }

        $maxCost = $options['maxCost'] ?? $this->maxCost;
        if ($maxCost <= 0) {
            return (float)$cost;
        }

        $normalized = $cost / $maxCost;
        $normalized = Dev::apply('anomaly.detector.graphpath.score', $normalized);
        return min(1.0, max(0.0, $normalized));
    }
}
