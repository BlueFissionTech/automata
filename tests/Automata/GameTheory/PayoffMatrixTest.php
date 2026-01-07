<?php

namespace BlueFission\Tests\Automata\GameTheory;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\GameTheory\PayoffMatrix;

class PayoffMatrixTest extends TestCase
{
    public function testSetAndGetPayoff(): void
    {
        $matrix = new PayoffMatrix();

        $actions   = ['Conservative', 'Aggressive'];
        $payoffs   = [5, 3];

        $matrix->setPayoff($actions, $payoffs);

        $retrieved = $matrix->getPayoff($actions);

        $this->assertSame($payoffs, $retrieved);
    }

    public function testMissingProfileReturnsNull(): void
    {
        $matrix = new PayoffMatrix();

        $this->assertNull($matrix->getPayoff(['A', 'B']));
    }
}

