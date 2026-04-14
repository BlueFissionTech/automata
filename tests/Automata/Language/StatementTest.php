<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
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
}
