<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use BlueFission\Automata\Anomaly\Detector;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

class ExternalModelDetector extends Detector
{
    protected object $model;
    protected $scoreResolver;
    protected $trainResolver;

    public function __construct(object $model, ?callable $scoreResolver = null, ?callable $trainResolver = null, float $threshold = 0.5)
    {
        parent::__construct($threshold);
        $this->model = $model;
        $this->scoreResolver = $scoreResolver;
        $this->trainResolver = $trainResolver;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('anomaly.detector.external.train.samples', $samples);
        $labels = Dev::apply('anomaly.detector.external.train.labels', $labels);

        if (is_callable($this->trainResolver)) {
            ($this->trainResolver)($this->model, $samples, $labels);
            return null;
        }

        if (method_exists($this->model, 'train')) {
            $this->model->train($samples, $labels);
            return null;
        }

        if (method_exists($this->model, 'fit')) {
            $this->model->fit($samples, $labels);
            return null;
        }

        throw new \RuntimeException('External model does not support train/fit.');
    }

    public function score($input, Context $context, array $options = []): float
    {
        $features = $this->resolveFeatures($input);
        if (empty($features)) {
            return 0.0;
        }

        $prediction = $this->predictWithModel($features, $options);

        if (is_callable($this->scoreResolver)) {
            return (float)($this->scoreResolver)($prediction, $features, $context, $options);
        }

        if (is_array($prediction)) {
            $prediction = $prediction[0] ?? 0.0;
        }

        if (is_bool($prediction)) {
            return $prediction ? 1.0 : 0.0;
        }

        if (is_numeric($prediction)) {
            $value = (float)$prediction;
            return $value < 0 ? 0.0 : min(1.0, $value);
        }

        return 0.0;
    }

    protected function predictWithModel(array $features, array $options)
    {
        if (method_exists($this->model, 'predict')) {
            return $this->model->predict([$features]);
        }

        if (method_exists($this->model, 'score')) {
            return $this->model->score($features);
        }

        if (method_exists($this->model, 'evaluate')) {
            return $this->model->evaluate($features);
        }

        throw new \RuntimeException('External model does not support predict/score.');
    }
}
