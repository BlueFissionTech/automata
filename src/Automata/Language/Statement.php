<?php
namespace BlueFission\Automata\Language;

// http://ogden.basic-english.org/verbs.html

use BlueFission\DevObject;

/*
The intended meanings for vocalizations were grouped into six main categories: animate entities (child, man, woman, tiger, snake, deer), inanimate entities (knife, fire, rock, water, meat, fruit), actions (gather, cook, hide, cut, pound, hunt, eat, sleep), properties (dull, sharp, big, small, good, bad), quantifiers (one, many) and demonstratives (this, that).

https://www.livescience.com/iconic-vocalizations-lead-to-human-languages.html
*/

class Statement extends DevObject {
	protected $_data = array(
		'type'=>1,
		'context'=>'',
		'priority'=>0,
		'subject'=>'',
		'modality'=>'',
		'behavior'=>'',
		'condition'=>'',
		'object'=>'',
		'relationship'=>'',
		'indirect_object'=>'',
		'position'=>''
	);

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
		$entities = array_merge($this->subject, $this->object, $this->indirect_object );
		return $entities;
	}
}