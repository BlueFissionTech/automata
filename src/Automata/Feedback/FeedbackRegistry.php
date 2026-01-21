<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\DevElation as Dev;

class FeedbackRegistry
{
    protected OrganizedCollection $_signals;

    public function __construct()
    {
        $this->_signals = new OrganizedCollection();
    }

    public function apply(string $subject, FeedbackSignal $signal): void
    {
        $current = $this->_signals->weight($subject) ?? 0.0;
        $this->_signals->add($signal, $subject);
        $this->_signals->weight($subject, (float)$current + $signal->value());
        $this->_signals->sort();

        Dev::do('feedback.registry.applied', ['subject' => $subject, 'signal' => $signal]);
    }

    public function score(string $subject): float
    {
        $value = $this->_signals->weight($subject);
        return is_numeric($value) ? (float)$value : 0.0;
    }
}
