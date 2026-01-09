<?php

namespace BlueFission\Tests\Automata\Comprehension;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Comprehension\Holoscene;

class FakeScene
{
    public function stats(): array
    {
        return [];
    }

    public function data(): array
    {
        return ['summary' => 'test'];
    }
}

class HolosceneTest extends TestCase
{
    public function testHolosceneStoresAndAssessesScenes(): void
    {
        $holoscene = new Holoscene();

        $sceneA = new FakeScene();
        $sceneB = new FakeScene();

        $holoscene->push('episode_a', $sceneA);
        $holoscene->push('episode_b', $sceneB);

        $holoscene->review();
        $assessment = $holoscene->assessment();

        $this->assertArrayHasKey('episode_a', $assessment);
        $this->assertArrayHasKey('episode_b', $assessment);

        $this->assertSame($sceneA, $assessment['episode_a']['value']);
        $this->assertSame($sceneB, $assessment['episode_b']['value']);
    }
}

