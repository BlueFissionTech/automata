<?php

namespace BlueFission\Bot\NaturalLanguage;

class Walker {	

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
			$query = $statement->satisy();
		}
	}

	public function process( )
	{
		$properties = array(
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
		// $stack = []
		foreach ($this->_statements as $statement) {
			$continue = true;
			while ( $statement->satisfy() && $continue ) {
				  
			}
			foreach ($statement->entities() as $entity) {
				$label = $this->_getLabel($entity, 'entity');
				$this->_entities[$label] = $entity;
			}
			foreach ($properties as $property) {
				$label = $this->_getLabel($statement->$property, $property);
				switch ( $property ) {
					case "type":

					break;
					case "subject":
					case "object":
					case "indirect_object":
						$current = $this->_entities[$label];
					break;
					case "modality":

					break;
					case "behavior":
						$this->operate($current, $label);
					break;
					case "condition":

					break;
					case "relationship":

					break;
					case "position":

					break;
				}
				$statement->$property;
			}
			// $this->_stack[] = $statement;

		}
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

	private function _getLabel($property, $class = null) {

	}

	public function traverse( $tree ) {
		foreach ( $tree as $node ) {
			// TODO: Move "runtime" class code to walker class
			$statement = $runtime = new Runtime( $node );
			// $statement = new Statement();
			$this->addStatement($statement);
		}
	}
}