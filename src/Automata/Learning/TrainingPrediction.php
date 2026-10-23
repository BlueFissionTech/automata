<?php

namespace BlueFission\Automata\Learning;

final readonly class TrainingPrediction
{
    public function __construct(
        public bool $available,
        public mixed $output = null,
        public float $confidence = 0.0,
        public int $support = 0,
    ) {
    }

    public static function unavailable(): self
    {
        return new self(false);
    }
}
