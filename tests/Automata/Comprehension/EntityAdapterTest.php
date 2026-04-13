<?php

namespace BlueFission\Tests\Automata\Comprehension;

use BlueFission\Automata\Comprehension\Entity;
use PHPUnit\Framework\TestCase;

class EntityAdapterTest extends TestCase
{
    public function testEntityProvidesSnapshotAndExplainContract(): void
    {
        $entity = new Entity('Warehouse A', 'Regional warehouse', ['category' => 'place']);
        $entity
            ->addLabel('place')
            ->addLabel('storage')
            ->defineDimension('x', ['kind' => 'absolute', 'unit' => 'km'])
            ->defineDimension('time', ['kind' => 'relative', 'unit' => 'minutes'])
            ->coordinate('x', 12)
            ->coordinate('time', 5)
            ->relate('supplies', 'Hospital A', ['distance_km' => 12])
            ->record('status', ['condition' => 'operational']);

        $snapshot = $entity->snapshot();

        $this->assertSame('Warehouse A', $snapshot['name']);
        $this->assertSame('Regional warehouse', $snapshot['description']);
        $this->assertSame(['category' => 'place'], $snapshot['meta']);
        $this->assertCount(2, $snapshot['labels']);
        $this->assertCount(1, $snapshot['relations']);
        $this->assertCount(1, $snapshot['history']);
        $this->assertSame(12, $snapshot['coordinates']['x']);
        $this->assertSame('minutes', $snapshot['dimensions']['time']['unit']);
        $this->assertStringContainsString('Warehouse A', $entity->explain());
    }
}
