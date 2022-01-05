<?php
namespace BlueFission\Bot\Sensory;

use BlueFission\Behavioral\Dispatcher;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Bot\Collections\OrganizedCollection;

class InputArray extends Dispatcher {
	
	const TEXTUAL = 'textual';
	const VISUAL = 'visual';
	const AUDITORY = 'auditory';
	const CINEMATIC = 'cinematic';

	private $_inputs;

	public function __construct() {

		parent::__construct();

		$this->_inputs = new OrganizedCollection();
	}

	public function create( $label, $preprocessors = [] )
	{
		$input = new Input();
		foreach ($preprocessors as $preprocessor) {
			$input->setPreprocessor( $preprocessor );
		}
		$input->behavior(Event::COMPLETE, [$this, 'onInputComplete']);

		$this->_inputs[$label] = $input;
	}

	public function observe( $data, $type = null )
	{
		$type = $type ?? $this->detect($data);

		switch( $type ) {
			default:
			case self::TEXTUAL:
				$this->_inputs->get(self::TEXTUAL)->scan($data);

				// $this->createImageFromText($data);
			break;
			case self::VISUAL:
				$this->_inputs->get(self::VISUAL)->scan($data);
			break;
			case self::AUDITORY:
				$this->_inputs->get(self::AUDITORY)->scan($data);
			break;
			case self::CINEMATIC:
				$this->_inputs->get(self::CINEMATIC)->scan($data);
			break;
		}
	}
	public function detect( $data )
	{
		return self::TEXTUAL;
	}

	public function onInputComplete( $behavior )
	{
		$this->dispatch($behavior);
	}
}