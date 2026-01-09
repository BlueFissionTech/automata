<?php

namespace BlueFission\Tests\Automata\Memory;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\Automata\Memory\MemoryNode;

class Abs2MemoryTest extends TestCase
{
    public function testAddAndRecallMemory(): void
    {
        $memory = new Abs2Memory();

        $context = new Context();
        $context->set('type', 'delivery')->set('destination', 'Hospital-A');

        $memory->addMemory('episode_1', $context);

        $recalled = $memory->recall('episode_1');

        $this->assertInstanceOf(Context::class, $recalled);
        $this->assertSame('Hospital-A', $recalled->get('destination'));
    }

    public function testAssociationsAndShortestAssociation(): void
    {
        $memory = new Abs2Memory();

        $ctxA = (new Context())->set('label', 'A');
        $ctxB = (new Context())->set('label', 'B');
        $ctxC = (new Context())->set('label', 'C');

        $memory->addMemory('A', $ctxA);
        $memory->addMemory('B', $ctxB);
        $memory->addMemory('C', $ctxC);

        // A <-> B <-> C forming a simple chain
        $memory->associate('A', 'B', 1.0);
        $memory->associate('B', 'C', 1.0);

        $path = $memory->shortestAssociation('A', 'C');

        $this->assertSame(['A', 'B', 'C'], $path);
    }

    public function testReinforcePathIncrementsReinforcement(): void
    {
        $memory = new Abs2Memory();

        $ctxA = (new Context())->set('label', 'A');
        $ctxB = (new Context())->set('label', 'B');

        $memory->addMemory('A', $ctxA, ['B' => 1.0]);
        $memory->addMemory('B', $ctxB, ['A' => 1.0]);

        $before = $memory->getMemory('A')->getContext()->get('reinforcement', 0.0);

        $path = $memory->reinforcePath('A', 'B');

        $after = $memory->getMemory('A')->getContext()->get('reinforcement', 0.0);

        $this->assertSame(['A', 'B'], $path);
        $this->assertGreaterThan($before, $after);
    }

    public function testRecallSimilarReturnsSortedResults(): void
    {
        $memory = new Abs2Memory();

        // Two memories close to the query vector, one clearly opposite.
        $ctx1 = (new Context())->set('x', 1.0)->set('y', 0.0);
        $ctx2 = (new Context())->set('x', 0.8)->set('y', 0.2);
        $ctx3 = (new Context())->set('x', -1.0)->set('y', 0.0);

        $memory->addMemory('near_1', $ctx1);
        $memory->addMemory('near_2', $ctx2);
        $memory->addMemory('far', $ctx3);

        $query = (new Context())->set('x', 1.0)->set('y', 0.0);

        $results = $memory->recallSimilar($query, 0.5);

        $this->assertArrayHasKey('near_1', $results);
        $this->assertArrayHasKey('near_2', $results);
        $this->assertArrayNotHasKey('far', $results);
    }
}
