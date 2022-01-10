<?php
namespace BlueFission\Bot\Comprehension;

use BlueFission\Bot\Collections\OrganizedCollection;

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

	public function extract() 
	{
		$values = [];
		$aggregate = [];
		// die(var_dump($this->_experiences));
		// var_dump($this->_experiences);

		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}


		foreach ( $values as $data ) {
			$i = 0;
			foreach ($data as $key=>$datum) {
				// $aggregate->add($datum, $key);
				$aggregate[$key] = $datum;
				$i++;
				if ( $i >= 5) {
					break;
				}
			}
		}
		return $aggregate;
	}

	public function hashArray() 
	{
		$values = [];

		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}

		// $aggregate = new OrganizedCollection();
		$hashes = [];

		foreach ( $values as $data ) {
			$i = 0;
			$data;
			foreach ($data as $key=>$datum) {
				// var_dump($dat);
				// die(var_dump( $datum ));
				// $hashes->add($datum, $key);
				$hashes[] = $datum;
				$i++;
				if ( $i >= 5) {
					break;
				}
			}

			for ( $i; $i < 5; $i++ ) {
				$hashes[] = 0;
			}
		}

		if ( count($hashes) < 30 ) {
			for ( $i = count($hashes)-1; $i < 30; $i++ ) {
				$hashes[] = 0;
			}
		}

		return $hashes;
	}
}