<?php

namespace BlueFission\Automata\Anomaly\Strategies;

use BlueFission\Automata\Anomaly\Detector;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;
use Phpml\Classification\Classifier;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Dataset\ArrayDataset;
use Phpml\Metric\Accuracy;

abstract class PhpmlClassifierDetector extends Detector
{
    protected Classifier $classifier;
    protected string $anomalyLabel;
    protected array $testSamples = [];
    protected array $testLabels = [];

    public function __construct(Classifier $classifier, string $anomalyLabel = 'anomaly', float $threshold = 0.5)
    {
        parent::__construct($threshold);
        $this->classifier = $classifier;
        $this->anomalyLabel = $anomalyLabel;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('anomaly.detector.phpml.train.samples', $samples);
        $labels = Dev::apply('anomaly.detector.phpml.train.labels', $labels);
        Dev::do('anomaly.detector.phpml.train', ['samples' => $samples, 'labels' => $labels, 'testSize' => $testSize]);

        $split = new RandomSplit(new ArrayDataset($samples, $labels), $testSize);
        $trainSamples = $split->getTrainSamples();
        $trainLabels = $split->getTrainLabels();

        $this->testSamples = $split->getTestSamples();
        $this->testLabels = $split->getTestLabels();

        $this->classifier->train($trainSamples, $trainLabels);
    }

    public function score($input, Context $context, array $options = []): float
    {
        $features = $this->resolveFeatures($input);
        if (empty($features)) {
            return 0.0;
        }

        $prediction = $this->classifier->predict([$features]);
        if (is_array($prediction)) {
            $prediction = $prediction[0] ?? null;
        }

        return $this->predictionToScore($prediction);
    }

    public function accuracy(): float
    {
        if (empty($this->testSamples) || empty($this->testLabels)) {
            return 0.0;
        }

        try {
            $predicted = $this->classifier->predict($this->testSamples);
            return Accuracy::score($this->testLabels, $predicted);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    protected function predictionToScore($prediction): float
    {
        if ($prediction === null) {
            return 0.0;
        }

        if ($this->anomalyLabel !== '' && (string)$prediction === (string)$this->anomalyLabel) {
            return 1.0;
        }

        if (is_numeric($prediction)) {
            return (float)$prediction > 0 ? 1.0 : 0.0;
        }

        return 0.0;
    }
}
