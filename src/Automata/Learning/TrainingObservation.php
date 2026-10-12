<?php

namespace BlueFission\Automata\Learning;

/** A value snapshot. It must contain only bounded, recordable PHP values. */
final readonly class TrainingObservation
{
    public function __construct(
        public array $input,
        public mixed $output,
        public array $context,
        public array $metadata,
    ) {
    }
}
