<?php

namespace BlueFission\Automata;

use BlueFission\Obj;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Sensory\Input;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Service\BenchmarkService;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;

/**
 * Class Intelligence
 *
 * Manages and orchestrates different AI strategies to analyze input data,
 * make predictions, and learn from feedback.
 */
class Intelligence extends Obj
{
    use Dispatches;

    protected OrganizedCollection $_strategies; // Collection of strategies with weights
    protected float $_minThreshold; // Minimum accuracy threshold for a strategy
    protected IStrategy $_lastStrategyUsed; // Last strategy used for prediction
    private array $_strategyGroups; // Groups of strategies based on data type
    private BenchmarkService $_benchmarkService; // Service for benchmarking strategies

    const PREDICTION_EVENT = 'prediction_event'; // Event name for predictions

    /**
     * Constructor
     *
     * @param float $minThreshold Minimum accuracy threshold for strategies
     */
    public function __construct($minThreshold = 0.8)
    {
        $this->_strategies = new OrganizedCollection();
        $this->_minThreshold = $minThreshold;
        $this->_strategyGroups = [];
        $this->_benchmarkService = new BenchmarkService(); // Initialize benchmark service
        parent::__construct();
    }

    /**
     * Register a strategy with a given name
     *
     * @param IStrategy $strategy The strategy to register
     * @param string $name The name of the strategy
     */
    public function registerStrategy(IStrategy $strategy, string $name)
    {
        $this->_strategies->add($strategy, $name);
    }

    /**
     * Register a group of strategies
     *
     * @param DataGroup $group The strategy group to register
     */
    public function registerStrategyGroup(DataGroup $group)
    {
        $this->_strategyGroups[$group->getName()] = $group;
    }

    /**
     * Register an input and set up an event listener for processing it
     *
     * @param Input $input The input to register
     */
    public function registerInput(Input $input)
    {
        $input->on(Event::COMPLETE, function (Behavior $event) {
            $data = $event->context;
            foreach ($this->_strategies as $strategy) {
                return $strategy->predict($data);
            }
        });
    }

    /**
     * Scan the input, determine its type, and use appropriate strategies to make predictions
     *
     * @param mixed $input The input data to scan
     */
    public function scan($input)
    {
        $dataType = $this->getType($input);
        if ($dataType && isset($this->_strategyGroups[$dataType])) {
            $group = $this->_strategyGroups[$dataType];
            $strategies = $group->getStrategies();

            // Iterate through strategies and use them
            foreach ($strategies as $strategy) {
                $result = $this->_benchmarkService->benchmarkPrediction($strategy, $input);
                $this->dispatch(self::PREDICTION_EVENT, [
                    'strategy' => get_class($strategy),
                    'output' => $result['output'],
                    'executionTime' => $result['executionTime'],
                    'type' => $dataType,
                ]);
            }
        }
    }

    /**
     * Train all registered strategies on the provided dataset
     *
     * @param array $dataset The training data
     * @param array $labels The labels for the training data
     */
    public function train(array $dataset, array $labels)
    {
        foreach ($this->_strategies as $strategy) {
            $executionTime = $this->_benchmarkService->benchmarkTraining($strategy, $dataset, $labels);
            $accuracy = $strategy->accuracy();
            $score = $this->calculateScore($accuracy, $executionTime);

            $this->_strategies->weight($strategy, $score);

            if ($accuracy >= $this->_minThreshold) {
                break;
            }
        }

        $this->_strategies->sort();
    }

    /**
     * Make a prediction using the best-rated strategy
     *
     * @param mixed $input The input data for prediction
     * @return mixed The prediction result
     */
    public function predict($input)
    {
        $bestStrategy = $this->_strategies->first();
        $this->_lastStrategyUsed = $bestStrategy;
        return $bestStrategy->predict($input);
    }

    /**
     * Approve the last prediction, increasing the weight of the strategy used
     */
    public function approvePrediction()
    {
        if (isset($this->_lastStrategyUsed)) {
            $score = $this->_strategies->weight($this->_lastStrategyUsed);
            $newScore = $score * 1.1;
            $this->_strategies->weight($this->_lastStrategyUsed, $newScore);
            $this->_strategies->sort();
        }
    }

    /**
     * Reject the last prediction, decreasing the weight of the strategy used
     */
    public function rejectPrediction()
    {
        if (isset($this->_lastStrategyUsed)) {
            $score = $this->_strategies->weight($this->_lastStrategyUsed);
            $newScore = $score * 0.9;
            $this->_strategies->weight($this->_lastStrategyUsed, $newScore);
            $this->_strategies->sort();
        }
    }

    /**
     * Register a listener for prediction events
     *
     * @param callable $listener The listener function
     */
    public function onPrediction(callable $listener)
    {
        $this->behavior(self::PREDICTION_EVENT, $listener);
    }

    /**
     * Get the data type of the input
     *
     * @param mixed $input The input data
     * @return string|null The detected data type
     */
    private function getType($input): ?string
    {
        return InputTypeDetector::detect($input);
    }

    /**
     * Calculate a score for a strategy based on its accuracy and execution time
     *
     * @param float $accuracy The accuracy of the strategy
     * @param float $executionTime The execution time of the strategy
     * @return float The calculated score
     */
    protected function calculateScore(float $accuracy, float $executionTime): float
    {
        return $accuracy / (1 + $executionTime);
    }
}
