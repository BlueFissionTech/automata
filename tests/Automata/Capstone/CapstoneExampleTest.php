<?php

namespace BlueFission\Tests\Automata\Capstone;

use PHPUnit\Framework\TestCase;

class CapstoneExampleTest extends TestCase
{
    public function testCapstoneDashboardProducesIntegratedOutput(): void
    {
        $cmd = 'php examples/disaster_response/capstone_multi_strategy_dashboard/run.php --seed=123 --steps=10';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Capstone script should exit successfully');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame(123, $data['seed'] ?? null);
        $this->assertSame(10, $data['steps'] ?? null);

        $this->assertArrayHasKey('best_policy', $data);
        $this->assertArrayHasKey('policy', $data['best_policy']);
        $this->assertArrayHasKey('risk_weight', $data['best_policy']['policy']);

        $this->assertArrayHasKey('timeline', $data);
        $this->assertCount(10, $data['timeline']);

        $this->assertArrayHasKey('summary', $data);
        $summary = $data['summary'];
        $this->assertArrayHasKey('total_logistics_utility', $summary);
        $this->assertArrayHasKey('total_hospital_utility', $summary);
        $this->assertArrayHasKey('decisions_logged', $summary);
    }
}

