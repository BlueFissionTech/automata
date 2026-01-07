<?php

namespace BlueFission\Automata\Strategy;

use Phpml\Dataset\ArrayDataset;
use Phpml\NeuralNetwork\ActivationFunction\Sigmoid;
use Phpml\Classification\MLPClassifier;
use Phpml\Metric\Accuracy;
use Phpml\ModelManager;

class NeuralNetImageClassification extends Strategy
{
    private $_classifier;
    protected $_testSamples;
    protected $_testTargets;
    private $_modelManager;

    public function __construct()
    {
        // Initialize the MLP classifier
        $this->_classifier = new MLPClassifier(784, [100], 10, new Sigmoid());
        $this->_modelManager = new ModelManager();
    }

    /**
     * Train the neural network image classification model.
     *
     * @param array $samples The training samples.
     * @param array $targets The corresponding targets for the training samples.
     * @param float $testSize The proportion of the dataset to include in the test split.
     */
    public function train(array $samples, array $targets, float $testSize = 0.2)
    {
        // Split data into training and testing sets
        $dataset = new ArrayDataset($samples, $targets);
        list($trainSamples, $testSamples, $trainTargets, $testTargets) = $dataset->randomSplit($testSize);

        $this->_testSamples = $testSamples;
        $this->_testTargets = $testTargets;

        // Train the classifier
        $this->_classifier->train($trainSamples, $trainTargets);
    }

    /**
     * Predict the class for the given sample.
     *
     * @param mixed $sample The sample to classify.
     * @return mixed The predicted class.
     */
    public function predict($sample)
    {
        return $this->_classifier->predict($sample);
    }

    /**
     * Calculate the accuracy of the model on the test data.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        $predicted = [];
        foreach ($this->_testSamples as $sample) {
            $predicted[] = $this->_classifier->predict($sample);
        }

        return Accuracy::score($this->_testTargets, $predicted);
    }

    /**
     * Save the trained model to a file.
     *
     * @param string $path The path to save the model.
     * @return bool True if the model was saved successfully, false otherwise.
     */
    public function saveModel(string $path): bool
    {
        try {
            $this->_modelManager->saveToFile($this->_classifier, $path);
            return true;
        } catch (\Exception $e) {
            // Handle the exception
            return false;
        }
    }

    /**
     * Load the trained model from a file.
     *
     * @param string $path The path to load the model from.
     * @return bool True if the model was loaded successfully, false otherwise.
     */
    public function loadModel(string $path): bool
    {
        try {
            if (file_exists($path)) {
                $this->_classifier = $this->_modelManager->restoreFromFile($path);
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            // Handle the exception
            return false;
        }
    }
}
