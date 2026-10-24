<?php

namespace BlueFission\Automata\Behavioral;

use Attribute;
use BlueFission\Arr;
use BlueFission\Str;

#[Attribute(Attribute::TARGET_METHOD)]
final class TrainableMethod
{
    public function __construct(
        public readonly int $minimumSamples = 3,
        public readonly float $minimumConfidence = 1.0,
        public readonly bool $allowPrediction = false,
        public readonly int $minimumMatches = 2,
        public readonly int $probeEvery = 10,
        /** @var list<string> Obj behavior names dispatched after a return on either path. */
        public readonly array $behaviors = [],
    ) {
        if ($minimumSamples < 1 || $minimumMatches < 1 || $probeEvery < 1
            || !is_finite($minimumConfidence)
            || $minimumConfidence < 0.0 || $minimumConfidence > 1.0) {
            throw new \InvalidArgumentException('Invalid Trainable thresholds.');
        }
        if (!array_is_list($behaviors)) {
            throw new \InvalidArgumentException('Trainable behaviors must be a list of names.');
        }
        foreach ($behaviors as $behavior) {
            if (!Str::is($behavior) || Str::trim($behavior) === '' || Str::trim($behavior) !== $behavior) {
                throw new \InvalidArgumentException('Trainable behaviors must be non-empty names.');
            }
        }
        if (Arr::make($behaviors)->unique()->size() !== Arr::size($behaviors)) {
            throw new \InvalidArgumentException('Trainable behavior names must be unique.');
        }
    }
}
