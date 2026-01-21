<?php

namespace BlueFission\Tests\Examples\DisasterResponse\Sim;

use Examples\DisasterResponse\Sim\Cell;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/Cell.php';

class CellTest extends TestCase
{
    public function testCellStateTransitions(): void
    {
        $cell = new Cell('road', ['blocked' => true, 'damaged' => true, 'people' => 2, 'supplies' => 1]);

        $this->assertTrue($cell->isBlocked());
        $this->assertTrue($cell->isDamaged());
        $this->assertSame(2, $cell->people());
        $this->assertSame(1, $cell->supplies());

        $this->assertTrue($cell->clear());
        $this->assertTrue($cell->repair());
        $this->assertTrue($cell->rescue());
        $this->assertTrue($cell->deliver());

        $tags = $cell->tags();
        $this->assertContains('road', $tags);
        $this->assertNotContains('blocked', $tags);
        $this->assertNotContains('damage', $tags);
        $this->assertContains('people', $tags);
    }
}
