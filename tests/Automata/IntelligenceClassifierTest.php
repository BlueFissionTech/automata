<?php

namespace BlueFission\Tests\Automata;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Context;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Arr;

class ClassifierStubStrategy implements IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function predict($input)
    {
        return 'ok';
    }

    public function accuracy(): float
    {
        return 0.8;
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

class IntentAnalyzerStub implements IAnalyzer
{
    public function analyze(string $input, Context $context, array $keywords): Arr
    {
        return Arr::make(['stub_intent' => 0.9]);
    }
}

class IntelligenceClassifierTest extends TestCase
{
    public function testAnalyzeAppliesClassifiers(): void
    {
        $intelligence = new Intelligence();
        $intelligence->registerStrategyProfile(new ClassifierStubStrategy(), 'classifier', [
            'types' => [InputType::TEXT],
            'weight' => 1,
        ]);

        $intelligence->registerIntentAnalyzer(new IntentAnalyzerStub());
        $intelligence->registerStructureClassifier(function ($payload) {
            return ['structure' => 'statement'];
        });
        $intelligence->registerContextProvider(function () {
            return ['topic' => 'demo'];
        });

        $report = $intelligence->analyze('hello', [
            'strategy_budget' => 1,
            'context' => ['source' => 'test'],
        ]);

        $meta = $report['segments'][0]['meta'];
        $gestalt = $report['gestalt'];

        $this->assertArrayHasKey('intent', $meta);
        $this->assertArrayHasKey('structure', $meta);
        $this->assertArrayHasKey('context', $meta);
        $this->assertSame('demo', $meta['context'][0]['topic']);

        $this->assertArrayHasKey('intent_scores', $gestalt);
        $this->assertArrayHasKey('structure_scores', $gestalt);
        $this->assertArrayHasKey('context_scores', $gestalt);
        $this->assertArrayHasKey('stub_intent', $gestalt['intent_scores']);
        $this->assertEqualsWithDelta(0.9, $gestalt['intent_scores']['stub_intent'], 0.0001);
        $this->assertSame(1.0, $gestalt['structure_scores']['structure:statement']);
        $this->assertSame(1.0, $gestalt['context_scores']['topic:demo']);
    }
}
