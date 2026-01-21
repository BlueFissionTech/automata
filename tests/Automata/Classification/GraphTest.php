<?php

namespace BlueFission\Tests\Automata\Classification;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Classification\Graph;

class GraphTest extends TestCase
{
    public function testRelateCreatesEdges(): void
    {
        $graph = new Graph();
        $graph->addTag('damage');
        $graph->addTag('flooding');
        $graph->relate('damage', 'flooding', 0.8);

        $attrs = $graph->getEdgeAttributes('damage', 'flooding');
        $this->assertIsArray($attrs);
        $this->assertSame(0.8, $attrs['weight']);
    }
}
