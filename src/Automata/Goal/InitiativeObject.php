<?php

namespace BlueFission\Automata\Goal;

use BlueFission\Obj;
use BlueFission\DevElation as Dev;

abstract class InitiativeObject extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();

        if (!empty($data)) {
            $this->assign($data);
        }

        Dev::do('goal.object.created', ['object' => $this]);
    }
}
