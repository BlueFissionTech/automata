<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

class BreadthFirstMethod implements IMethod
{
    public function orderRules(array $rules): array
    {
        $rules = Dev::apply('expert.breadth.rules', $rules);
        $ordered = array_reverse($rules);
        $ordered = Dev::apply('expert.breadth.ordered', $ordered);
        Dev::do('expert.breadth.ordering', ['method' => 'breadth', 'rules' => $rules, 'ordered' => $ordered]);
        return $ordered;
    }
}
