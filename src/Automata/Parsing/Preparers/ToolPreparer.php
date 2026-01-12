<?php

namespace BlueFission\Automata\Parsing\Preparers;

use BlueFission\Parsing\Preparers\BasePreparer;
use BlueFission\Parsing\Element;
use BlueFission\DevElation as Dev;

class ToolPreparer extends BasePreparer
{
	public function prepare(Element $element): void
	{
		if ( !$this->data ) {
			return;
		}

        foreach ($this->data as $name => $tool) {
        	$tool = Dev::apply('automata.parsing.preparers.toolpreparer.prepare.1', $tool);
            $element->addTool($name, $tool);
            Dev::do('automata.parsing.preparers.toolpreparer.prepare.action1', ['element' => $element, 'toolName' => $name, 'tool' => $tool]);
        }
	}
}
