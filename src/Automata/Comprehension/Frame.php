<?php
namespace BlueFission\Automata\Comprehension;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class Frame {
	private $_experiences = [];

	public function construct()
	{
		$this->_experiences = [];
		// $this->_experiences = Collection();
	}

	public function addExperience( $experience, $source = null ) 
	{
        $experience = Dev::apply('comprehension.frame.experience', $experience);
		$this->_experiences[$source] = $experience;
        Dev::do('comprehension.frame.experience_added', ['source' => $source, 'experience' => $experience]);
	}

	public function process()
	{
        Dev::do('comprehension.frame.process_start', ['experiences' => $this->_experiences]);
		$values = [];

		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}

		$aggregate = new OrganizedCollection();
		$aggregate->autoSort(false);

		foreach ( $values as $data ) {
			foreach ($data as $key=>$datum) {
				$aggregate->add($datum['value'], $key, $datum['weight']);
			}
		}
		$aggregate->sort();
		$aggregate->stats();
		$result = $aggregate->data()['values'];
        Dev::do('comprehension.frame.process_complete', ['result' => $result]);
		return Dev::apply('comprehension.frame.process_result', $result);
	}

	public function extract() 
	{
        Dev::do('comprehension.frame.extract_start', ['experiences' => $this->_experiences]);
		$values = [];
		$aggregate = [];

		foreach ( $this->_experiences as $experience ) { 
			$values[] = $experience['values'] ?? [];
		}


		foreach ( $values as $data ) {
			$i = 0;
			foreach ($data as $key=>$datum) {
				$aggregate[$key] = $datum;
				$i++;
				if ( $i >= 5) {
					break;
				}
			}
		}
        Dev::do('comprehension.frame.extracted', ['aggregate' => $aggregate]);
        return Dev::apply('comprehension.frame.extract_result', $aggregate);
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
