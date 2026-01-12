<?php

namespace BlueFission\Automata\Parsing\Preparers;

use BlueFission\Parsing\Preparers\BasePreparer;
use BlueFission\Parsing\Element;
use BlueFission\DevElation as Dev;

class LLMPreparer extends BasePreparer
{
	public function prepare(Element $element): void
	{
		if ( !$this->data ) {
			return;
		}

        if (method_exists($element, 'setDriver')) {
        	$driver = $this->data->getLLM();
        	$driver = Dev::apply('automata.parsing.preparers.llmpreparer.prepare.1', $driver);
        	$element->setDriver($driver);
        	Dev::do('automata.parsing.preparers.llmpreparer.prepare.action1', ['element' => $element, 'driver' => $driver]);
        }
	}
}
