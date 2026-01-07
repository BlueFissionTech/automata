<?php
namespace BlueFission\Automata\Strategy;

use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Tokenization\NGramTokenizer;
use Phpml\Metric\Accuracy;

/**
 * NGramTextPrediction
 *
 * Simple n-gram frequency-based predictor adapted to the
 * Strategy interface. Treats samples as sentences and
 * builds (n-1)-gram → next-word distributions.
 */
class NGramTextPrediction extends Strategy
{
    private WhitespaceTokenizer $_tokenizer;
    private ?NGramTokenizer $_nGramTokenizer = null;

    /** @var array<string,array<string,int>> */
    private array $_ngramCounts = [];

    public function __construct()
    {
        $this->_tokenizer = new WhitespaceTokenizer();
    }

    /**
     * Train from sample sentences and labels.
     *
     * @param array $samples array<int, string> sentences
     * @param array $labels  unused
     * @param float $testSize fraction of n-grams used as test set
     */
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        $n = 2; // default to bigrams for simplicity
        $this->_nGramTokenizer = new NGramTokenizer($n + 1);

        $allNGrams = [];

        foreach ($samples as $sample) {
            $words = $this->_tokenizer->tokenize((string)$sample);
            $nGrams = $this->_nGramTokenizer->tokenize($words);
            foreach ($nGrams as $nGram) {
                $context = implode(' ', array_slice($nGram, 0, -1));
                $target = end($nGram);
                $allNGrams[] = [$context, $target];

                if (!isset($this->_ngramCounts[$context])) {
                    $this->_ngramCounts[$context] = [];
                }
                if (!isset($this->_ngramCounts[$context][$target])) {
                    $this->_ngramCounts[$context][$target] = 0;
                }
                $this->_ngramCounts[$context][$target]++;
            }
        }

        $total = count($allNGrams);
        $testCount = (int)($total * $testSize);
        $trainCount = $total - $testCount;

        $testNGrams = array_slice($allNGrams, $trainCount);

        $this->_testSamples = array_column($testNGrams, 0);
        $this->_testTargets = array_column($testNGrams, 1);
    }

    /**
     * Predict the next word from previous words.
     *
     * @param mixed $input array of previous words
     * @return string
     */
    public function predict($input): string
    {
        $context = is_array($input) ? implode(' ', $input) : (string)$input;

        if (!isset($this->_ngramCounts[$context])) {
            return '';
        }

        $choices = $this->_ngramCounts[$context];
        arsort($choices);

        return (string)array_key_first($choices);
    }

    public function accuracy(): float
    {
        if (empty($this->_testSamples) || empty($this->_testTargets)) {
            return 0.0;
        }

        $predicted = [];
        foreach ($this->_testSamples as $context) {
            $words = explode(' ', $context);
            $predicted[] = $this->predict($words);
        }

        return Accuracy::score($this->_testTargets, $predicted);
    }
}
