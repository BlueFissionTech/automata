<?php

namespace BlueFission\Tests\Automata;

use BlueFission\Automata\Intelligence;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Service\BenchmarkService;
use PHPUnit\Framework\TestCase;

class IntelligenceTest extends TestCase
{
    private $intelligence;

    protected function setUp(): void
    {
        $this->intelligence = new Intelligence();
    }

    public function testRegisterStrategy()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);

        // Register the strategy
        $this->intelligence->registerStrategy($strategyMock, 'testStrategy');

        // Get the strategies collection using reflection
        $reflection = new \ReflectionClass($this->intelligence);
        $strategiesProperty = $reflection->getProperty('_strategies');
        $strategiesProperty->setAccessible(true);
        $strategies = $strategiesProperty->getValue($this->intelligence);

        // Assert that the strategy is in the collection
        $this->assertInstanceOf(OrganizedCollection::class, $strategies);
        $this->assertTrue($strategies->has('testStrategy'));
    }

    public function testTrain()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);
        $strategyMock->expects($this->once())
                     ->method('train');
        $strategyMock->expects($this->once())
                     ->method('accuracy')
                     ->willReturn(0.9);

        // Register the strategy
        $this->intelligence->registerStrategy($strategyMock, 'testStrategy');

        // Train the strategies
        $this->intelligence->train([], []);

        // Get the strategies collection using reflection
        $reflection = new \ReflectionClass($this->intelligence);
        $strategiesProperty = $reflection->getProperty('_strategies');
        $strategiesProperty->setAccessible(true);
        $strategies = $strategiesProperty->getValue($this->intelligence);

        // Assert that the strategy weight is updated
        $weight = $strategies->weight('testStrategy');
        $this->assertGreaterThan(0, $weight);
    }

    public function testPredict()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);
        $strategyMock->expects($this->once())
                     ->method('predict')
                     ->willReturn('testPrediction');

        // Register the strategy
        $this->intelligence->registerStrategy($strategyMock, 'testStrategy');

        // Predict using the strategy
        $prediction = $this->intelligence->predict('inputData');

        // Assert that the prediction is 'testPrediction'
        $this->assertEquals('testPrediction', $prediction);
    }

    public function testApprovePrediction()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);
        $strategyMock->expects($this->once())
                     ->method('predict')
                     ->willReturn('testPrediction');

        // Register the strategy
        $this->intelligence->registerStrategy($strategyMock, 'testStrategy');

        // Make a prediction to set the last strategy used
        $this->intelligence->predict('inputData');

        // Approve the prediction
        $this->intelligence->approvePrediction();

        // Get the strategies collection using reflection
        $reflection = new \ReflectionClass($this->intelligence);
        $strategiesProperty = $reflection->getProperty('_strategies');
        $strategiesProperty->setAccessible(true);
        $strategies = $strategiesProperty->getValue($this->intelligence);

        // Assert that the strategy weight is increased
        $weight = $strategies->weight('testStrategy');
        $this->assertGreaterThan(1, $weight);
    }

    public function testRejectPrediction()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);
        $strategyMock->expects($this->once())
                     ->method('predict')
                     ->willReturn('testPrediction');

        // Register the strategy
        $this->intelligence->registerStrategy($strategyMock, 'testStrategy');

        // Make a prediction to set the last strategy used
        $this->intelligence->predict('inputData');

        // Reject the prediction
        $this->intelligence->rejectPrediction();

        // Get the strategies collection using reflection
        $reflection = new \ReflectionClass($this->intelligence);
        $strategiesProperty = $reflection->getProperty('_strategies');
        $strategiesProperty->setAccessible(true);
        $strategies = $strategiesProperty->getValue($this->intelligence);

        // Assert that the strategy weight is decreased
        $weight = $strategies->weight('testStrategy');
        $this->assertLessThan(1, $weight);
    }
}
