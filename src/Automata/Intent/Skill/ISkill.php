<?php

namespace BlueFission\Automata\Intent\Skill;

use BlueFission\Automata\Context;

interface ISkill {
	public function name(): string;
	public function execute(Context $context);
	public function response(): string;
}