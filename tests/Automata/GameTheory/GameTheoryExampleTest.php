<?php

namespace BlueFission\Tests\Automata\GameTheory;

use PHPUnit\Framework\TestCase;

class GameTheoryExampleTest extends TestCase
{
    public function testAllocationExampleProducesExpectedProfiles(): void
    {
        $cmd = 'php examples/disaster_response/game_theory_allocation/run.php --seed=123';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, 'Example script should exit successfully');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertSame(123, $data['seed'] ?? null);
        $this->assertArrayHasKey('profiles', $data);
        $this->assertCount(4, $data['profiles']);

        $first = $data['profiles'][0];
        $this->assertSame('Conservative', $first['actions']['logistics'] ?? null);
        $this->assertSame('Conservative', $first['actions']['hospital'] ?? null);
        $this->assertSame(8, $first['payoff']['logistics'] ?? null);
        $this->assertSame(7, $first['payoff']['hospital'] ?? null);
    }
}

