<?php

namespace BlueFission\Tests\Automata\Genetic;

use PHPUnit\Framework\TestCase;

class GeneticExampleTest extends TestCase
{
    public function testGeneticPolicyExampleProducesDeterministicShape(): void
    {
        $cmd = 'php examples/disaster_response/genetic_policy_optimization/run.php --seed=123';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Example script should exit successfully');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame(123, $data['seed'] ?? null);
        $this->assertSame(20, $data['generations'] ?? null);

        $this->assertArrayHasKey('history', $data);
        $this->assertIsArray($data['history']);
        $this->assertCount(20, $data['history']);

        $first = $data['history'][0];
        $this->assertArrayHasKey('generation', $first);
        $this->assertArrayHasKey('best_fitness', $first);
        $this->assertArrayHasKey('best_policy', $first);

        $policy = $first['best_policy'];
        $this->assertArrayHasKey('risk_weight', $policy);
        $this->assertArrayHasKey('time_weight', $policy);
        $this->assertArrayHasKey('capacity_bias', $policy);
    }
}

