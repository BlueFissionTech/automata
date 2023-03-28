<?php

namespace BlueFission\Bot\Strategies;

use Phpml\Dataset\ArrayDataset;
use Phpml\NeuralNetwork\ActivationFunction\Sigmoid;
use Phpml\Classification\MLPClassifier;
use Phpml\Metric\Accuracy;

class NeuralNetImageClassification extends Strategy
{
    private $classifier;
    private $testSamples;
    private $testTargets;

    public function __construct()
    {
        $this->classifier = new MLPClassifier(784, [100], 10, new Sigmoid());
    }

    public function train(array $samples, array $targets, float $testSize = 0.2)
    {
        // Split data into training and testing sets
        $dataset = new ArrayDataset($samples, $targets);
        list($trainSamples, $testSamples, $trainTargets, $testTargets) = $dataset->randomSplit($testSize);

        $this->testSamples = $testSamples;
        $this->testTargets = $testTargets;

        $this->classifier->train($trainSamples, $trainTargets);
    }

    public function predict(array $sample): int
    {
        return $this->classifier->predict($sample);
    }

    public function accuracy(): float
    {
        $predicted = [];
        foreach ($this->testSamples as $sample) {
            $predicted[] = $this->classifier->predict($sample);
        }

        return Accuracy::score($this->testTargets, $predicted);
    }
}

// Load your dataset
$samples = []; // Replace with your array of samples (each sample should be an array of 784 elements for 28x28 images)
$targets = []; // Replace with your array of targets (each target should be an integer from 0 to 9)

$iconClassifier = new NeuralNetClassificationStrategy();
$iconClassifier->train($samples, $targets, 0.2);

$sampleToPredict = []; // Replace with a single sample to predict (array of 784 elements)
$prediction = $iconClassifier->predict($sampleToPredict);
echo "Prediction: " . $prediction . PHP_EOL;

$accuracy = $iconClassifier->accuracy();
echo "Model accuracy: " . $accuracy . PHP_EOL;
