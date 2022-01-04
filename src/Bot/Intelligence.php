<?php

namespace BlueFission\Bot;

use BlueFission\Data\Queues\Queue as Queue;

use BlueFission\Bot\Collections\OrganizedCollection as Collection;
use BlueFission\Bot\Behaviors\OrganizedBehaviorCollection as BehaviorCollection;
use BlueFission\Bot\Behaviors\OrganizedHandlerCollection as HandlerCollection;
use BlueFission\Bot\Sensory\Input;
use BlueFission\Bot\Sensory\Sense;
use BlueFission\Services\Service;
use BlueFission\Behavioral\Behaviors\Event;

class Intelligence extends Service {

	const TRANSACTION_MULTIPLIER = 10;
	const TRANSACTION_BASE_SIZE = 1;
	
	private $_score = 0;
	private $_transaction_size;
	private $_level;

	protected $_strategies;
	protected $_memory;

	protected $_biases;
	protected $_scene;

	protected $_inputs;
	protected $_senses;

	protected $_starttime;
	protected $_stoptime;
	protected $_totaltime;

	public function __construct() 
	{
		parent::__construct();
		$this->_services = new Collection();
		$this->_routes = new Collection();
		$this->_strategies = new Collection();
		$this->_behaviors = new BehaviorCollection();
		$this->_handlers = new HandlerCollection();
	}

	public function classify( $input ) {
		$result = $input;
		if ( $this->_scene->has($source) ) {
			$this->_scene->add($source);
		} else {
			foreach ( $this->_strategies as $strategy ) {
				$this->startclock();
				$strategy->process($input);
				$this->stopclock();

				$time = $this->time();
				$result = $strategy->guess();
				var_dump($strategy->score());
				if ($result) {
					break;
				}
			}
		}

		return $result;
	}

	public function read( $data )
	{
		foreach ($this->_inputs as $input) {
			$classify = $input->test($data);
			if ( $classify ) {
				$input->scan($data);
			}
		}
	}

	public function input($name, $processor = null) {
		// $sense = $name.'_sense';
		// $input = $name.'_input';

		// $this
		// 	->delegate($input, '\BlueFission\Intelligence\Input', $processor )
		// 	->delegate($sense, '\BlueFission\Intelligence\Sense', $this)
			
		// 	->register($input, 'url', 'scan' )
		// 	->register($sense, 'DoProcess', 'invoke')
		// 	->register($this->name(), 'DoQueueInput', 'queueInput')
		// 	->register($this->name(), 'DoTraining', 'addFrame')

		// 	->route($input, $sense, 'OnComplete', 'DoProcess')
		// 	->route($sense, $this->name(), 'OnSweep', 'DoTraining')
		// 	->route($sense, $this->name(), 'OnCapture', 'DoQueueInput')
		// ;

		// return $this;
		
		$this->_inputs[$name] = new Input( $processor );

        $app = \App::instance();
        $sense = new Sense( $app );
        $sense->behavior(Event::SUCCESS, [$this, 'capture']);

        $this->_senses[$name] = $sense;

        $this->_inputs[$name]->behavior(Event::COMPLETE, function( $behavior ) use ($sense) {
            $sense->invoke($behavior->_context);
        });
	}

	public function capture( $behavior, $data )
	{
		die(var_dump($data));
	}

	// public function addFrame( $frame ) {
	// 	foreach ( $this->_strategies as $strategy ) {
	// 		$this->service($strategy, 'train', $frame );
	// 	}
	// }

	public function strategy($name, $class) {
		$strategy = $name.'_strategy';
		// $this->delegate($strategy, $class);

		$this->_strategies->add(new $class, $strategy);

		return $this;
	}

	public function queueInput( $behavior ) {
		$this->classify($behavior->_context);
		// Queue::enqueue( $behavior->_target->name(), $behavior->_context );
	}

	protected function startclock() {
		if ( function_exists('getrusage')) {
			$this->_starttime = getrusage();
		} else {
			$this->_starttime = microtime(true);
		}
	}

	protected function stopclock() {
		if ( function_exists('getrusage')) {
			$this->_stoptime = getrusage();
			$this->_totaltime = ($ru["ru_utime.tv_sec"]*1000 + intval($ru["ru_utime.tv_usec"]/1000)) - ($rus["ru_utime.tv_sec"]*1000 + intval($rus["ru_utime.tv_usec"]/1000));
		} else {
			$this->_stopttime = microtime(true);
			$this->_totaltime = ($time_end - $time_start);
		}
	}

	public function time() {
		return $this->_totaltime;
	}
}