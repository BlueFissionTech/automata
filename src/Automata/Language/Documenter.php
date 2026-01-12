<?php

namespace BlueFission\Automata\Language;

use Exception;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Func;
use BlueFission\DevElation as Dev;

class Documenter {

	protected $_statements = [];

	protected $_currentStatement;

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
        Dev::do('language.documenter.construct', ['buffer' => $this->_buffer]);
	}

	/**
	 * Return the currently active entity role label that
	 * subsequent tokens should fill (for example, "subject",
	 * "object", or "indirect_object"). This is primarily used
	 * by higher-level configuration (such as Synthetiq's
	 * sample documenter rules) to decide where to attach
	 * additional entities in a compound phrase.
	 */
	public function get_entity_type(): ?string
	{
		return $this->_entity ?? null;
	}

	public function addRule($types, callable $callable, int $priority = 0) {
		$this->_rules[$priority][] = ['types'=>$types, 'function'=>$callable];
	}

	public function push( $cmd )
	{
        $cmd = Dev::apply('language.documenter.push_cmd', $cmd);
        Dev::do('language.documenter.push_start', ['cmd' => $cmd]);
		if ( !isset($this->_currentStatement) ) {
			$this->_currentStatement = new Statement();
		}

		foreach ( $this->_rules as $priority=>$rules ) {
			foreach ( $rules as $rule ) {
				if ( !$this->isExpected($cmd) ) {
					throw new Exception("Error Processing Request", 1);
				}

				$types = Arr::make($rule['types'])->val();

				if ( $this->match($cmd, $types) ) {
					Func::make($rule['function'])->bind($this, $this)($cmd, $this->_currentStatement);
					$this->_expected = $cmd['expects'][$types[0]];
					break 2;
				}
			}
		}
		if ( $this->_currentStatement->percentSatisfied() >= .7 ) {
			$this->_statements[] = $this->_currentStatement;
			$this->_currentStatement = new Statement();
            Dev::do('language.documenter.statement_complete', ['statement' => end($this->_statements)]);
		}
        Dev::do('language.documenter.push_complete', ['cmd' => $cmd]);
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
        Dev::do('language.documenter.process_start', ['statements' => $this->_statements]);
		foreach ( $this->_statements as $statement ) {
			$this->processStatement($statement);
		}
        Dev::do('language.documenter.process_complete', ['tree' => $this->_tree]);
	}

	private function processStatement( $statement )
	{
		$this->_nodes++;
		$this->_tree[$this->_nodes] = $statement;
        Dev::do('language.documenter.process_statement', ['statement' => $statement, 'index' => $this->_nodes]);
	}

	public function getTree()
	{
        return Dev::apply('language.documenter.tree', $this->_tree);
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
