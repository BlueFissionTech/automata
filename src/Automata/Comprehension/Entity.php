<?php
namespace BlueFission\Automata\Comprehension;

class Entity {

	protected string $_name;
	protected ?string $_description;
	protected mixed $_meta;

	public function __construct( string $name, ?string $description = null, $meta = null ) {
		$this->_name = $name;
		$this->_description = $description;
		$this->_meta = $meta;
	}

	public function name(): string
	{
		return $this->_name;
	}

	public function description(): ?string
	{
		return $this->_description;
	}

	public function meta()
	{
		return $this->_meta;
	}
}
