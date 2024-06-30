<?php
namespace BlueFission\Bot\Comprehension;

class Entity {

	$_name;
	$_description;
	$_meta;

	public function __construct( $name, $description = null, $meta = null ) {
		$this->_name = $name;
		$this->_description = $description;
		$this->_meta = $meta;
	}
}
