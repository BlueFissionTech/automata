<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\DevElation as Dev;
use BlueFission\Obj;

class Indicator extends Obj
{
    public function __construct(string $label = '', float $score = 0.0, array $meta = [])
    {
        parent::__construct();

        $this->assign([
            'label' => Dev::apply('anomaly.indicator.label', $label),
            'score' => Dev::apply('anomaly.indicator.score', $score),
            'meta' => Dev::apply('anomaly.indicator.meta', $meta),
        ]);

        Dev::do('anomaly.indicator.created', ['indicator' => $this]);
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

    public function flagged(): bool
    {
        $meta = $this->meta();
        return !empty($meta['flagged']);
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
