<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Context;
use BlueFission\Automata\Learning\CallbackTrainingAdapter;
use BlueFission\Automata\Learning\Experience;
use BlueFission\Automata\Learning\ExperienceRecomposer;
use BlueFission\Automata\Learning\Outcome;
use BlueFission\Automata\Learning\TrainingExample;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExperienceRecomposerTest extends TestCase
{
    public function testProjectionRetainsLineageAndDoesNotTrainOnPendingExperience(): void
    {
        $pending = Experience::fromStatements('pending', [], new Context(['text' => 'hello']));
        $observed = Experience::fromStatements('observed', [], new Context(['text' => 'where is breakfast']))
            ->withOutcome(new Outcome('review-1', 'observed', 'human-review', true, ['intent' => 'directions']));
        $adapter = new CallbackTrainingAdapter('intent', '1', static function (Experience $experience): iterable {
            foreach ($experience->outcomes() as $outcome) {
                yield new TrainingExample($experience->id(), $outcome->id(),
                    $experience->toArray()['context']['data']['text'], $outcome->observations()['intent']);
            }
        });
        $batch = (new ExperienceRecomposer())->compose([$pending, $observed, $observed], $adapter);
        $this->assertSame(['where is breakfast'], $batch->samples());
        $this->assertSame(['directions'], $batch->labels());
        $this->assertSame('intent', $batch->toArray()['projection_id']);
        $this->assertSame('1', $batch->toArray()['projection_version']);
        $this->assertSame('observed', $batch->toArray()['examples'][0]['experience_id']);
        $this->assertSame('review-1', $batch->toArray()['examples'][0]['outcome_id']);
    }

    public function testProjectionCannotInventOutcomeEvidence(): void
    {
        $experience = Experience::fromStatements('episode-1', [], new Context());
        $adapter = new CallbackTrainingAdapter('intent', '1', static fn () => [
            new TrainingExample('episode-1', 'invented', 'input', 'label'),
        ]);
        $this->expectException(InvalidArgumentException::class);
        (new ExperienceRecomposer())->compose([$experience], $adapter);
    }

    public function testProjectionCannotMisattributeAnotherExperience(): void
    {
        $experience = Experience::fromStatements('episode-1', [], new Context())
            ->withOutcome(new Outcome('review-1', 'episode-1', 'review', true));
        $adapter = new CallbackTrainingAdapter('intent', '1', static fn () => [
            new TrainingExample('other-episode', 'review-1', 'input', 'label'),
        ]);
        $this->expectException(InvalidArgumentException::class);
        (new ExperienceRecomposer())->compose([$experience], $adapter);
    }
}
