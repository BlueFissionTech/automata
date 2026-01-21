<?php
namespace BlueFission\Automata;

use BlueFission\Automata\Intelligence;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Sensory\Sense;
use BlueFission\DevElation as Dev;

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
	protected $_level;

	private $_is_running = false;

	protected $_biases;
	protected $_scene;

	protected $_permissions;
	protected ?Sense $_sense = null;

	protected $_inputs;
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
		$this->_strategies = $this->_strategies ?? new OrganizedCollection();

		$this->_biases = new OrganizedCollection();
		$this->_inputs = new OrganizedCollection();
		$this->_permissions = [];
		$this->_scene = null;
		$this->_level = 1;
		$this->_avgtime = 0.0;

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
		$input = Dev::apply('automata.engine.classify.1', $input);
		$result = $input;

		if ( $this->_scene && method_exists($this->_scene, 'has') ) {
			if ( !$this->_scene->has($input) && method_exists($this->_scene, 'add') ) {
				$this->_scene->add($input);
			}
		}

		$strategies = $this->_strategies instanceof OrganizedCollection ? $this->_strategies->toArray() : [];
		foreach ( $strategies as $name => $meta ) {
			$strategy = $meta['value'] ?? $meta;
			if ( !is_object($strategy) ) {
				continue;
			}

			$this->startclock();
			if ( method_exists($strategy, 'process') ) {
				$strategy->process($input);
			}

			$guess = null;
			if ( method_exists($strategy, 'guess') ) {
				$guess = $strategy->guess();
			} elseif ( method_exists($strategy, 'predict') ) {
				$guess = $strategy->predict($input);
			}

			$this->stopclock();

			if ( $guess !== null ) {
				$result = $guess;
			}

			Dev::do('automata.engine.classify.action1', [
				'strategy' => is_string($name) ? $name : get_class($strategy),
				'input' => $input,
				'output' => $result,
				'executionTime' => $this->time(),
			]);

			if ( $result ) {
				break;
			}
		}

		return Dev::apply('automata.engine.classify.2', $result);
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
		$this->_starttime = function_exists('getrusage') ? getrusage() : microtime(true);
	}

	protected function stopclock() {
		if ( function_exists('getrusage') && is_array($this->_starttime) ) {
			$this->_stoptime = getrusage();
			$ru = $this->_starttime;
			$rus = $this->_stoptime;
			$this->_totaltime = ($rus["ru_utime.tv_sec"]*1000 + intval($rus["ru_utime.tv_usec"]/1000))
				- ($ru["ru_utime.tv_sec"]*1000 + intval($ru["ru_utime.tv_usec"]/1000));
		} else {
			$this->_stoptime = microtime(true);
			$start = is_numeric($this->_starttime) ? $this->_starttime : $this->_stoptime;
			$this->_totaltime = ($this->_stoptime - $start);
		}

		if ( !is_numeric($this->_avgtime) || $this->_avgtime <= 0 ) {
			$this->_avgtime = $this->_totaltime;
		} else {
			$this->_avgtime = ($this->_avgtime + $this->_totaltime) / 2;
		}
	}

	public function time() {
		return $this->_totaltime ?? 0;
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
			$event->context = array('service'=>$recipientName, 'behavior'=>$behavior);
			$this->dispatch($event);
		}
	}

	public function setSense(Sense $sense): void
	{
		$this->_sense = $sense;
	}

	public function analyzeWithAttention($input, array $options = []): array
	{
		$sense = $this->_sense ?? new Sense($this);
		$data = $sense->invoke($input);

		$attentionScore = $sense->attentionScore();
		$options['attention_score'] = $options['attention_score'] ?? $attentionScore;

		$report = $this->analyze($input, $options);
		$report['attention'] = $this->buildAttentionProfile($sense, $data, $attentionScore);

		return $report;
	}

	protected function buildAttentionProfile(Sense $sense, $data, float $score): array
	{
		$stats = [];
		if (is_array($data)) {
			$stats = array_intersect_key($data, array_flip([
				'count',
				'mean1',
				'variance1',
				'std1',
			]));
		}

		return [
			'score' => $score,
			'stats' => $stats,
			'state' => $sense->attentionState(),
		];
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
		if ( !$strategy ) {
			return $this;
		}

		if ( is_string($strategy) && !class_exists($strategy) ) {
			return $this;
		}

		$strategyName = $name.'_strategy';
		$instance = $strategy;
		if ( is_string($strategy) ) {
			$instance = new $strategy();
		}
		if ( !is_object($instance) ) {
			return $this;
		}

		$this->_strategies->add($instance, $strategyName);

		return $this;
	}

	public function addInput( $name, $processor = null )
	{
		$senseName = $name.'_sense';
		$inputName = $name.'_input';

		$input = new \BlueFission\Automata\Sensory\Input($processor);
		$input->name($inputName);

		$sense = new \BlueFission\Automata\Sensory\Sense($this);

		$this->_inputs->add($input, $inputName);
		$this->_inputs->add($sense, $senseName);

		$input->when(Event::COMPLETE, function ($behavior, $args = null) use ($sense) {
			$sense->invoke($behavior->context);
		});

		$sense->when('DoEnhance', function ($behavior, $args = null) {
			$this->dispatch('OnEnhance', $behavior->context);
		});

		return $this;
	}

	public function addFrame( $frame ) {
		foreach ( $this->_strategies as $strategy ) {
			$this->service($strategy, 'train', $frame );
		}
	}

	public function queueInput( $behavior ) {
		$this->classify($behavior->context);
		// Queue::enqueue( $behavior->target->name(), $behavior->context );
	}

	public function strategy($name, $class) {
		return $this->addProcessor($name, $class);
	}

	public function __destruct()
	{
		parent::__destruct();
		$this->dispatch('OnComplete');
	}
}
