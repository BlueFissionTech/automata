<?php

namespace BlueFission\Automata\LLM\Clients;

interface IClient {
	public function generate();
	public function complete();
	public function respond();
}