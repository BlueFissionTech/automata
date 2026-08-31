<?php

namespace BlueFission\Automata\LLM\Agent\Capability;

use BlueFission\Obj;

abstract class CapabilityValue extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();
        $this->assign($data);
    }
}
