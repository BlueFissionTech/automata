<?php

namespace BlueFission\Automata\Language;

interface IInterpreter {
	public function load( $file );
	public function run( $code );
	public function isValid( $code ): bool;
}