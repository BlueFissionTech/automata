<?php

namespace BlueFission\Automata\Language;;

use Exception;

class Documenter {

	private $_statements = [];

	private $_entities = [];

	private $_definitions = [];

	private $_contexts = [];

	private $_stack = [];

	private $_tree = [];

	private $_nodes = -1;

	private $_entity;

	private $_expected = ['T_TYPE_INDICATOR'];

	private $_closing_stack = [];

	private $_command = '';

	private $_literal = '';

	private $_class;

	private $_reaction = null;


	private $_buffer = [];

	public function push( $cmd )
	{
		if ($this->_command == 'LITERAL') {
			$this->_literal .= $cmd['match'];
		}

		if ( $cmd['token'] == 'T_WHITESPACE' ) return;
		// echo $cmd['token'].':'.$cmd['match'].PHP_EOL;
		if ($this->_command == 'ESCAPE') {
			$this->_command = '';
			$this->_expected = [];

			return;
		}
		if ($cmd['token'] == 'T_ESCAPE') {
			$this->_command = 'ESCAPE';
		}

		if ($cmd['token'] == 'T_COLON') {
			$this->_command = 'ASSIGN';
		}

		if ( $cmd['token'] == 'T_EOL' ) {

			// if ( $this->_reaction == 'EVAL') {

			// } elseif ( $this->_reaction == 'ECHO') {
			// 	echo var_dump($this->retrieve());
			// 	$this->_reaction = null;
			// }
			return;
		}

		if ( $this->_expected && !in_array($cmd['token'], $this->_expected)) {
			throw new Exception("Unexpected {$cmd['token']} '{$cmd['match']}' on line, {$cmd['line']}. Expected ".implode(', ',$this->_expected), 1);
		}
		if ($cmd['token'] == 'T_TYPE_INDICATOR') {
			$this->_nodes++;
			
			if ($cmd['match'] == '?') {
				$this->_reaction = 'ASK';
			}

			if ($cmd['match'] == '!') {
				$this->_reaction = 'ECHO';
			}

			if ($cmd['match'] == '&') {
				$this->_reaction = 'EVAL';
			}
			
			$this->_tree[$this->_nodes]['type'] = $this->_reaction;//$cmd['match'];

			$this->_expected = [];
		}
		if (in_array($cmd['token'], ['T_ID','T_CLASS_OPEN_BRACKET'])) {
			$this->prepare_entity();
			$this->_expected = ['T_SYMBOL', 'T_CLASS_OPEN_BRACKET'];
		}
		if (in_array($cmd['token'], ['T_DOUBLE_QUOTE', 'T_SINGLE_QUOTE'])) {

			if (end($this->_closing_stack) == $cmd['match']) {
				array_pop($this->_closing_stack);
				$this->_expected = [];
				$this->_command = '';
			} else {
				array_push($this->_closing_stack, $cmd['match']);
				$this->_expected = ['T_SYMBOL', 'T_ESCAPE', 'T_DOUBLE_QUOTE', 'T_SINGLE_QUOTE'];
				$this->_command = 'LITERAL';
			}
		}

		if ($cmd['token'] == 'T_ID') {
			$this->_tree[$this->_nodes]['entities'][$this->_entity] = $cmd['match'];
			if ( isset($this->_entities[$cmd['match']])) {
				$this->store($this->_entities[$cmd['match']]);
			}
		}

		if ($cmd['token'] == 'T_CLASS_OPEN_BRACKET') {
			array_push($this->_closing_stack, $cmd['match']);

			$this->_class = [];
			$this->_expected = ['T_DOUBLE_QUOTE', 'T_SINGLE_QUOTE', 'T_SYMBOL', 'T_CLASS_CLOSE_BRACKET'];
		}

		if ($cmd['token'] == 'T_CLASS_CLOSE_BRACKET') {
			array_pop($this->_closing_stack);

			$data = $this->retrieve();
				// die(var_dump($data));
			if ( !$data) {
				// die(var_dump($this->_entity));
			// } elseif ($data['token'] == 'T_ID') {
			// 	$this->_entities[$data['match']] = $this->_class;
			// } elseif ( $data['token'] == 'T_SYMBOL') {
			// 	$this->_definitions[$data['match']] = $this->_class;
			} else {
				$this->_entities[$data['match']] = $this->_class;
			}
				$this->prepare_entity();
				$this->_tree[$this->_nodes]['entities'][$this->_entity] = $this->_class;

			$this->_expected = [];
			$this->class = [];
		}

		if ($cmd['token'] == 'T_SYMBOL') {
			$this->_expected = ['T_CLASS_OPEN_BRACKET', 'T_ID', 'T_SYMBOL'];

			if ( in_array($cmd['match'], ['TYPE', 'DEFINE'])) {
				// $this->_tree[$this->_nodes]['command'] = $cmd['match'];
				$this->_command = $cmd['match'];
			} elseif ( in_array($cmd['match'], ['LIKE','DOES','WILL','HANDLES','QUERIES','COMMITS','INTENDS'])) {
				$this->_tree[$this->_nodes]['operators'][] = $cmd['match'];
				// $this->_operate($cmd['match']);
				// $this->_expected = ['T_CLASS_OPEN_BRACKET', 'T_ID', 'T_SYMBOL'];
			} elseif ( in_array($cmd['match'], ['NEEDS','EXPECTS','MIGHT','COULD','WOULD','SHOULD','MUST'])) {
				if ( !isset($this->_tree[$this->_nodes]['modes'][$this->_entity]) ) {
					$this->_tree[$this->_nodes]['modes'][$this->_entity] = [];
				}
				$this->_tree[$this->_nodes]['modes'][$this->_entity][] = $cmd['match'];
				// $this->_expected = ['T_CLASS_OPEN_BRACKET', 'T_ID', 'T_SYMBOL'];
			} elseif (array_key_exists($cmd['match'], $this->_definitions)) {
				$this->store($cmd);
				
				$this->_expected = [];
			} elseif (array_key_exists($cmd['match'], $this->_entities)) {
				$this->prepare_entity();
				$this->_tree[$this->_nodes]['entities'][$this->_entity] = $cmd['match'];
				$this->store($this->_entities[$cmd['match']]);
				$this->_expected = [];
			} elseif ( $this->_command == 'TYPE') {
				$this->store($cmd);
				$this->prepare_entity();
				$this->_tree[$this->_nodes]['entities'][$this->_entity] = $cmd['match'];
				$this->_command = '';
			} elseif ( !isset($this->_tree[$this->_nodes]['entities'][$this->_entity]) ) {
				if ( $this->_command == 'ASSIGN') {
					$this->_class[$this->_last_field] = $this->_literal?substr($this->_literal,0,-strlen($cmd['match'])):$cmd['match'];
					$this->_command = '';
					$this->_literal = '';
				} else {
					$this->_last_field = $cmd['match'];
					$this->_class[$cmd['match']] = true;
				}
				$this->_expected = [];
			} else {
				throw new Exception("Undefined {$cmd['token']} '{$cmd['match']}' on line, {$cmd['line']}.", 1);
				
			}
		}
	}

	private function store( $data ) {
		array_push($this->_buffer, $data);
	}

	private function retrieve() {
		return array_pop($this->_buffer);
	}

	public function getTree()
	{
		return $this->_tree;
	}

	
	public function prepare_entity() {
		if ( !isset($this->_tree[$this->_nodes]['entities']['subject']) ) {
			$this->_entity = 'subject';
		} elseif ( !isset($this->_tree[$this->_nodes]['entities']['object']) ) {
			$this->_entity = 'object';
		} elseif ( !isset($this->_tree[$this->_nodes]['entities']['indirect_object']) ) {
			$this->_entity = 'indirect_object';
		}
	}

}