<?php
namespace BlueFission\Automata\ExpertSystem;

class BreadthFirstMethod implements IMethod
{
    public function orderRules(array $rules): array
    {
        // Assuming that breadth-first search corresponds to the reverse order of rules
        return array_reverse($rules);
    }
}
