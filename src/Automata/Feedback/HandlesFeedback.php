<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\DevElation as Dev;

trait HandlesFeedback
{
    public function applyFeedback(FeedbackSignal $signal): void
    {
        Dev::do('feedback.handled', ['target' => $this, 'signal' => $signal]);
    }
}
