<?php
namespace BlueFission\Automata\Expert;

use BlueFission\DevElation as Dev;

class DepthFirstMethod implements IMethod
{
    public function orderRules(array $rules): array
    {
        $rules = Dev::apply('expert.depth.rules', $rules);
        Dev::do('expert.depth.ordering', ['method' => 'depth', 'rules' => $rules]);
        return Dev::apply('expert.depth.result', $rules);
    }
}
