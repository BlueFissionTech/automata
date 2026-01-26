<?php

namespace BlueFission\Automata\Anomaly\Strategies;

/**
 * IsolationForestDetector
 *
 * Wraps an externally provided Isolation Forest model. Inject the model
 * instance from your preferred backend (Rubix, Python bridge, etc.).
 */
class IsolationForestDetector extends ExternalModelDetector
{
    public function __construct(object $model, ?callable $scoreResolver = null, ?callable $trainResolver = null, float $threshold = 0.5)
    {
        parent::__construct($model, $scoreResolver, $trainResolver, $threshold);
    }
}
