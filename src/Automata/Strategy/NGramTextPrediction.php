<?php
namespace BlueFission\Automata\Strategy;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\Dataset\ArrayDataset;
use Phpml\Regression\LeastSquares;
use Phpml\Metric\Accuracy;
use Phpml\ModelManager;

class NGramTextPrediction extends Strategy
{
    private $_regression;
    private $_tokenizer;
    private $_nGramTokenizer;
    private $_modelManager;

    public function __construct()
    {
        // Initialize the regression model and tokenizers
        $this->_regression = new LeastSquares();
        $this->_tokenizer = new WhitespaceTokenizer();
        $this->_modelManager = new ModelManager();
    }

    /**
     * Train the NGram text prediction model.
     *
     * @param string $text The input text to train on.
     * @param int $n The size of the n-grams.
     * @param float $testSize The proportion of the dataset to include in the test split.
     */
    public function train(string $text, int $n = 10, float $testSize = 0.2)
    {
        // Tokenize the text into words
        $words = $this->_tokenizer->tokenize($text);
        $this->_nGramTokenizer = new NGramTokenizer($n + 1);
        $nGrams = $this->_nGramTokenizer->tokenize($words);

        $samples = [];
        $targets = [];

        // Create samples and targets from n-grams
        foreach ($nGrams as $nGram) {
            $samples[] = array_slice($nGram, 0, -1);
            $targets[] = end($nGram);
        }

        // Split data into training and testing sets
        $dataset = new ArrayDataset($samples, $targets);
        list($trainSamples, $testSamples, $trainTargets, $testTargets) = $dataset->randomSplit($testSize);
        
        $this->_testSamples = $testSamples;
        $this->_testTargets = $testTargets;

        // Train the regression model
        $this->_regression->train($trainSamples, $trainTargets);
    }

    /**
     * Predict the next word in the sequence.
     *
     * @param array $previousWords The previous words in the sequence.
     * @return string The predicted next word.
     */
    public function predict(array $previousWords): string
    {
        return $this->_regression->predict($previousWords);
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
            $predicted[] = $this->_regression->predict($sample);
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
            $this->_modelManager->saveToFile($this->_regression, $path);
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
                $this->_regression = $this->_modelManager->restoreFromFile($path);
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
