<?php
namespace BlueFission\Bot\Comprehension;

use BlueFission\Bot\Collections\OrganizedCollection;

class Frame {
	private $_experiences = [];

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

	public function process()
	{
		$values = [];
		// die(var_dump($this->_experiences));

		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}

		$aggregate = new OrganizedCollection();
		$aggregate->autoSort(false);
		// $aggregate = [];

		foreach ( $values as $data ) {
			foreach ($data as $key=>$datum) {
				$aggregate->add($datum['value'], $key, $datum['weight']);
				// $aggregate[$key] = $datum;
			}
		}
		$aggregate->sort();
		$aggregate->stats();
		return $aggregate->data()['values'];
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
			// var_dump($experience['values']);
			$values[] = $experience['values'] ?? [];
		}

		// $aggregate = new OrganizedCollection();
		$hashes = [];

		$max = 20;
		foreach ( $values as $data ) {
			$i = 0;
			foreach ($data as $key=>$datum) {
				// var_dump($dat);
				// die(var_dump( $datum ));
				// $hashes->add($datum, $key);
				$hashes[] = crc32($datum['value']) * .0000000001;
				$i++;
				if ( $i >= $max) {
					$max = 5;
					break;
				}
			}

			for ( $i; $i < $max; $i++ ) {
				$hashes[] = 0;
			}
			$max = 5;
		}

		if ( count($hashes) > 0 && count($hashes) < 54 ) {
			for ( $i = count($hashes)-1; $i < 54; $i++ ) {
				$hashes[] = 0;
			}
		}

		return $hashes;
	}
}