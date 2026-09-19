<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\TrainingBatch;
use BlueFission\Automata\Learning\TrainingExample;
use PHPUnit\Framework\TestCase;

class TrainingBatchTest extends TestCase
{
    public function testKeyedExamplesBecomeOrderedListsWithoutLosingFalseOrZeroValues(): void
    {
        $batch = new TrainingBatch('projection', '1', [
            'first' => new TrainingExample('experience-1', 'outcome-1', 0, false),
            'second' => new TrainingExample('experience-2', 'outcome-2', '', 0),
        ]);
        $this->assertSame([0, ''], $batch->samples());
        $this->assertSame([false, 0], $batch->labels());
        $this->assertSame([0, 1], array_keys($batch->toArray()['examples']));
    }
}
