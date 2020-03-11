<?php
namespace BlueFission\Bot\NaturalLanguage;

// http://ogden.basic-english.org/verbs.html

use BlueFission\DevObject;

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