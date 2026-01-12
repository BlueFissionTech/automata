<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;
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
        // Initialize the MLP classifier with explicit class list (0-9) compatible with php-ml
        $classes = range(0, 9);
        $this->_classifier = new MLPClassifier(784, [100], $classes, 1000, new Sigmoid());
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
        $samples = Dev::apply('automata.strategy.neuralnetimageclassification.train.1', $samples);
        $targets = Dev::apply('automata.strategy.neuralnetimageclassification.train.2', $targets);
        Dev::do('automata.strategy.neuralnetimageclassification.train.action1', ['samples' => $samples, 'targets' => $targets, 'testSize' => $testSize]);

        // Split data into training and testing sets
        $count = count($samples);
        if ($count === 0) {
            return;
        }

        $testCount = (int)ceil($count * $testSize);
        if ($testCount >= $count) {
            $testCount = max(0, $count - 1);
        }

        $trainCount = max(1, $count - $testCount);

        $trainSamples = array_slice($samples, 0, $trainCount);
        $trainTargets = array_slice($targets, 0, $trainCount);

        $this->_testSamples = array_slice($samples, $trainCount);
        $this->_testTargets = array_slice($targets, $trainCount);

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
        $sample = Dev::apply('automata.strategy.neuralnetimageclassification.predict.1', $sample);
        Dev::do('automata.strategy.neuralnetimageclassification.predict.action1', ['sample' => $sample]);

        $prediction = $this->_classifier->predict($sample);

        $prediction = Dev::apply('automata.strategy.neuralnetimageclassification.predict.2', $prediction);
        Dev::do('automata.strategy.neuralnetimageclassification.predict.action2', ['sample' => $sample, 'prediction' => $prediction]);

        return $prediction;
    }

    /**
     * Calculate the accuracy of the model on the test data.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        if (empty($this->_testSamples) || empty($this->_testTargets)) {
            return 0.0;
        }

        $predicted = [];
        foreach ($this->_testSamples as $sample) {
            $predicted[] = $this->_classifier->predict($sample);
        }

        $accuracy = Accuracy::score($this->_testTargets, $predicted);
        $accuracy = Dev::apply('automata.strategy.neuralnetimageclassification.accuracy.1', $accuracy);
        Dev::do('automata.strategy.neuralnetimageclassification.accuracy.action1', ['accuracy' => $accuracy]);

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
        $path = Dev::apply('automata.strategy.neuralnetimageclassification.saveModel.1', $path);
        Dev::do('automata.strategy.neuralnetimageclassification.saveModel.action1', ['path' => $path, 'model' => 'neural_net_classifier']);

        try {
            $this->_modelManager->saveToFile($this->_classifier, $path);
            Dev::do('automata.strategy.neuralnetimageclassification.saveModel.action2', ['path' => $path, 'saved' => true]);
            return true;
        } catch (\Exception $e) {
            Dev::do('automata.strategy.neuralnetimageclassification.saveModel.action3', ['path' => $path, 'saved' => false, 'error' => $e]);
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
        $path = Dev::apply('automata.strategy.neuralnetimageclassification.loadModel.1', $path);
        Dev::do('automata.strategy.neuralnetimageclassification.loadModel.action1', ['path' => $path]);

        try {
            if (file_exists($path)) {
                $this->_classifier = $this->_modelManager->restoreFromFile($path);
                Dev::do('automata.strategy.neuralnetimageclassification.loadModel.action2', ['path' => $path, 'loaded' => true]);
                return true;
            } else {
                Dev::do('automata.strategy.neuralnetimageclassification.loadModel.action3', ['path' => $path, 'loaded' => false, 'reason' => 'missing']);
                return false;
            }
        } catch (\Exception $e) {
            Dev::do('automata.strategy.neuralnetimageclassification.loadModel.action4', ['path' => $path, 'loaded' => false, 'error' => $e]);
            return false;
        }
    }
}
