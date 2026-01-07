<?php

namespace BlueFission\Tests\Automata\Analysis;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Analysis\BetaReliability;

class BetaReliabilityTest extends TestCase
{
    public function testUpdatesMeanReliability(): void
    {
        $beta = new BetaReliability();

        // Prior Beta(1,1) is uniform.
        $this->assertEqualsWithDelta(0.5, $beta->mean('edge1'), 1e-6);

        // Three successes, one failure.
        $beta->update('edge1', true);
        $beta->update('edge1', true);
        $beta->update('edge1', true);
        $beta->update('edge1', false);

        $params = $beta->parameters('edge1');

        $this->assertEquals(4.0, $params['alpha']);
        $this->assertEquals(2.0, $params['beta']);

        $this->assertEqualsWithDelta(4.0 / 6.0, $beta->mean('edge1'), 1e-6);
    }
}

