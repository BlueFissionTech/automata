<?php

namespace BlueFission\Tests\Examples\DisasterResponse;

use PHPUnit\Framework\TestCase;

class GridworldDemoTest extends TestCase
{
    public function testGridworldDemoProducesTimeline(): void
    {
        $cmd = 'php examples/generic/disaster_response/gridworld_demo.php --seed=11 --ticks=6';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Gridworld demo should exit successfully');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame(11, $data['seed'] ?? null);
        $this->assertSame(6, $data['ticks'] ?? null);
        $this->assertArrayHasKey('timeline', $data);
        $this->assertCount(6, $data['timeline']);
    }
}
