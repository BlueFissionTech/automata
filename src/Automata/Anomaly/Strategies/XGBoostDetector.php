<?php

namespace BlueFission\Automata\Anomaly\Strategies;

/**
 * XGBoostDetector
 *
 * Wraps an externally provided XGBoost model. Inject the model instance
 * from your preferred backend (php-ml extension, Rubix, or a custom adapter).
 */
class XGBoostDetector extends ExternalModelDetector
{
    public function __construct(object $model, ?callable $scoreResolver = null, ?callable $trainResolver = null, float $threshold = 0.5)
    {
        parent::__construct($model, $scoreResolver, $trainResolver, $threshold);
    }
}
