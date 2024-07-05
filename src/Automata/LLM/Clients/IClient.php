<?php

namespace BlueFission\Automata\LLM\Client;

interface IClient {
	public function generate();
	public function complete();
	public function respond();
}