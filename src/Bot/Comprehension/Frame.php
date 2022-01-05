<?php
namespace BlueFission\Bot\Comprehension;

class Frame {
	private $_experiences;

	public function construct()
	{
		$this->_experiences = Collection();
	}

	public function addExperience( $experience, $source = null ) 
	{
		$this->_experiences[$source] = $experience;
	}

	public function extract() {
		$values = [];
		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}
		return $values;
	}
}