<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Statement;
use BlueFission\Automata\Learning\Experience;
use BlueFission\Automata\Learning\InMemoryExperienceStore;
use BlueFission\Automata\Learning\Outcome;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ExperienceTest extends TestCase
{
    public function testSemanticInputIsSnapshottedAndCanBeRestored(): void
    {
        $statement = new Statement();
        $statement->assign(['subject' => 'guest', 'behavior' => 'asks', 'object' => 'breakfast']);
        $context = new Context(['utterance' => 'Where is breakfast?']);
        $context->addTag('concierge')->setNormalization('channel', 'text');
        $experience = Experience::fromStatements('episode-1', [$statement], $context, [
            'timestamp' => '2026-09-19T12:00:00Z', 'trace_id' => 'trace-1',
        ]);
        $statement->field('object', 'luggage');
        $context->set('utterance', 'changed');
        $record = $experience->toArray();
        $this->assertSame('breakfast', $record['statements'][0]['object']);
        $this->assertSame('Where is breakfast?', $record['context']['data']['utterance']);
        $this->assertArrayHasKey('concierge', $record['context']['tags']);
        $this->assertSame('text', $record['context']['normalizations']['channel']['value']);
        $restored = new Experience(json_decode(json_encode($experience, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true));
        $this->assertSame($record, $restored->toArray());
    }

    public function testDelayedOutcomesPreserveTheOriginalAndStoreSnapshots(): void
    {
        $original = Experience::fromStatements('episode-1', [], new Context());
        $outcome = new Outcome('outcome-1', 'episode-1', 'operator-review', true, ['intent' => 'directions']);
        $completed = $original->withOutcome($outcome);
        $store = new InMemoryExperienceStore();
        $store->save($original);
        $store->save($completed);
        $store->save($completed);
        $this->assertSame([], $original->outcomes());
        $this->assertCount(1, $store->get('episode-1')->outcomes());
        $this->assertCount(1, iterator_to_array($store->experiences()));
        $this->assertNull($store->get('missing'));
        $this->assertSame($completed->toArray(), $completed->withOutcome($outcome)->toArray());
    }

    public function testOutcomeCannotBeAttachedToAnotherExperience(): void
    {
        $experience = Experience::fromStatements('episode-1', [], new Context());
        $this->expectException(InvalidArgumentException::class);
        $experience->withOutcome(new Outcome('outcome-1', 'episode-2', 'review', true));
    }

    public function testConflictingDuplicateOutcomeIsRejected(): void
    {
        $experience = Experience::fromStatements('episode-1', [], new Context())
            ->withOutcome(new Outcome('outcome-1', 'episode-1', 'review', true));
        $this->expectException(InvalidArgumentException::class);
        $experience->withOutcome(new Outcome('outcome-1', 'episode-1', 'review', false));
    }

    public function testUnknownSchemaCannotBeSilentlyRestored(): void
    {
        $record = Experience::fromStatements('episode-1', [], new Context())->toArray();
        $record['schema_version'] = 99;
        $this->expectException(InvalidArgumentException::class);
        new Experience($record);
    }

    public function testRuntimeObjectsCannotLeakIntoPersistedExperience(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Experience::fromStatements('episode-1', [], new Context(['callback' => static fn () => null]));
    }
}
