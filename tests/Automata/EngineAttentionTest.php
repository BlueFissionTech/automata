<?php

namespace BlueFission\Tests\Automata;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Engine;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Sensory\Sense;
use BlueFission\Automata\Strategy\IStrategy;

class EngineAttentionStubStrategy implements IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function predict($input)
    {
        return ['result' => 'ok'];
    }

    public function accuracy(): float
    {
        return 0.5;
    }

    public function saveModel(string $path): bool
    {
        return true;
    }

    public function loadModel(string $path): bool
    {
        return true;
    }
}

class EngineAttentionTest extends TestCase
{
    public function testAttentionProfileRetainsOnlyNamedStatisticsWithoutChangingValues(): void
    {
        $engine = new class extends Engine {
            public function profile($data): array
            {
                return $this->buildAttentionProfile(new Sense($this), $data, 0.5);
            }
        };
        $this->assertSame(['count' => 0, 'mean1' => null, 'variance1' => 1.5, 'std1' => 0.0],
            $engine->profile(['count' => 0, 'mean1' => null, 'variance1' => 1.5,
                'std1' => 0.0, 'private' => 'excluded'])['stats']);
        $this->assertSame([], $engine->profile('not statistics')['stats']);
    }

    public function testAnalyzeWithAttentionReturnsProfile(): void
    {
        $engine = new Engine();
        $strategy = new EngineAttentionStubStrategy();

        $engine->registerStrategyProfile($strategy, 'attention', [
            'types' => [InputType::TEXT],
            'weight' => 1,
        ]);

        $report = $engine->analyzeWithAttention('attention input', [
            'strategy_budget' => 1,
        ]);

        $this->assertArrayHasKey('attention', $report);
        $this->assertArrayHasKey('score', $report['attention']);
        $this->assertArrayHasKey('segments', $report);
        $this->assertArrayHasKey('insights', $report);
    }
}
