<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;
use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Metric\Accuracy;
use Phpml\Dataset\ArrayDataset;
use Phpml\CrossValidation\RandomSplit;
use Phpml\Pipeline;
use Phpml\ModelManager;

class NaiveBayesTextClassification extends Strategy
{
    private $_classifier;
    private $_vectorizer;
    private $_transformer;
    private $_pipeline;
    private $_testLabels;
    private $_modelManager;

    public function __construct()
    {
        $this->_classifier = new NaiveBayes();
        $this->_vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
        $this->_transformer = new TfIdfTransformer();
        $this->_pipeline = new Pipeline([
            $this->_vectorizer,
            $this->_transformer,
        ], $this->_classifier);
        $this->_modelManager = new ModelManager();
    }

    /**
     * Set a custom pipeline for the classifier.
     *
     * @param Pipeline $pipeline The custom pipeline to set.
     */
    public function setPipeline(Pipeline $pipeline)
    {
        $this->_pipeline = $pipeline;
    }

    /**
     * Get the current pipeline of the classifier.
     *
     * @return Pipeline The current pipeline.
     */
    public function getPipeline()
    {
        return $this->_pipeline;
    }

    /**
     * Train the Naive Bayes model with the provided samples and labels.
     *
     * @param array $samples The training samples.
     * @param array $labels The corresponding labels for the training samples.
     * @param float $testSize The proportion of the dataset to include in the test split.
     */
    public function train($samples, $labels, float $testSize = 0.2)
    {
        $samples = Dev::apply('automata.strategy.naivebayestextclassification.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.naivebayestextclassification.train.2', $labels);
        Dev::do('automata.strategy.naivebayestextclassification.train.action1', ['samples' => $samples, 'labels' => $labels, 'testSize' => $testSize]);

        $splitDataset = new RandomSplit(new ArrayDataset($samples, $labels), $testSize);
        $trainSamples = $splitDataset->getTrainSamples();
        $trainLabels = $splitDataset->getTrainLabels();

        $this->_testSamples = $splitDataset->getTestSamples();
        $this->_testLabels = $splitDataset->getTestLabels();

        $this->_pipeline->train($trainSamples, $trainLabels);
    }

    /**
     * Predict the label for the given input.
     *
     * @param string $input The input text to classify.
     * @return string The predicted label.
     */
    public function predict($input): string
    {
        $input = Dev::apply('automata.strategy.naivebayestextclassification.predict.1', $input);
        Dev::do('automata.strategy.naivebayestextclassification.predict.action1', ['input' => $input]);

        $samples = [$input];

        $prediction = $this->_pipeline->predict($samples);
        $label = $prediction[0];

        $label = Dev::apply('automata.strategy.naivebayestextclassification.predict.2', $label);
        Dev::do('automata.strategy.naivebayestextclassification.predict.action2', ['input' => $input, 'prediction' => $label]);

        return $label;
    }

    /**
     * Calculate the accuracy of the Naive Bayes model on the test data.
     *
     * Note: depending on the php-ml version, labels may be
     * non-numeric, so we simply attempt to score and fall back
     * to 0.0 on failure.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        try {
            $predictedLabels = $this->_pipeline->predict($this->_testSamples);
            $accuracy = Accuracy::score($this->_testLabels, $predictedLabels);
            $accuracy = Dev::apply('automata.strategy.naivebayestextclassification.accuracy.1', $accuracy);
            Dev::do('automata.strategy.naivebayestextclassification.accuracy.action1', ['accuracy' => $accuracy]);
            return $accuracy;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Save the trained model to a file.
     *
     * @param string $path The path to save the model.
     * @return bool True if the model was saved successfully, false otherwise.
     */
    public function saveModel(string $path): bool
    {
        $path = Dev::apply('automata.strategy.naivebayestextclassification.saveModel.1', $path);
        Dev::do('automata.strategy.naivebayestextclassification.saveModel.action1', ['path' => $path, 'model' => 'naive_bayes_pipeline']);

        try {
            $this->_modelManager->saveToFile($this->_pipeline, $path);
            Dev::do('automata.strategy.naivebayestextclassification.saveModel.action2', ['path' => $path, 'saved' => true]);
            return true;
        } catch (\Exception $e) {
            Dev::do('automata.strategy.naivebayestextclassification.saveModel.action3', ['path' => $path, 'saved' => false, 'error' => $e]);
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
        $path = Dev::apply('automata.strategy.naivebayestextclassification.loadModel.1', $path);
        Dev::do('automata.strategy.naivebayestextclassification.loadModel.action1', ['path' => $path]);

        try {
            if (file_exists($path)) {
                $this->_pipeline = $this->_modelManager->restoreFromFile($path);
                Dev::do('automata.strategy.naivebayestextclassification.loadModel.action2', ['path' => $path, 'loaded' => true]);
                return true;
            } else {
                Dev::do('automata.strategy.naivebayestextclassification.loadModel.action3', ['path' => $path, 'loaded' => false, 'reason' => 'missing']);
                return false;
            }
        } catch (\Exception $e) {
            Dev::do('automata.strategy.naivebayestextclassification.loadModel.action4', ['path' => $path, 'loaded' => false, 'error' => $e]);
            return false;
        }
    }
}
