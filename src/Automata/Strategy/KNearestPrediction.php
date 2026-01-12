<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;
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
        $data   = Dev::apply('automata.strategy.knearestprediction.train.1', $data);
        $labels = Dev::apply('automata.strategy.knearestprediction.train.2', $labels);
        Dev::do('automata.strategy.knearestprediction.train.action1', ['data' => $data, 'labels' => $labels]);

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
        $input = Dev::apply('automata.strategy.knearestprediction.predict.1', $input);
        Dev::do('automata.strategy.knearestprediction.predict.action1', ['input' => $input]);

        $features = (array)$input;
        $prediction = $this->_knn->predict($features);

        $prediction = Dev::apply('automata.strategy.knearestprediction.predict.2', $prediction);
        Dev::do('automata.strategy.knearestprediction.predict.action2', ['input' => $input, 'prediction' => $prediction]);

        return $prediction;
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

        $accuracy = Accuracy::score($this->_testLabels, $predictedLabels);
        $accuracy = Dev::apply('automata.strategy.knearestprediction.accuracy.1', $accuracy);
        Dev::do('automata.strategy.knearestprediction.accuracy.action1', ['accuracy' => $accuracy]);

        return $accuracy;
    }

    /**
     * Save the trained model to a file.
     *
     * @param string $path The path to save the model.
     * @return bool True if the model was saved successfully, false otherwise.
     */
    public function saveModel(string $path): bool
    {
        $path = Dev::apply('automata.strategy.knearestprediction.saveModel.1', $path);
        Dev::do('automata.strategy.knearestprediction.saveModel.action1', ['path' => $path, 'model' => 'knn']);

        try {
            $this->_modelManager->saveToFile($this->_knn, $path);
            Dev::do('automata.strategy.knearestprediction.saveModel.action2', ['path' => $path, 'saved' => true]);
            return true;
        } catch (\Exception $e) {
            Dev::do('automata.strategy.knearestprediction.saveModel.action3', ['path' => $path, 'saved' => false, 'error' => $e]);
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
        $path = Dev::apply('automata.strategy.knearestprediction.loadModel.1', $path);
        Dev::do('automata.strategy.knearestprediction.loadModel.action1', ['path' => $path]);

        try {
            if (file_exists($path)) {
                $this->_knn = $this->_modelManager->restoreFromFile($path);
                Dev::do('automata.strategy.knearestprediction.loadModel.action2', ['path' => $path, 'loaded' => true]);
                return true;
            } else {
                Dev::do('automata.strategy.knearestprediction.loadModel.action3', ['path' => $path, 'loaded' => false, 'reason' => 'missing']);
                return false;
            }
        } catch (\Exception $e) {
            Dev::do('automata.strategy.knearestprediction.loadModel.action4', ['path' => $path, 'loaded' => false, 'error' => $e]);
            return false;
        }
    }
}
