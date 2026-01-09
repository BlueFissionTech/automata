<?php

namespace BlueFission\Tests\Automata\Markov;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Markov\DiscreteMarkov;

class InfrastructureMarkovTest extends TestCase
{
    private function roadMatrixMild(): array
    {
        return [
            'open' => [
                'open' => 0.7,
                'degraded' => 0.2,
                'closed' => 0.1,
            ],
            'degraded' => [
                'open' => 0.3,
                'degraded' => 0.5,
                'closed' => 0.2,
            ],
            'closed' => [
                'open' => 0.1,
                'degraded' => 0.3,
                'closed' => 0.6,
            ],
        ];
    }

    public function testSingleStepDistributionMatchesExpected(): void
    {
        $model = new DiscreteMarkov();
        $matrix = $this->roadMatrixMild();

        $current = ['open' => 1.0, 'degraded' => 0.0, 'closed' => 0.0];
        $next = $model->step($current, $matrix);

        $this->assertEqualsWithDelta(0.7, $next['open'], 1e-6);
        $this->assertEqualsWithDelta(0.2, $next['degraded'], 1e-6);
        $this->assertEqualsWithDelta(0.1, $next['closed'], 1e-6);
    }

    public function testMultipleStepsConvergeDeterministically(): void
    {
        $model = new DiscreteMarkov();
        $matrix = $this->roadMatrixMild();

        $current = ['open' => 1.0, 'degraded' => 0.0, 'closed' => 0.0];
        $next = $model->stepMany($current, $matrix, 3);

        $this->assertArrayHasKey('open', $next);
        $this->assertArrayHasKey('degraded', $next);
        $this->assertArrayHasKey('closed', $next);

        $sum = array_sum($next);
        $this->assertEqualsWithDelta(1.0, $sum, 1e-6);
    }
}
