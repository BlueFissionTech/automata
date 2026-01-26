<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

abstract class Detector extends Obj implements IAnomalyDetector
{
    protected float $_threshold = 0.5;

    public function __construct(float $threshold = 0.5)
    {
        parent::__construct();
        $this->_threshold = $threshold;
    }

    public function setThreshold(float $threshold): void
    {
        $this->_threshold = $threshold;
    }

    public function threshold(): float
    {
        return $this->_threshold;
    }

    public function detect($input, Context $context, array $options = []): bool
    {
        $score = $this->score($input, $context, $options);
        return $score >= $this->_threshold;
    }

    public function predict($input)
    {
        $context = $this->resolveContext($input);
        return $this->score($input, $context);
    }

    abstract public function train(array $samples, array $labels, float $testSize = 0.2);

    abstract public function score($input, Context $context, array $options = []): float;

    public function accuracy(): float
    {
        return 0.0;
    }

    public function saveModel(string $path): bool
    {
        return false;
    }

    public function loadModel(string $path): bool
    {
        return false;
    }

    protected function resolveContext($input): Context
    {
        if ($input instanceof Activity) {
            return $input->context();
        }

        if (is_array($input) && isset($input['context']) && $input['context'] instanceof Context) {
            return $input['context'];
        }

        $context = new Context();
        return Dev::apply('automata.anomaly.detector.resolve_context', $context);
    }

    protected function resolveFeatures($input): array
    {
        if ($input instanceof Activity) {
            return $input->features();
        }

        if ($input instanceof Fingerprint) {
            return $input->features();
        }

        if (is_array($input)) {
            if (isset($input['features']) && is_array($input['features'])) {
                return $input['features'];
            }
            return $input;
        }

        return [];
    }
}
