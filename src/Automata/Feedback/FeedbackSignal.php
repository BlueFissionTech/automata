<?php

namespace BlueFission\Automata\Feedback;

class FeedbackSignal
{
    protected float $_value;

    public function __construct(float $value)
    {
        $this->_value = $value;
    }

    public static function positive(float $value = 1.0): self
    {
        return new self(abs($value));
    }

    public static function negative(float $value = 1.0): self
    {
        return new self(-abs($value));
    }

    public function value(): float
    {
        return $this->_value;
    }
}
