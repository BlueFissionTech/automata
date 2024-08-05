<?php
namespace BlueFission\Automata\Language;

// http://ogden.basic-english.org/verbs.html

use BlueFission\Obj;
use BlueFission\Arr;

/*
The intended meanings for vocalizations were grouped into six main categories: animate entities (child, man, woman, tiger, snake, deer), inanimate entities (knife, fire, rock, water, meat, fruit), actions (gather, cook, hide, cut, pound, hunt, eat, sleep), properties (dull, sharp, big, small, good, bad), quantifiers (one, many) and demonstratives (this, that).

https://www.livescience.com/iconic-vocalizations-lead-to-human-languages.html
*/

class Statement extends Obj {
	protected $_data = [
		'type'=>1, // interogative, imperative, declarative
		'context'=>'',
		'priority'=>0,
		'subject'=>'',
		'negation'=>true, // figure out a better word for this later
		'modality'=>'',
		'behavior'=>'',
		'condition'=>'',
		'object'=>'',
		'relationship'=>'',
		'indirect_object'=>'',
		'position'=>''
	];

	public function field( string $var, $value = null ) {
		if ( $value ) {
			
		}
		
		return parent::field( $var, $value);
	}

	public function percentSatisfied() {
		$parts = Arr::size($this->_data);
		$satisfied = 0;
		foreach ( $this->_data as $part=>$value ) {
			if ( $value ) {
				$satisfied++;
			}
		}
		return $satisfied / $parts;
	}

	public function satisfy() {
		foreach ( $this->_data as $part=>$value ) {
			if ( !$value ) {
				return "{$part}";
				// return "? {$part}".PHP_EOL;
			}
			return null;
		}
	}

	public function entities() {
		$entities = Arr::merge($this->subject, $this->object, $this->indirect_object );
		return $entities;
	}
}