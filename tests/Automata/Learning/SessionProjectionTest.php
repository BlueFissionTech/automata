<?php

namespace BlueFission\Tests\Automata\Learning;

require_once dirname(__DIR__, 3) . '/examples/generic/cortex/SessionProjection.php';

use BlueFission\Automata\Context;
use BlueFission\Automata\Learning\{Experience, ExperienceRecomposer, Outcome};
use BlueFission\Examples\Cortex\SessionProjection;
use PHPUnit\Framework\TestCase;

/** Labels require independent explicit review, never an execution status or reward. */
final class SessionProjectionTest extends TestCase
{
    private function experience(): Experience
    {
        return Experience::fromStatements('session', [], new Context(['model_features' => [4.0]]))
            ->withOutcome(new Outcome('execution', 'session', 'worker', true, ['reward' => 1]));
    }

    public function testSuccessfulExecutionAndNeutralFeedbackDoNotInventLabels(): void
    {
        $experience = $this->experience()->withOutcome(new Outcome('review', 'session', 'fixture-reviewer', true,
            ['approved' => true, 'reward' => 0, 'label' => null]));
        self::assertSame([], (new ExperienceRecomposer())->compose([$experience], new SessionProjection())->samples());
    }

    public function testExplicitLabelUsesCapturedFeaturesAndRetainsSourceLineage(): void
    {
        $experience = $this->experience()->withOutcome(new Outcome('review', 'session', 'fixture-reviewer', true,
            ['approved' => true, 'reward' => -1, 'label' => 'assist']));
        $batch = (new ExperienceRecomposer())->compose([$experience, $experience], new SessionProjection());
        self::assertSame([[4.0]], $batch->samples());
        self::assertSame(['assist'], $batch->labels());
        self::assertSame('review', $batch->toArray()['examples'][0]['outcome_id']);
    }

    public function testConflictingOrUnapprovedLabelsAbstain(): void
    {
        $experience = $this->experience()->withOutcome(new Outcome('review-a', 'session', 'fixture-reviewer', true,
            ['approved' => true, 'label' => 'assist']))
            ->withOutcome(new Outcome('review-b', 'session', 'fixture-reviewer', true, ['approved' => true, 'label' => 'observe']));
        self::assertSame([], (new ExperienceRecomposer())->compose([$experience], new SessionProjection())->samples());
        $unapproved = $this->experience()->withOutcome(new Outcome('review', 'session', 'fixture-reviewer', true,
            ['approved' => false, 'label' => 'assist']));
        self::assertSame([], (new ExperienceRecomposer())->compose([$unapproved], new SessionProjection())->samples());
    }
}
