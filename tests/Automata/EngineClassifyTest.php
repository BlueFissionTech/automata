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

class EngineScalarStubStrategy
{
    public int $calls = 0;

    public function __construct(private mixed $result)
    {
    }

    public function predict($input): mixed
    {
        $this->calls++;

        return $this->result;
    }
}

class EngineClockFixture extends Engine
{
    public function __construct(private array $ticks)
    {
        parent::__construct();
    }

    protected function clockSeconds(): float
    {
        return array_shift($this->ticks);
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

    public function testNullGuessContinuesToLaterPredictor(): void
    {
        $first = new class {
            public int $calls = 0;

            public function process($input): void
            {
                $this->calls++;
            }

            public function guess(): mixed
            {
                return null;
            }
        };
        $second = new EngineScalarStubStrategy('answer');
        $engine = new Engine();
        $engine->addProcessor('first', $first)->addProcessor('second', $second);

        $this->assertSame('answer', $engine->classify('nonempty-input'));
        $this->assertSame(1, $first->calls);
        $this->assertSame(1, $second->calls);
    }

    public function testFirstFalseOrZeroPredictionIsNotOverwritten(): void
    {
        foreach ([false, 0] as $prediction) {
            $first = new EngineScalarStubStrategy($prediction);
            $second = new EngineScalarStubStrategy('later');
            $engine = new Engine();
            $engine->addProcessor('first', $first)->addProcessor('second', $second);

            $this->assertSame($prediction, $engine->classify('nonempty-input'));
            $this->assertSame(1, $first->calls);
            $this->assertSame(0, $second->calls);
        }
    }

    public function testInputIsRetainedOnlyWhenEveryPredictorReturnsNull(): void
    {
        $first = new EngineScalarStubStrategy(null);
        $second = new EngineScalarStubStrategy(null);
        $engine = new Engine();
        $engine->addProcessor('first', $first)->addProcessor('second', $second);

        $this->assertSame('original', $engine->classify('original'));
        $this->assertSame(1, $first->calls);
        $this->assertSame(1, $second->calls);
    }

    public function testTimeAndAverageUseElapsedSeconds(): void
    {
        $engine = new EngineClockFixture([10.0, 10.25, 20.0, 20.5]);
        $engine->addProcessor('predictor', new EngineScalarStubStrategy('answer'));

        $engine->classify('first');
        $this->assertEqualsWithDelta(0.25, $engine->time(), 0.000001);

        $engine->classify('second');
        $this->assertEqualsWithDelta(0.5, $engine->time(), 0.000001);
        $this->assertEqualsWithDelta(0.375, $engine->stats()['avgtime'], 0.000001);
    }
}
