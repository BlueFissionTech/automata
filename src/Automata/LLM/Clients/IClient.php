<?php

namespace BlueFission\Automata\LLM\Clients;

interface IClient {
	public function generate($input, $config = [], $callback = null);
	public function complete($input, $config = []);
	public function respond($input, $config = []);
}