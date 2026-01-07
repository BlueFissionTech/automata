<?php

namespace BlueFission\Tests\Automata\Comprehension;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Comprehension\Log;
use BlueFission\Automata\Comprehension\Entity;

class HolosceneLogTest extends TestCase
{
    public function testLogBuildsNarrativeStructure(): void
    {
        $log = new Log();
        $log->setTime('2026-01-07 10:00:00');
        $log->setPlace('Coastal County');
        $log->addTag('flood');
        $log->addTag('hospital');
        $log->addEntity('Hospital A', 'Regional medical center');
        $log->addFact('Ambulances delayed due to bridge closure.');
        $log->setDescription('Severe flooding has isolated Hospital A from key supply routes.');

        $ref = new \ReflectionClass($log);
        $method = $ref->getMethod('buildHeader');
        $method->setAccessible(true);

        $output = $method->invoke($log);

        $this->assertStringContainsString('##Scene', $output);
        $this->assertStringContainsString('##Tags', $output);
        $this->assertStringContainsString('##Characters', $output);
        $this->assertStringContainsString('##Facts', $output);
        $this->assertStringContainsString('Hospital A', $output);
    }
}

