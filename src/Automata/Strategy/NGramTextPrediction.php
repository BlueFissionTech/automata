<?php
namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;
use Phpml\Tokenization\WhitespaceTokenizer;
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
        $samples = Dev::apply('automata.strategy.ngramtextprediction.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.ngramtextprediction.train.2', $labels);
        Dev::do('automata.strategy.ngramtextprediction.train.action1', ['samples' => $samples, 'labels' => $labels, 'testSize' => $testSize]);

        $n = 2; // default to bigrams for simplicity
        $size = $n + 1;

        $allNGrams = [];
        $this->_ngramCounts = [];

        foreach ($samples as $sample) {
            $words = $this->_tokenizer->tokenize((string)$sample);
            $nGrams = $this->buildNGrams($words, $size);
            foreach ($nGrams as $nGram) {
                $context = implode(' ', array_slice($nGram, 0, -1));
                $target = end($nGram);
                $allNGrams[] = [$context, $target];
            }
        }

        $total = count($allNGrams);
        $testCount = (int)($total * $testSize);
        $trainCount = $total - $testCount;

        $trainNGrams = array_slice($allNGrams, 0, $trainCount);
        $testNGrams = array_slice($allNGrams, $trainCount);

        // Populate counts only from the training n-grams so that the
        // test n-grams remain held out for evaluation and do not
        // inflate accuracy() metrics.
        foreach ($trainNGrams as [$context, $target]) {
            if (!isset($this->_ngramCounts[$context])) {
                $this->_ngramCounts[$context] = [];
            }
            if (!isset($this->_ngramCounts[$context][$target])) {
                $this->_ngramCounts[$context][$target] = 0;
            }
            $this->_ngramCounts[$context][$target]++;
        }

        $this->_testSamples = array_column($testNGrams, 0);
        $this->_testTargets = array_column($testNGrams, 1);

        Dev::do('automata.strategy.ngramtextprediction.train.action2', [
            'trainNGrams' => $trainNGrams,
            'testNGrams'  => $testNGrams,
        ]);
    }

    /**
     * Predict the next word from previous words.
     *
     * @param mixed $input array of previous words
     * @return string
     */
    public function predict($input): string
    {
        $input = Dev::apply('automata.strategy.ngramtextprediction.predict.1', $input);
        Dev::do('automata.strategy.ngramtextprediction.predict.action1', ['input' => $input]);

        $context = is_array($input) ? implode(' ', $input) : (string)$input;

        if (!isset($this->_ngramCounts[$context])) {
            return '';
        }

        $choices = $this->_ngramCounts[$context];
        arsort($choices);

        $prediction = (string)array_key_first($choices);
        $prediction = Dev::apply('automata.strategy.ngramtextprediction.predict.2', $prediction);
        Dev::do('automata.strategy.ngramtextprediction.predict.action2', ['input' => $input, 'prediction' => $prediction]);

        return $prediction;
    }

    public function accuracy(): float
    {
        if (empty($this->_testSamples) || empty($this->_testTargets)) {
            return 0.0;
        }

        $predicted = [];
        $targets = [];
        foreach ($this->_testSamples as $index => $context) {
            if (!isset($this->_ngramCounts[$context])) {
                continue;
            }

            $words = explode(' ', $context);
            $predicted[] = $this->predict($words);
            $targets[] = $this->_testTargets[$index];
        }

        if (empty($targets)) {
            return 0.0;
        }

        $accuracy = Accuracy::score($targets, $predicted);
        $accuracy = Dev::apply('automata.strategy.ngramtextprediction.accuracy.1', $accuracy);
        Dev::do('automata.strategy.ngramtextprediction.accuracy.action1', ['accuracy' => $accuracy]);

        return $accuracy;
    }

    /**
     * Build n-gram sequences of tokens from a list of words.
     *
     * @param array<int,string> $tokens
     * @param int $size number of words per n-gram
     * @return array<int,array<int,string>>
     */
    private function buildNGrams(array $tokens, int $size): array
    {
        $ngrams = [];
        $count = count($tokens);

        if ($size <= 0 || $count < $size) {
            return $ngrams;
        }

        for ($i = 0; $i <= $count - $size; $i++) {
            $ngrams[] = array_slice($tokens, $i, $size);
        }

        return $ngrams;
    }
}
