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
}
