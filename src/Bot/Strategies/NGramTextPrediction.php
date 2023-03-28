<?php
namespace BlueFission\Bot\Strategies;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Dataset\ArrayDataset;
use Phpml\Regression\LeastSquares;
use Phpml\Metric\Accuracy;

class NGramTextPrediction extends Strategy {
    private $regression;
    private $tokenizer;
    private $nGramTokenizer;
    private $testSamples;
    private $testTargets;

    public function __construct() {
        $this->regression = new LeastSquares();
        $this->tokenizer = new WhitespaceTokenizer();
    }

    public function train(string $text, int $n = 10, float $testSize = 0.2) {
        $words = $this->tokenizer->tokenize($text);
        $this->nGramTokenizer = new NGramTokenizer($n + 1);
        $nGrams = $this->nGramTokenizer->tokenize($words);

        $samples = [];
        $targets = [];

        foreach ($nGrams as $nGram) {
            $samples[] = array_slice($nGram, 0, -1);
            $targets[] = end($nGram);
        }

        // Split data into training and testing sets
        $dataset = new ArrayDataset($samples, $targets);
        list($trainSamples, $testSamples, $trainTargets, $testTargets) = $dataset->randomSplit($testSize);
        
        $this->testSamples = $testSamples;
        $this->testTargets = $testTargets;

        $this->regression->train($trainSamples, $trainTargets);
    }

    public function predict(array $previousWords): string {
        return $this->regression->predict($previousWords);
    }

    public function accuracy(): float {
        $predicted = [];
        foreach ($this->testSamples as $sample) {
            $predicted[] = $this->regression->predict($sample);
        }

        return Accuracy::score($this->testTargets, $predicted);
    }
}

$chatMessages = file_get_contents('chat_dataset.txt'); // Replace with path to your dataset

$chatPredictor = new ChatPredictor();
$chatPredictor->train($chatMessages, 10, 0.2);

$previousWords = ['how', 'are', 'you']; // Replace with words to predict the next word
$nextWordPrediction = $chatPredictor->predict($previousWords);
echo "Next word prediction: " . $nextWordPrediction . PHP_EOL;

$accuracy = $chatPredictor->accuracy();
echo "Model accuracy: " . $accuracy . PHP_EOL;
