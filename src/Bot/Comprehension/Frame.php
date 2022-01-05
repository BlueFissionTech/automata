<?php
namespace BlueFission\Bot\Comprehension;

class Frame {
	private $_experiences;

	public function construct()
	{
		$this->_experiences = [];
		// $this->_experiences = Collection();
	}

	public function addExperience( $experience, $source = null ) 
	{
		$this->_experiences[$source] = $experience;
		// die(var_dump($this->_experiences));
	}

	public function extract() {
		$values = [];
		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}
		return $values;
	}
}