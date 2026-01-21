<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\DevElation as Dev;

class Observation extends FeedbackItem
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        Dev::do('feedback.observation.created', ['observation' => $this]);
    }
}
