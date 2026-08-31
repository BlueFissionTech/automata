<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Obj;

abstract class RoutingValue extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign($data);
    }
}
