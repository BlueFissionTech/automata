<?php

namespace BlueFission\Automata\Strategy;

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
        $samples = [$input];

        $prediction = $this->_pipeline->predict($samples);

        return $prediction[0];
    }

    /**
     * Calculate the accuracy of the Naive Bayes model on the test data.
     *
     * @return float The accuracy of the model.
     */
    public function accuracy(): float
    {
        $predictedLabels = $this->_classifier->predictBatch($this->_testSamples);
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
            $this->_modelManager->saveToFile($this->_pipeline, $path);
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
                $this->_pipeline = $this->_modelManager->restoreFromFile($path);
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
