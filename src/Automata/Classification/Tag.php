<?php

namespace BlueFission\Automata\Classification;

use BlueFission\Obj;
use BlueFission\DevElation as Dev;

class Tag extends Obj
{
    public function __construct(string $label = '', float $score = 0.0, array $meta = [])
    {
        parent::__construct();

        $this->assign([
            'label' => Dev::apply('classification.tag.label', $label),
            'score' => Dev::apply('classification.tag.score', $score),
            'meta' => Dev::apply('classification.tag.meta', $meta),
        ]);

        Dev::do('classification.tag.created', ['tag' => $this]);
    }

    public function label(): string
    {
        return (string)$this->field('label');
    }

    public function score(): float
    {
        return (float)$this->field('score');
    }

    public function meta(): array
    {
        $meta = $this->field('meta');
        return is_array($meta) ? $meta : [];
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label(),
            'score' => $this->score(),
            'meta' => $this->meta(),
        ];
    }
}
