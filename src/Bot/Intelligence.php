<?php

namespace BlueFission\Bot;

use BlueFission\DevObject;
use BlueFission\Bot\Collections\OrganizedCollection;
use BlueFission\Bot\Sensory\Input;
use BlueFission\Bot\Strategies\IStrategy;
use BlueFission\Bot\Sensory\Sense;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;
use BlueFission\Behavioral\Behaviors\Handler;

class Intelligence extends DevObject
{
	use Dispatches;

    protected OrganizedCollection $_strategies;
    protected float $_minThreshold;
    protected $_starttime;
    protected $_stoptime;
    protected $_totaltime;
    protected IStrategy $_lastStrategyUsed;

    private array $_strategyGroups;

    const PREDICTION_EVENT = 'prediction_event';

    public function __construct($minThreshold = 0.8)
    {
        $this->_strategies = new OrganizedCollection();
        $this->_minThreshold = $minThreshold;
        $this->_strategyGroups = [];
		parent::__construct();
    }

    public function registerStrategy(IStrategy $strategy, string $name)
    {
        $this->_strategies->add($strategy, $name);
    }

    public function registerStrategyGroup(DataGroup $group)
    {
        $this->_strategyGroups[$group->getName()] = $group;
    }

    public function registerInput(Input $input)
    {
        $input->on(Event::COMPLETE, function (Behavior $event) {
            $data = $event->_context;
            foreach ($this->_strategies as $strategy) {
                return $strategy->predict($data);
            }
            // $this->_strategies->sort();
        });
    }

    public function scan($input)
	{
	    $dataType = $this->getType($input);
	    if ($dataType && isset($this->_strategyGroups[$dataType])) {
	        $group = $this->_strategyGroups[$dataType];
	        $strategies = $group->getStrategies();

	        // Iterate through strategies and use them
	        foreach ($strategies as $strategy) {
	            $output = $strategy->predict($input);
	            $this->dispatch(self::PREDICTION_EVENT, [
	                'strategy' => get_class($strategy),
	                'output' => $output,
	                'type' => $dataType,
	            ]);
	        }
	    }
	}


    public function train(array $dataset, array $labels)
    {
        foreach ($this->_strategies as $strategy) {
            $this->startclock();
            $strategy->train($dataset, $labels);
            $accuracy = $strategy->accuracy();
            $this->stopclock();
            $executionTime = $this->_totaltime;

            $score = $this->calculateScore($accuracy, $executionTime);
            $this->_strategies->weight($strategy, $score);

            if ($accuracy >= $this->_minThreshold) {
                break;
            }
        }

        $this->_strategies->sort();
    }

    public function predict($input)
    {
        $bestStrategy = $this->_strategies->first();
        $this->_lastStrategyUsed = $bestStrategy;
        return $bestStrategy->predict($input);
    }

    public function approvePrediction()
    {
        if (isset($this->_lastStrategyUsed)) {
            $score = $this->_strategies->weight($this->_lastStrategyUsed);
            $newScore = $score * 1.1;
            $this->_strategies->weight($this->_lastStrategyUsed, $newScore);
            $this->_strategies->sort();
        }
    }

    public function rejectPrediction()
    {
        if (isset($this->_lastStrategyUsed)) {
            $score = $this->_strategies->weight($this->_lastStrategyUsed);
            $newScore = $score * 0.9;
            $this->_strategies->weight($this->_lastStrategyUsed, $newScore);
            $this->_strategies->sort();
        }
    }

    public function onPrediction(callable $listener)
    {
        $this->behavior(self::PREDICTION_EVENT, $listener);
    }

    private function getType($input): ?string
    {
        return InputTypeDetector::detect($input);
    }

    protected function calculateScore(float $accuracy, float $executionTime): float
    {
        return $accuracy / (1 + $executionTime);
    }

    protected function startclock()
    {
        if (function_exists('getrusage')) {
            $this->_starttime = getrusage();
        } else {
            $this->_starttime = microtime(true);
        }
    }

    protected function stopclock()
    {
        if (function_exists('getrusage')) {
            $this->_stoptime = getrusage();
            $this->_totaltime = ($this->_stoptime["ru_utime.tv_sec"] * 1000 + intval($this->_stoptime["ru_utime.tv_usec"] / 1000)) - ($this->_starttime["ru_utime.tv_sec"] * 1000 + intval($this->_starttime["ru_utime.tv_usec"] / 1000));
        } else {
            $this->_stopttime = microtime(true);
            $this->_totaltime = ($this->_stopttime - $this->_starttime);
        }
    }
}