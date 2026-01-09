<?php

namespace BlueFission\Tests\Automata\Comprehension;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Comprehension\Scene;
use BlueFission\Automata\Comprehension\Frame;
use BlueFission\Automata\Memory\IWorkingMemory;
use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\IRecallScoringStrategy;

class FakeWorkingMemory implements IWorkingMemory
{
    public array $added = [];

    public function addMemory(string $label, Context $context, array $edges = []): void
    {
        $this->added[$label] = $context;
    }

    public function getMemory(string $label): ?\BlueFission\Automata\Memory\MemoryNode
    {
        return null;
    }

    public function reinforcePath(string $start, string $end): array
    {
        return [];
    }

    public function contextSwitchPath(string $from, string $to): array
    {
        return [];
    }

    public function recall(string $label): ?Context
    {
        return null;
    }

    public function recallWithAssociations(string $label, int $max = 10): array
    {
        return [];
    }

    public function associate(string $name1, string $name2, float $weight = 1.0): void
    {
    }

    public function shortestAssociation(string $start, string $end): array
    {
        return [];
    }

    public function forget(string $name): void
    {
    }

    public function contents(): array
    {
        return [];
    }
}

class DummyStrategy implements IRecallScoringStrategy
{
    public function score(array $vecA, array $vecB, Context $contextA, Context $contextB): float
    {
        return 1.0;
    }
}

class SceneTest extends TestCase
{
    public function testAddFrameStoresContextsInWorkingMemory(): void
    {
        $memory = new FakeWorkingMemory();
        $scene  = new Scene($memory);

        $frame = new Frame();
        $frame->addExperience([
            'values' => [
                'road_blockage'    => ['value' => 'bridge_east', 'weight' => 3],
                'hospital_supply'  => ['value' => 'low', 'weight' => 2],
            ],
        ], 'source');

        $scene->addFrame($frame);

        $this->assertArrayHasKey('road_blockage', $memory->added);
        $this->assertArrayHasKey('hospital_supply', $memory->added);
    }

    public function testRecallFromGroupUsesScoringStrategy(): void
    {
        $memory = new FakeWorkingMemory();
        $scene  = new Scene($memory);

        $ctxA = new Context();
        $ctxA->set('label', 'a')->set('value', 'x');
        $ctxB = new Context();
        $ctxB->set('label', 'b')->set('value', 'y');

        $ref = new \ReflectionClass($scene);
        $groupsProp = $ref->getProperty('_groups');
        $groupsProp->setAccessible(true);
        $groupsProp->setValue($scene, [
            'group_ab' => [$ctxA, $ctxB],
        ]);

        $strategy = new DummyStrategy();

        $results = $scene->recallFromGroup('group_ab', 0.5, $strategy);

        $this->assertArrayHasKey('a', $results);
        $this->assertArrayHasKey('b', $results);
    }
}

