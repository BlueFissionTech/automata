<?php

namespace BlueFission\Automata\Parsing\Preparers;

use BlueFission\Parsing\Preparers\BasePreparer;
use BlueFission\Parsing\Element;

class LLMPreparer extends BasePreparer
{
	public function prepare(Element $element): void
	{
		if ( !$this->data ) {
			return;
		}

        if (method_exists($element, 'setDriver')) {
        	$element->setDriver($this->data->getLLM());
        }
	}
}