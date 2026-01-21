<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\Automata\Context;

trait ProducesObservations
{
    public function produceObservation(array $data = []): Observation
    {
        if (!isset($data['context'])) {
            $data['context'] = new Context();
        }

        return new Observation($data);
    }
}
