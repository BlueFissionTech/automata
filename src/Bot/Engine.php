<?php
namespace BlueFission\Bot;

use BlueFission\Bot\Intelligence;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Bot\Collections\OrganizedCollection;

use BlueFission\Data\Queues\Queue as Queue;

/*
 * Vector
 * Tensor
 * Hedron
 * Choron
 * Sphere
 */

// Drives
/* 
	Security - stats
	Potential - scanning/testing resource gathering
	Desire - preference/personality
	Utility - service
	Expression - creation
	Insight - insight
	Consciousness
*/

// Efficiency = 1 - output / input ?
// Score = ( total transactions / correct transactions ) * ( total transactions / total successful transactions ) * avg transaction speed

// Conversational implicature = what is said versus what is implied (grice)
// Cooperate with most likely meaning from interpretation
// Speaker rules: quanity of information, quality of information, relation in information cohesion, manner of culture? (non-obscure, non-ambiguous, brief and orderly)
// Flout a maxim to draw attention to a point or intention
// Determine speaker vs audience meaning/attitude

// Platonic solid math for transaction size

// http://image-net.org/
// https://secure.php.net/manual/en/imagick.convolveimage.php

class Engine extends Intelligence implements ISphere {

	const TRANSACTION_MULTIPLIER = 10;
	const TRANSACTION_BASE_SIZE = 1;

	// protected $_config = array(
	// 	'level'=>1,
	// 	'biases'=>[],
	// 	'name'=>''
	// );

	private $_score = 0;
	private $_transaction_size;
	// private $_level;

	private $_is_running = false;

	protected $_biases;
	protected $_scene;

	protected $_permissions;

	// protected $_inputs;
	protected $_strategies;
	protected $_memory;
	// protected $_outputs;

	protected $_starttime;
	protected $_stoptime;
	protected $_totaltime;
	protected $_avgtime;

	protected static $_class = __CLASS__;

	public function __construct() {
		parent::__construct();

		$this->_services = new OrganizedCollection(); // Redefined to organize

		$this->_biases = new OrganizedCollection();

		// Not sure if I need these
		// $this->_scene = new Holoscene();
		// $this->_strategies = new OrganizedCollection();
	}

	static function instance()
	{
		if (!isset(self::$_instance)) {
			self::$_instance = new self::$_class;
		}

		return self::$_instance;
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
				echo $strategy->score()."\n";
				if ($result) {
					break;
				}
			}
		}

		return $result;
	}

	// Method probably not necessary
	// public function react() {
	// 	$this->perform();
	// }

	public function getTransactionSize() {
		$this->_transaction_size = pow(self::TRANSACTION_BASE_SIZE * $this->_level, self::TRANSACTION_MULTIPLIER);
		return $this->_transaction_size;
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
			$this->_stoptime = microtime(true);
			$this->_totaltime = ($time_end - $time_start);
		}

	}

	public function time() {
		return $this->_totaltime;
	}

	public function stats() {
		$stats = array( 
			'score'=>$this->_score,
			'avgtime'=>$this->_avgtime,
			'depth'=>$this->_level
		);

		return $stats;
	}

	protected function message( $recipientName, $behavior, $arguments = null, $callback = null )
	{
		if ( $this->isPermitted($recipientName, $behavior->name()) ) {
			parent::message( $recipientName, $behavior, $arguments = null, $callback = null );
		} else {
			// $this->confirm($recipientName, $behavior);
			$event = new Action('DoConfirm');
			$event->_context = array('service'=>$recipientName, 'behavior'=>$behavior);
			$this->dispatch($event);
		}
	}

	protected function isPermitted($service, $behaviorName)
	{
		return (isset($this->_permissions[$service]) && isset($this->_permissions[$service][$behaviorName]) && false !== $this->_permissions[$service][$behaviorName]);
	}

	protected function permit($service, $behaviorName, $permission)
	{
		$this->_permissions[$service][$behaviorName] = $permission;
	}

	public function addProcessor( $name, $strategy )
	{
		$strategy = $name.'_strategy';
		$this->delegate($strategy, $class);

		$this->_strategies->add($strategy, $strategy);

		return $this;
	}

	public function addInput( $name, $processor = null )
	{
		$sense = $name.'_sense';
		$input = $name.'_input';

		$this
			->delegate($input, '\BlueFission\Bot\Sensory\Input', $processor )
			->delegate($sense, '\BlueFission\Bot\Sensory\Sense', $this)

			->register($input, 'input', 'scan' )
			->register($sense, 'DoProcess', 'invoke')

			// ->register($this->name(), 'DoQueueInput', 'queueInput')
			// ->register($this->name(), 'DoTraining', 'addFrame')

			->route($input, $sense, 'OnComplete', 'DoProcess')
			->route($sense, $this->name(), 'DoEnhance', 'OnEnhance')
			// ->route($sense, $this->name(), 'OnCapture', 'DoQueueInput')
		;

		return $this;
	}

	public function addFrame( $frame ) {
		foreach ( $this->_strategies as $strategy ) {
			$this->service($strategy, 'train', $frame );
		}
	}

	public function queueInput( $behavior ) {
		$this->classify($behavior->_context);
		// Queue::enqueue( $behavior->_target->name(), $behavior->_context );
	}

	public function strategy($name, $class) {
		$strategy = $name.'_strategy';
		$this->delegate($strategy, $class);

		$this->_strategies->add($strategy, $strategy);

		return $this;
	}

	public function __destruct()
	{
		parent::__destruct();
		$this->dispatch('OnComplete');
	}
}