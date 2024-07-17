<?php

namespace BlueFission\Automata\Intent\Skill;

interface ISkill {
	public function name(): string;
	public function execute(Context $context);
	public function response(): string;
}