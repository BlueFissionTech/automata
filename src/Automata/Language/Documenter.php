<?php

namespace BlueFission\Automata\Language;

use Exception;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Func;

class Documenter {

	protected $_statements = [];

	protected $_entities = [];

	protected $_definitions = [];

	protected $_contexts = [];

	protected $_stack = [];

	protected $_tree = [];

	protected $_nodes = -1;

	protected $_entity;

	protected $_expected = ['T_DOCUMENT'];

	protected $_closing_stack = [];

	protected $_command = '';

	protected $_literal = '';

	protected $_class;

	protected $_reaction = null;

	protected $_rules = [];


	protected $_buffer = [];

	public function __construct( ) {
		$this->_buffer = Arr::make([]);
	}

	public function addRule($types, callable $callable, int $priority = 0) {
		$this->_rules[$priority][] = ['types'=>$types, 'function'=>$callable];
	}

	public function push( $cmd )
	{
		if ( !isset($statement) ) {
			$statement = new Statement();
		}

		foreach ( $this->_rules as $priority=>$rules ) {
			foreach ( $rules as $rule ) {
				if ( !$this->isExpected($cmd) ) {
					throw new Exception("Error Processing Request", 1);
				}

				$types = Arr::make($rule['types'])->val();

				if ( $this->match($cmd, $types) ) {
					Func::make($rule['function'])->bind($this, $this)($cmd, $statement);
					$this->_expected = $cmd['expects'][$types[0]];
				}
			}
		}
		if ( $statement->percentSatisfied() >= .1 ) {
			$this->_statements[] = $statement;
			$statement = new Statement();
		}
	}

	private function isExpected( $cmd ) {
		$expected = false;


		if ( $this->_expected == ['T_DOCUMENT'] ) return true;

		foreach ( $cmd['classifications'] as $type ) {
			$expected = Arr::has($this->_expected, $type);

			if (! $expected) {
				foreach ( $this->_expected as $expect ) {
					if (Str::has($expect, '|')) {
						$expected = Str::use()->split('|')->has($type);
						if ( $expected ) break;
					}
				}
			}
			if ( $expected ) break;
		}

		return $expected;
	}

	private function match( $cmd, $types ) {
		$match = false;
		foreach ( $cmd['classifications'] as $classification ) {
			$match = Arr::has($types, $classification);
			if ( $match ) break;
		}

		return $match;
	}

	private function store( $data ) {
		$this->_buffer->push($data);
	}

	private function retrieve() {
		return $this->_buffer->pop();
	}

	public function processStatements()
	{
		foreach ( $this->_statements as $statement ) {
			$this->processStatement($statement);
		}
	}

	private function processStatement( $statement )
	{
		$this->_nodes++;
		$this->_tree[$this->_nodes] = $statement;
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