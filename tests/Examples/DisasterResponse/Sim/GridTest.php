<?php

namespace BlueFission\Tests\Examples\DisasterResponse\Sim;

use Examples\DisasterResponse\Sim\Cell;
use Examples\DisasterResponse\Sim\Grid;
use Examples\DisasterResponse\Sim\Position;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/Cell.php';
require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/Position.php';
require_once dirname(__DIR__, 4) . '/examples/generic/disaster_response/sim/Grid.php';

class GridTest extends TestCase
{
    public function testGridNeighborsAndNeedScore(): void
    {
        $cells = [
            '1,1' => new Cell('residential', ['people' => 1]),
            '0,1' => new Cell('road', ['blocked' => true]),
        ];
        $grid = new Grid(3, 3, $cells);

        $center = new Position(1, 1);
        $this->assertTrue($grid->inBounds($center));
        $this->assertSame(4, count($grid->neighbors($center)));

        $best = $grid->bestNeighbor(new Position(1, 0));
        $this->assertInstanceOf(Position::class, $best);

        $needScore = $grid->needScore(new Position(1, 1));
        $this->assertGreaterThan(0, $needScore);
    }
}
