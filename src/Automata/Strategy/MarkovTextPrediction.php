<?php
namespace BlueFission\Automata\Strategy;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\Classification\MarkovChain;
use Phpml\Metric\Accuracy;

/**
 * MarkovTextPrediction
 *
 * Adapts php-ai/php-ml MarkovChain to the Strategy interface.
 * Train is given samples and labels; Markov transitions are
 * inferred from adjacent pairs in the samples.
 */
class MarkovTextPrediction extends Strategy
{
    private MarkovChain $_markovChain;
    private WhitespaceTokenizer $_tokenizer;
    private ?NGramTokenizer $_nGramTokenizer = null;

    public function __construct()
    {
        $this->_markovChain = new MarkovChain();
        $this->_tokenizer = new WhitespaceTokenizer();
    }

    /**
     * Train the Markov model from sample/label pairs.
     *
     * Each sample is treated as a sequence (string); adjacent pairs
     * are converted into Markov transitions.
     *
     * @param array $samples array<int, string> sentences or sequences
     * @param array $labels  unused (kept for interface compatibility)
     * @param float $testSize fraction of samples used as test set
     */
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $transitions = [];
        $allPairs = [];

        foreach ($samples as $sample) {
            $words = $this->_tokenizer->tokenize((string)$sample);
            for ($i = 0; $i < count($words) - 1; $i++) {
                $prevWord = $words[$i];
                $nextWord = $words[$i + 1];
                $allPairs[] = [$prevWord, $nextWord];

                if (!isset($transitions[$prevWord])) {
                    $transitions[$prevWord] = [];
                }
                if (!isset($transitions[$prevWord][$nextWord])) {
                    $transitions[$prevWord][$nextWord] = 0;
                }
                $transitions[$prevWord][$nextWord]++;
            }
        }

        $this->_markovChain->train($transitions);

        $totalPairs = count($allPairs);
        $testCount = (int)($totalPairs * $testSize);
        $trainCount = $totalPairs - $testCount;

        $testPairs = array_slice($allPairs, $trainCount);

        $this->_testSamples = array_column($testPairs, 0);
        $this->_testTargets = array_column($testPairs, 1);
    }

    /**
     * Predict the next word in the sequence.
     *
     * @param mixed $input Previous word
     * @return string
     */
    public function predict($input): string
    {
        return $this->_markovChain->predict((string)$input);
    }

    public function accuracy(): float
    {
        if (empty($this->_testSamples) || empty($this->_testTargets)) {
            return 0.0;
        }

        $predicted = [];
        foreach ($this->_testSamples as $sample) {
            $predicted[] = $this->_markovChain->predict($sample);
        }

        return Accuracy::score($this->_testTargets, $predicted);
    }
}
