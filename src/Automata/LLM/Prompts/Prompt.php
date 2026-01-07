<?php

namespace BlueFission\Automata\LLM\Prompts;

class Prompt implements IPrompt
{
	protected $_fields = ['input'];
	protected $_data = [];
	protected $_template = "Consider the following input, think through your steps, and respond as best as you can -
	Input: {input}
	Response: ";

	public function __construct()
	{
		$args = func_get_args();

		if ( empty($this->_fields) ) {
			// Find every {{bracketed}} word in the template and populate the fields array
			$matches = [];
			preg_match_all('/{(.*?)}/', $this->_template, $matches);
			$this->_fields = $matches[1];
		}

		foreach ($this->_fields as $field) {
			$var = array_shift($args);
			$var = (isset($var)) ? $var : '';
			$this->_data[$field] = $var;
		}
	}

	public function prompt()
	{
		$output = $this->_template;
		foreach ($this->_data as $key => $value) {
			$output = str_replace("{{$key}}", $value, $output);
		}

		return $output;
	}
}
