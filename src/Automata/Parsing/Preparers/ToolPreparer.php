<?php

namespace BlueFission\Automata\Parsing\Preparers;

use BlueFission\Parsing\Preparers\BasePreparer;
use BlueFission\Parsing\Element;

class ToolPreparer extends BasePreparer
{
	public function prepare(Element $element): void
	{
		if ( !$this->data ) {
			return;
		}

        foreach ($this->data as $name => $tool) {
            $element->addTool($name, $tool);
        }
	}
}