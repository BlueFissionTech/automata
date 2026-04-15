<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Comprehension\Entity;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Statement;

class StatementTest extends TestCase
{
    public function testPercentSatisfiedReflectsFilledFields(): void
    {
        $statement = new Statement();

        $initial = $statement->percentSatisfied();

        $statement->field('subject', 'HospitalA');
        $statement->field('behavior', 'requests');

        $after = $statement->percentSatisfied();

        $this->assertGreaterThan($initial, $after);
    }

    public function testEntitiesReturnsSubjectObjectAndIndirectObject(): void
    {
        $statement = new Statement();
        $statement->field('subject', 'HospitalA');
        $statement->field('object', 'oxygen');
        $statement->field('indirect_object', 'ShelterB');

        $entities = $statement->entities();

        $this->assertNotEmpty($entities);
    }

    public function testStatementSnapshotProjectsPrototypeState(): void
    {
        $statement = new Statement();
        $statement->field('subject', 'HospitalA');
        $statement->field('behavior', 'requests');
        $statement->field('object', 'oxygen');
        $statement->field('relationship', 'needs');
        $statement->field('condition', 'critical_supply');
        $statement->field('position', ['x' => 12, 'y' => 4]);

        $snapshot = $statement->snapshot();

        $this->assertSame('statement', $snapshot['kind']);
        $this->assertSame('HospitalA requests oxygen', $snapshot['name']);
        $this->assertNotEmpty($snapshot['relations']);
        $this->assertNotEmpty($snapshot['conditions']);
        $this->assertSame(12, $snapshot['coordinates']['x']);
        $this->assertSame(4, $snapshot['coordinates']['y']);
    }

    public function testStatementAcceptsContextAndEntityObjects(): void
    {
        $statement = new Statement();
        $statement->field('context', (new Context(['topic' => 'triage']))->addTag('emergency'));
        $statement->field('subject', new Entity('Hospital A', 'Regional hospital'));
        $statement->field('behavior', 'requests');
        $statement->field('object', new Entity('Oxygen', 'Critical supply'));

        $snapshot = $statement->snapshot();

        $this->assertSame('Hospital A requests Oxygen', $snapshot['name']);
        $this->assertContains('emergency', $snapshot['labels']);
        $this->assertSame('Hospital A', $snapshot['subject']['name']);
        $this->assertSame('triage', $snapshot['context']['data']['topic']);
        $this->assertStringContainsString('Hospital A', $snapshot['summary']);
    }

    public function testStatementAcceptsPositionLikeObjects(): void
    {
        $statement = new Statement();
        $statement->field('subject', 'Vehicle 1');
        $statement->field('behavior', 'travels');
        $statement->field('object', 'Depot');
        $statement->field('position', new class {
            public function coordinates(): array
            {
                return ['x' => 3, 'y' => 4];
            }
        });

        $snapshot = $statement->snapshot();

        $this->assertSame(3, $snapshot['coordinates']['x']);
        $this->assertSame(4, $snapshot['coordinates']['y']);
    }

    public function testStatementSatisfyFocusesOnCoreClauseCompletion(): void
    {
        $statement = new Statement();
        $statement->field('subject', 'HospitalA');
        $statement->field('behavior', 'requests');

        $this->assertSame('object', $statement->satisfy());

        $statement->field('object', 'oxygen');

        $this->assertNull($statement->satisfy());
        $this->assertGreaterThanOrEqual(0.7, $statement->percentSatisfied());
    }

    public function testStatementFieldsBatchNormalizesPrototypeStateOnceComplete(): void
    {
        $statement = new Statement();

        $statement->assign([
            'subject' => 'sender',
            'behavior' => 'requests',
            'object' => 'refund',
            'relationship' => 'needs',
            'condition' => 'triage',
            'position' => ['x' => 2, 'y' => 9],
        ]);

        $snapshot = $statement->snapshot();

        $this->assertSame('sender requests refund', $snapshot['name']);
        $this->assertSame('refund', $snapshot['relations']['needs'][0]['target']);
        $this->assertSame('triage', $snapshot['conditions'][0]['path']);
        $this->assertSame(2, $snapshot['coordinates']['x']);
        $this->assertSame(9, $snapshot['coordinates']['y']);
    }

    public function testPrototypeFacingReadsNormalizeDirtySemanticStateOnDemand(): void
    {
        $statement = new Statement();

        $statement->field('subject', 'sender');
        $statement->field('behavior', 'requests');
        $statement->field('object', 'refund');

        $this->assertSame('sender requests refund', $statement->name());
        $this->assertNotEmpty($statement->relations());

        $this->assertStringContainsString('sender', $statement->explain());
    }
}
