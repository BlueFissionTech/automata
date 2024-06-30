<?php

namespace BlueFission\Automata\Services;

use BlueFission\Automata\Strategy\IStrategy;

/**
 * Class BenchmarkService
 *
 * Provides functionality to benchmark training and prediction processes of strategies
 * by measuring execution time.
 */
class BenchmarkService
{
    /**
     * Benchmark the training process of a strategy.
     *
     * @param IStrategy $strategy The strategy to benchmark.
     * @param array $dataset The training dataset.
     * @param array $labels The labels for the training dataset.
     * @return float The execution time in seconds.
     */
    public function benchmarkTraining(IStrategy $strategy, array $dataset, array $labels): float
    {
        $startTime = microtime(true); // Start the timer
        $strategy->train($dataset, $labels); // Train the strategy
        $endTime = microtime(true); // End the timer

        return $endTime - $startTime; // Calculate and return the execution time
    }

    /**
     * Benchmark the prediction process of a strategy.
     *
     * @param IStrategy $strategy The strategy to benchmark.
     * @param mixed $input The input data for prediction.
     * @return array The prediction output and execution time in seconds.
     */
    public function benchmarkPrediction(IStrategy $strategy, $input): array
    {
        $startTime = microtime(true); // Start the timer
        $output = $strategy->predict($input); // Predict using the strategy
        $endTime = microtime(true); // End the timer

        $executionTime = $endTime - $startTime; // Calculate the execution time

        return ['output' => $output, 'executionTime' => $executionTime]; // Return the prediction output and execution time
    }
}
