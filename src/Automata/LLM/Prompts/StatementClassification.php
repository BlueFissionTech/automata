<?php
namespace BlueFission\Automata\LLM\Prompts;

class StatementClassification extends Prompt
{
	protected $_fields = ['input'];
	protected $_template = "If there is any question, request, or inquiry present anywhere in the following text, then classify it as 'question'. Otherwise classify it as either a 'statement', or a 'stop' phrase. \"{input}\" is a (question, statement, stop): ";
}