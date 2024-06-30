<?php
namespace BlueFission\Automata\Strategy;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\Classification\MarkovChain;
use Phpml\Metric\Accuracy;
use Phpml\ModelManager;

class MarkovTextPrediction extends Strategy
{
    private $_markovChain;
    private $_tokenizer;
    private $_nGramTokenizer;
    private $_modelManager;

    public function __construct()
    {
        $this->_markovChain = new MarkovChain();
        $this->_tokenizer = new WhitespaceTokenizer();
        $this->_modelManager = new ModelManager();
    }

    /**
     * Train the Markov text prediction model.
     *
     * @param string $text The input text to train on.
     * @param int $n The size of the n-grams.
     * @param float $testSize The proportion of the dataset to include in the test split.
     */
    public function train(string $text, int $n = 1, float $testSize = 0.2)
    {
        // Tokenize the text into words
        $words = $this->_tokenizer->tokenize($text);
        $this->_nGramTokenizer = new NGramTokenizer($n + 1);
        $nGrams = $this->_nGramTokenizer->tokenize($words);

        // Split data into training and testing sets
        $datasetSize = count($nGrams);
        $testCount = (int)($datasetSize * $testSize);
        $trainCount = $datasetSize - $testCount;
        $trainNGrams = array_slice($nGrams, 0, $trainCount);
        $testNGrams = array_slice($nGrams, $trainCount);

        // Prepare training data
        $transitions = [];
        foreach ($trainNGrams as $nGram) {
            $prevWord = $nGram[0];
            $nextWord = $nGram[1];

            if (!isset($transitions[$prevWord])) {
                $transitions[$prevWord] = [];
            }
            if (!isset($transitions[$prevWord][$nextWord])) {
                $transitions[$prevWord][$nextWord] = 0;
            }
            $transitions[$prevWord][$nextWord]++;
        }

        $this->_markovChain->train($transitions);

        // Prepare test samples and targets
        $this->_testSamples = array_column($testNGrams, 0);
        $this->_testTargets = array_column($testNGrams, 1);
    }

    /**
     * Predict the next word in the sequence.
     *
     * @param string $previousWord The previous word in the sequence.
     * @return string The predicted next word.
     */
    public function predict(string $previousWord): string
    {
        return $this->_markovChain->predict($previousWord);
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
            $predicted[] = $this->_markovChain->predict($sample);
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
            $this->_modelManager->saveToFile($this->_markovChain, $path);
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
                $this->_markovChain = $this->_modelManager->restoreFromFile($path);
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
