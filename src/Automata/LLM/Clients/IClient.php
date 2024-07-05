<?php

namespace BlueFission\Automata\LLM\Clients;

use BlueFission\Automata\LLM\Reply;

interface IClient {
	public function generate($input, $config = [], ?callable $callback = null): Reply;
	public function complete($input, $config = []): Reply;
	public function respond($input, $config = []): Reply;
}