<?php

namespace BlueFission\Tests\Automata\Simulation;

use PHPUnit\Framework\TestCase;

class SimulationExampleTest extends TestCase
{
    public function testSimulationWorldExampleProducesTimeline(): void
    {
        $cmd = 'php examples/disaster_response/simulation_world/run.php --seed=123';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Example script should exit successfully');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame(123, $data['seed'] ?? null);
        $this->assertSame(15, $data['ticks'] ?? null);

        $this->assertArrayHasKey('timeline', $data);
        $this->assertCount(15, $data['timeline']);

        $first = $data['timeline'][0];
        $this->assertSame(0, $first['tick'] ?? null);
        $this->assertArrayHasKey('road_condition_index', $first);
        $this->assertArrayHasKey('demand_level', $first);
    }
}

