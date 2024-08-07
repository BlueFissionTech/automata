<?php

namespace BlueFission\Automata\LLM;

use BlueFission\Arr;

class Reply {
	private $_messages;
	private bool $_success;

	public function __construct() {
		$this->_messages = new Arr();
		$this->_success = false;
	}

	public function addMessage( string $message, $success = true ): void
	{
		$this->_messages->push(trim($message));
		$this->setSuccess($success);
	}
	public function setSuccess( bool $success ): void
	{
		$this->_success = $success;
	}

	public function messages(): Arr
	{
		return $this->_messages;
	}

	public function success(): bool
	{
		return $this->_success;
	}

}