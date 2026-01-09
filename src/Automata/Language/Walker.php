<?php

namespace BlueFission\Automata\Language;

class Walker {

	protected $_statements = [];

	/**
	 * @var array<int,array<string,mixed>> Collected semantic actions
	 */
	protected $_log = [];

	public function addStatement( $statement )
	{
		$this->_statements[] = $statement;
		// foreach ($statement->entities() as $entity) {
		// 	$this->_entities[] = $entity;
		// }
	}

	public function assume()
	{
		foreach ($this->_statements as $statement) {
			$query = $statement->satisfy();
		}
	}

	public function process( )
	{
		$this->_log = [];

		foreach ($this->_statements as $statement) {
			if (!is_object($statement)) {
				continue;
			}

			$entry = [
				'type'            => method_exists($statement, 'field') ? $statement->field('type') : null,
				'context'         => method_exists($statement, 'field') ? $statement->field('context') : null,
				'priority'        => method_exists($statement, 'field') ? $statement->field('priority') : null,
				'subject'         => method_exists($statement, 'field') ? $statement->field('subject') : null,
				'modality'        => method_exists($statement, 'field') ? $statement->field('modality') : null,
				'behavior'        => method_exists($statement, 'field') ? $statement->field('behavior') : null,
				'condition'       => method_exists($statement, 'field') ? $statement->field('condition') : null,
				'object'          => method_exists($statement, 'field') ? $statement->field('object') : null,
				'relationship'    => method_exists($statement, 'field') ? $statement->field('relationship') : null,
				'indirect_object' => method_exists($statement, 'field') ? $statement->field('indirect_object') : null,
				'position'        => method_exists($statement, 'field') ? $statement->field('position') : null,
				'satisfied'       => method_exists($statement, 'percentSatisfied') ? $statement->percentSatisfied() : null,
			];

			$this->_log[] = $entry;
		}
	}

	/**
	 * Return the collected semantic action log. Each entry is a
	 * snapshot of a Statement's core roles (subject, behavior,
	 * object, etc.) and its satisfaction ratio.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function log(): array
	{
		return $this->_log;
	}

	private function _apply($subject, $object, $property, $name) {
		if ( is_scalar($name) && !is_numeric($name) ) {
			$subject->$name = $property;
		} else {
			$subject->property = true;
		}	
	}

	// private function _operate($subject, $operation, $object = null, $indirect_object = null) {
	private function _operate($node) {
		// die();
		// echo "Count: ".count($this->_tree);
		// var_dump($this->_tree);
		// return;
		foreach ($node as $branch=>$fork) {
			if ( is_array($fork)) {
				echo "$branch:\n";
				foreach ($fork as $property=>$attr) {
					if ( is_array($attr) ) {
						echo "\t$property: \n";
						foreach ( $attr as $key=>$val ) {
							echo "\t\t$key: $val\n";
						}
					} else {
						echo "\t$property: ". ( ( is_array($attr) ) ? implode(', ', $attr) : $attr);
						echo "\n";
					}
					// var_dump($attr);
				}
			} else {
				echo "$branch: $fork\n";
			}
		}
		return;

		// $type = 

		// get type of command
		// run operations

		switch ( $operation ) {
			case 'LIKE':

				
				foreach ($subject as $name=>$property) {
					$this->_apply($subject, $object, $property, $name);
				}
			break;
			case 'DOES':
				foreach ($object as $name=>$property) {
					if ( is_scalar($name) && !is_numeric($name) ) {
						$object->$name = $property;
					} else {
						$object->property = true;
					}
				}
			break;
			case 'WILL':
				foreach ($object as $name=>$property) {
					if ( is_scalar($name) && !is_numeric($name) ) {
						$object->$name = $property;
					} else {
						$object->property = true;
					}
				}
			break;
			case 'HANDLES':
				// foreach ($object as $name=>)
			break;
			case 'COMMITS':
			break;
			case 'QUERIES':
			break;
			case 'INTENDS':
			break;
		}
	}

	private function getLabel($property, $class = null) {

	}

	public function traverse( $tree ) {
		foreach ( $tree as $node ) {
			// Documenter currently stores Statement instances directly
			// in its tree. We simply record them here; richer runtime
			// behavior can be layered on top by consumers.
			$this->addStatement($node);
		}
	}
}
