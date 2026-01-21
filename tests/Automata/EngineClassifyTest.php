<?php

namespace BlueFission\Tests\Automata;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Engine;
use BlueFission\Automata\Strategy\IStrategy;

class EnginePredictStubStrategy implements IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function predict($input)
    {
        return 'predicted';
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

class EngineGuessStubStrategy
{
    private $processed = false;

    public function process($input): void
    {
        $this->processed = true;
    }

    public function guess()
    {
        return $this->processed ? 'guessed' : null;
    }
}

class EngineClassifyTest extends TestCase
{
    public function testClassifyFallsBackToInputWithoutStrategies(): void
    {
        $engine = new Engine();

        $this->assertSame('input', $engine->classify('input'));
    }

    public function testClassifyUsesPredictStrategy(): void
    {
        $engine = new Engine();
        $engine->registerStrategy(new EnginePredictStubStrategy(), 'predictor');

        $this->assertSame('predicted', $engine->classify('input'));
    }

    public function testClassifyUsesGuessStrategy(): void
    {
        $engine = new Engine();
        $engine->addProcessor('guess', new EngineGuessStubStrategy());

        $this->assertSame('guessed', $engine->classify('input'));
    }
}
