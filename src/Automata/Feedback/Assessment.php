<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\DevElation as Dev;

class Assessment
{
    protected bool $_matched = false;
    protected float $_score = 0.0;
    protected string $_strategy = '';
    protected array $_meta = [];

    public function __construct(bool $matched = false, float $score = 0.0, string $strategy = '', array $meta = [])
    {
        $this->_matched = $matched;
        $this->_score = $score;
        $this->_strategy = $strategy;
        $this->_meta = $meta;

        Dev::do('feedback.assessment.created', ['assessment' => $this]);
    }

    public function matched(): bool
    {
        return $this->_matched;
    }

    public function score(): float
    {
        return $this->_score;
    }

    public function strategy(): string
    {
        return $this->_strategy;
    }

    public function meta(): array
    {
        return $this->_meta;
    }
}
