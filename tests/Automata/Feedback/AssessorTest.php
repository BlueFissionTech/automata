<?php

namespace BlueFission\Tests\Automata\Feedback;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Feedback\Assessor;
use BlueFission\Automata\Feedback\Projection;
use BlueFission\Automata\Feedback\Observation;
use BlueFission\Automata\Feedback\Strategies\LabelOverlapStrategy;

class AssessorTest extends TestCase
{
    public function testAssessorMatchesProjectionByLabel(): void
    {
        $assessor = new Assessor();
        $assessor->addStrategy(new LabelOverlapStrategy());

        $projection = new Projection(['tags' => ['damage', 'people']]);
        $observation = new Observation(['tags' => ['damage']]);

        $assessment = $assessor->assess($projection, $observation);

        $this->assertTrue($assessment->matched());
        $this->assertGreaterThan(0, $assessment->score());
    }
}
