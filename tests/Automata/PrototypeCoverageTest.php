<?php

namespace BlueFission\Tests\Automata;

use BlueFission\Automata\Comprehension\Entity;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\GameTheory\Player;
use BlueFission\Automata\Goal\Condition;
use BlueFission\Automata\Language\Statement;
use PHPUnit\Framework\TestCase;

class PrototypeCoverageTest extends TestCase
{
    public function testCorePrototypeCarriersExposeStableSnapshots(): void
    {
        $entity = (new Entity('Clinic A', 'Primary care site', ['type' => 'facility']))
            ->addLabel('care')
            ->coordinate('x', 7)
            ->relate('served_by', 'Responder A');

        $statement = new Statement();
        $statement->assign([
            'subject' => $entity,
            'behavior' => 'requests',
            'object' => 'support',
            'relationship' => 'needs',
            'condition' => 'priority',
            'position' => ['x' => 7, 'y' => 2],
        ]);

        $condition = new Condition([
            'path' => 'triage.priority',
            'operator' => 'gte',
            'value' => 2,
            'confidence' => 0.8,
        ]);

        $holoscene = new Holoscene('incident-response');
        $holoscene->push('clinic', $entity);
        $holoscene->review();

        $player = new Player('Responder A');
        $player
            ->role('operator')
            ->scope('incident')
            ->awareness('local')
            ->efficacy('can_make_now')
            ->addStrategy('dispatch')
            ->adoptDecision(['action' => 'route_support']);

        $entitySnapshot = $entity->snapshot();
        $statementSnapshot = $statement->snapshot();
        $conditionSnapshot = $condition->snapshot();
        $holosceneSnapshot = $holoscene->snapshot();
        $playerSnapshot = $player->snapshot();

        $this->assertSame('entity', $entitySnapshot['kind']);
        $this->assertSame('Clinic A', $entitySnapshot['name']);
        $this->assertSame('facility', $entitySnapshot['meta']['type']);
        $this->assertSame(7, $entitySnapshot['coordinates']['x']);
        $this->assertArrayHasKey('served_by', $entitySnapshot['relations']);

        $this->assertSame('statement', $statementSnapshot['kind']);
        $this->assertSame('needs', array_key_first($statementSnapshot['relations']));
        $this->assertSame('priority', $statementSnapshot['conditions'][0]['path']);
        $this->assertSame(7, $statementSnapshot['coordinates']['x']);

        $this->assertSame('condition', $conditionSnapshot['kind']);
        $this->assertSame('triage.priority', $conditionSnapshot['path']);
        $this->assertSame(0.8, $conditionSnapshot['confidence']);
        $this->assertTrue($condition->matches(['triage' => ['priority' => 3]]));

        $this->assertSame('domain', $holosceneSnapshot['kind']);
        $this->assertSame('incident-response', $holosceneSnapshot['name']);
        $this->assertSame(1, $holosceneSnapshot['sceneCount']);
        $this->assertSame(1, $holosceneSnapshot['measures']['scene_count'] ?? null);

        $this->assertSame('agent', $playerSnapshot['kind']);
        $this->assertSame('operator', $playerSnapshot['role']);
        $this->assertSame('incident', $playerSnapshot['scope']);
        $this->assertSame(1, $playerSnapshot['strategyCount']);
        $this->assertSame(['action' => 'route_support'], $playerSnapshot['lastDecision']);
    }
}
