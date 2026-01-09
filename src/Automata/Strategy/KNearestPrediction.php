<?php

namespace BlueFission\Automata\Strategy;

use Phpml\Classification\KNearestNeighbors;
use Phpml\Dataset\ArrayDataset;
use Phpml\Metric\Accuracy;
use Phpml\ModelManager;

class KNearestPrediction extends Strategy
{
    /**
     * Underlying KNN classifier from php-ml.
     */
    private $_knn;

    /**
     * Local copies of test samples/labels; protected to be
     * compatible with the base Strategy visibility.
     */
    protected $_testSamples;
    protected $_testLabels;

    private $_modelManager;

    public function __construct(int $k = 3)
    {
        // Initialize the KNN classifier with k neighbors
        $this->_knn = new KNearestNeighbors($k);
        // Initialize the ModelManager for saving and loading models
        $this->_modelManager = new ModelManager();
    }

    /**
     * Train the KNN model with the provided data and labels.
     *
     * @param array $data The training data.
     * @param array $labels The corresponding labels for the training data.
     * @param float $testSize The proportion of the dataset to include in the test split.
     */
    public function train(array $data, array $labels, float $testSize = 0.2): void
    {
        $dataset = new ArrayDataset($data, $labels);
        $samples = $dataset->getSamples();
        $targets = $dataset->getTargets();

        $count = count($samples);
        $testCount = (int)($count * $testSize);
        $trainCount = $count - $testCount;

        $trainSamples = array_slice($samples, 0, $trainCount);
        $trainLabels = array_slice($targets, 0, $trainCount);
        $this->_testSamples = array_slice($samples, $trainCount);
        $this->_testLabels = array_slice($targets, $trainCount);

        // Train the KNN classifier
        $this->_knn->train($trainSamples, $trainLabels);
    }

    /**
     * Predict the label for the given features.
     *
     * @param mixed $input The features to predict the label for.
     * @return mixed The predicted label.
     */
    public function predict($input)
    {
        $features = (array)$input;
        return $this->_knn->predict($features);
    }

    /**
     * Calculate the accuracy of the KNN model on the test data.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        $predictedLabels = [];
        foreach ($this->_testSamples as $sample) {
            $predictedLabels[] = $this->_knn->predict($sample);
        }

        return Accuracy::score($this->_testLabels, $predictedLabels);
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
            $this->_modelManager->saveToFile($this->_knn, $path);
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
                $this->_knn = $this->_modelManager->restoreFromFile($path);
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
