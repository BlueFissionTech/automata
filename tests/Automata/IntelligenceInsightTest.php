<?php

namespace BlueFission\Tests\Automata;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Strategy\IStrategy;

class InsightStubStrategy implements IStrategy
{
    private string $name;
    private string $output;
    private float $accuracy;

    public function __construct(string $name, string $output, float $accuracy)
    {
        $this->name = $name;
        $this->output = $output;
        $this->accuracy = $accuracy;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function predict($input)
    {
        return $this->output;
    }

    public function accuracy(): float
    {
        return $this->accuracy;
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

class IntelligenceInsightTest extends TestCase
{
    public function testAnalyzeReturnsInsightsAndGestalt(): void
    {
        $intelligence = new Intelligence();

        $primary = new InsightStubStrategy('primary', 'alpha', 0.9);
        $secondary = new InsightStubStrategy('secondary', 'beta', 0.3);

        $intelligence->registerStrategyProfile($primary, 'primary', [
            'types' => [InputType::TEXT],
            'weight' => 3,
        ]);
        $intelligence->registerStrategyProfile($secondary, 'secondary', [
            'types' => [InputType::TEXT],
            'weight' => 1,
        ]);

        $report = $intelligence->analyze('hello world', [
            'strategy_budget' => 1,
        ]);

        $this->assertArrayHasKey('segments', $report);
        $this->assertArrayHasKey('insights', $report);
        $this->assertArrayHasKey('gestalt', $report);

        $this->assertCount(1, $report['segments']);
        $this->assertCount(1, $report['insights']);
        $this->assertSame('primary', $report['insights'][0]['strategy']);
        $this->assertSame(1, $report['gestalt']['segment_count']);
        $this->assertSame(1, $report['gestalt']['insight_count']);
    }

    public function testAnalyzeSegmentsMultipleInputs(): void
    {
        $intelligence = new Intelligence();

        $strategy = new InsightStubStrategy('solo', 'ok', 0.5);
        $intelligence->registerStrategyProfile($strategy, 'solo', [
            'types' => [InputType::TEXT],
            'weight' => 1,
        ]);

        $report = $intelligence->analyze(['first', 'second'], [
            'strategy_budget' => 1,
        ]);

        $this->assertCount(2, $report['segments']);
        $this->assertCount(2, $report['insights']);
        $this->assertSame(2, $report['gestalt']['segment_count']);
    }
}
