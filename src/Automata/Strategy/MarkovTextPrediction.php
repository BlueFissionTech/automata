<?php
namespace BlueFission\Automata\Strategy;

use BlueFission\DevElation as Dev;
use BlueFission\Automata\Language\MarkovPredictor;

/**
 * MarkovTextPrediction
 *
 * Adapts Automata's MarkovPredictor to the Strategy interface.
 * Train is given samples and labels; Markov transitions are
 * inferred from adjacent pairs in the samples.
 */
class MarkovTextPrediction extends Strategy
{
    private MarkovPredictor $_markovPredictor;

    public function __construct()
    {
        $this->_markovPredictor = new MarkovPredictor();
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
        $samples = Dev::apply('automata.strategy.markovtextprediction.train.1', $samples);
        $labels  = Dev::apply('automata.strategy.markovtextprediction.train.2', $labels);
        Dev::do('automata.strategy.markovtextprediction.train.action1', ['samples' => $samples, 'labels' => $labels, 'testSize' => $testSize]);

        $this->_markovPredictor = new MarkovPredictor();

        $totalSamples = count($samples);
        $testCount = (int)($totalSamples * $testSize);
        $trainCount = $totalSamples - $testCount;

        $trainSamples = array_slice($samples, 0, $trainCount);
        $testSamples = array_slice($samples, $trainCount);

        foreach ($trainSamples as $sample) {
            $this->_markovPredictor->addSentence((string)$sample);
        }

        $this->_testSamples = [];
        $this->_testTargets = [];

        foreach ($testSamples as $sample) {
            $tokens = $this->_markovPredictor->tokenize((string)$sample);
            $tokenCount = count($tokens);
            for ($i = 0; $i < $tokenCount - 1; $i++) {
                $this->_testSamples[] = $tokens[$i];
                $this->_testTargets[] = $tokens[$i + 1];
            }
        }

        if (empty($this->_testSamples)) {
            foreach ($trainSamples as $sample) {
                $tokens = $this->_markovPredictor->tokenize((string)$sample);
                $tokenCount = count($tokens);
                for ($i = 0; $i < $tokenCount - 1; $i++) {
                    $this->_testSamples[] = $tokens[$i];
                    $this->_testTargets[] = $tokens[$i + 1];
                }
            }
        }

        Dev::do('automata.strategy.markovtextprediction.train.action2', [
            'trainSamples' => $trainSamples,
            'testSamples'  => $testSamples,
        ]);
    }

    /**
     * Predict the next word in the sequence.
     *
     * @param mixed $input Previous word
     * @return string
     */
    public function predict($input): string
    {
        $input = Dev::apply('automata.strategy.markovtextprediction.predict.1', $input);
        Dev::do('automata.strategy.markovtextprediction.predict.action1', ['input' => $input]);

        $prediction = $this->_markovPredictor->predictNextWord((string)$input);

        $prediction = Dev::apply('automata.strategy.markovtextprediction.predict.2', $prediction);
        Dev::do('automata.strategy.markovtextprediction.predict.action2', ['input' => $input, 'prediction' => $prediction]);

        return (string)($prediction ?? '');
    }

    public function accuracy(): float
    {
        if (empty($this->_testSamples) || empty($this->_testTargets)) {
            return 0.0;
        }

        $total = count($this->_testSamples);
        if ($total === 0) {
            return 0.0;
        }

        $matches = 0;
        foreach ($this->_testSamples as $index => $sample) {
            $prediction = $this->_markovPredictor->predictNextWord($sample);
            if ($prediction !== null && $prediction === $this->_testTargets[$index]) {
                $matches++;
            }
        }

        $accuracy = $matches / $total;
        $accuracy = Dev::apply('automata.strategy.markovtextprediction.accuracy.1', $accuracy);
        Dev::do('automata.strategy.markovtextprediction.accuracy.action1', ['accuracy' => $accuracy]);

        return $accuracy;
    }
}
