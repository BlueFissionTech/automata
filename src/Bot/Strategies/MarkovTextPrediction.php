<?php
namespace BlueFission\Bot\Strategies;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\Classification\MarkovChain;
use Phpml\Metric\Accuracy;

class MarkovTextPrediction extends Strategy {
    private $markovChain;
    private $tokenizer;
    private $nGramTokenizer;
    private $testSamples;
    private $testTargets;

    public function __construct() {
        $this->markovChain = new MarkovChain();
        $this->tokenizer = new WhitespaceTokenizer();
    }

    public function train(string $chatMessages, int $n = 1, float $testSize = 0.2) {
        $words = $this->tokenizer->tokenize($chatMessages);
        $this->nGramTokenizer = new NGramTokenizer($n + 1);
        $nGrams = $this->nGramTokenizer->tokenize($words);

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

        $this->markovChain->train($transitions);

        // Prepare test samples and targets
        $this->testSamples = array_column($testNGrams, 0);
        $this->testTargets = array_column($testNGrams, 1);
    }

    public function predict(string $previousWord): string {
        return $this->markovChain->predict($previousWord);
    }

    public function accuracy(): float {
        $predicted = [];
        foreach ($this->testSamples as $sample) {
            $predicted[] = $this->markovChain->predict($sample);
        }

        return Accuracy::score($this->testTargets, $predicted);
    }
}

$chatMessages = file_get_contents('chat_dataset.txt'); // Replace with path to your dataset

$chatPredictor = new ChatPredictor();
$chatPredictor->train($chatMessages, 1, 0.2);

$previousWord = 'you'; // Replace with a word to predict the next word
$nextWordPrediction = $chatPredictor->predict($previousWord);
echo "Next word prediction: " . $nextWordPrediction . PHP_EOL;

$accuracy = $chatPredictor->accuracy();
echo "Model accuracy: " . $accuracy . PHP_EOL;
