<?php

namespace BlueFission\Tests\Automata\Feedback;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\Feedback\Observation;
use BlueFission\Automata\Feedback\Strategies\LabelOverlapStrategy;
use BlueFission\Automata\Feedback\Strategies\TimeWindowMatchStrategy;
use BlueFission\Automata\Feedback\Strategies\ContextSimilarityStrategy;

class StrategyTest extends TestCase
{
    public function testLabelOverlapScores(): void
    {
        $projection = new Projection(['tags' => ['damage', 'people']]);
        $observation = new Observation(['tags' => ['damage']]);

        $strategy = new LabelOverlapStrategy();
        $score = $strategy->score($projection, $observation);

        $this->assertSame(0.5, $score);
    }

    public function testTimeWindowRejectsExpiredProjection(): void
    {
        $projection = new Projection(['ttl' => -1, 'tags' => ['time']]);
        $observation = new Observation(['tags' => ['time']]);

        $strategy = new TimeWindowMatchStrategy();
        $score = $strategy->score($projection, $observation);

        $this->assertSame(0.0, $score);
    }

    public function testContextSimilarityScores(): void
    {
        $projection = new Projection(['context' => ['region' => 'north', 'priority' => 'high']]);
        $observation = new Observation(['context' => ['region' => 'north', 'priority' => 'low']]);

        $strategy = new ContextSimilarityStrategy();
        $score = $strategy->score($projection, $observation);

        $this->assertSame(0.5, $score);
    }
}
