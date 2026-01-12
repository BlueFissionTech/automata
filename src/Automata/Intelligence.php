<?php

namespace BlueFission\Automata;

use BlueFission\Obj;
use BlueFission\DevElation as Dev;
use BlueFission\DevElation;
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
    protected ?IStrategy $_lastStrategyUsed = null; // Last strategy used for prediction
    private ?string $_lastStrategyName = null; // Key/name of last strategy used
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
        // Allow filters to adjust training data or inject instrumentation.
        $dataset = Dev::apply('automata.intelligence.train.1', $dataset);
        $labels  = Dev::apply('automata.intelligence.train.2', $labels);

        foreach ($this->_strategies->toArray() as $name => $meta) {
            /** @var IStrategy $strategy */
            $strategy = $meta['value'];

            $executionTime = $this->_benchmarkService->benchmarkTraining($strategy, $dataset, $labels);
            $accuracy = $strategy->accuracy();
            $score = $this->calculateScore($accuracy, $executionTime);

            $this->_strategies->weight($name, $score);

            // Hook per-strategy training metrics.
            Dev::do('automata.intelligence.train.action1', [
                'strategy'      => $name,
                'accuracy'      => $accuracy,
                'executionTime' => $executionTime,
            ]);
        }

        // Reorder strategies so that the highest scoring strategy is preferred.
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
        // Pre-prediction input filter.
        $input = Dev::apply('automata.intelligence.predict.1', $input);

        $strategies = $this->_strategies->toArray();
        if (empty($strategies)) {
            return null;
        }

        // Select the strategy with the highest weight.
        $bestName = null;
        $bestMeta = null;

        foreach ($strategies as $name => $meta) {
            if (!isset($bestMeta) || $meta['weight'] > $bestMeta['weight']) {
                $bestMeta = $meta;
                $bestName = $name;
            }
        }

        /** @var IStrategy $bestStrategy */
        $bestStrategy = $bestMeta['value'];

        $this->_lastStrategyUsed = $bestStrategy;
        $this->_lastStrategyName = $bestName;

        $output = $bestStrategy->predict($input);

        // Post-prediction filter and action hook.
        $output = Dev::apply('automata.intelligence.predict.2', $output);
        Dev::do('automata.intelligence.predict.action1', [
            'strategy' => $bestName,
            'input'    => $input,
            'output'   => $output,
        ]);

        return $output;
    }

    /**
     * Approve the last prediction, increasing the weight of the strategy used
     */
    public function approvePrediction()
    {
        if ($this->_lastStrategyName !== null && $this->_strategies->has($this->_lastStrategyName)) {
            $score = $this->_strategies->weight($this->_lastStrategyName);
            $newScore = $score * 1.1;
            $this->_strategies->weight($this->_lastStrategyName, $newScore);
            $this->_strategies->sort();
        }
    }

    /**
     * Reject the last prediction, decreasing the weight of the strategy used
     */
    public function rejectPrediction()
    {
        if ($this->_lastStrategyName !== null && $this->_strategies->has($this->_lastStrategyName)) {
            $score = $this->_strategies->weight($this->_lastStrategyName);
            $newScore = $score * 0.9;
            $this->_strategies->weight($this->_lastStrategyName, $newScore);
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
