<?php

namespace BlueFission\Tests\Automata\Services;

use BlueFission\Automata\Service\BenchmarkService;
use BlueFission\Automata\Strategy\IStrategy;
use PHPUnit\Framework\TestCase;

class BenchmarkServiceTest extends TestCase
{
    public function testBenchmarkTraining()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);
        $strategyMock->expects($this->once())
                     ->method('train');

        // Create instance of BenchmarkService
        $benchmarkService = new BenchmarkService();

        // Benchmark training
        $executionTime = $benchmarkService->benchmarkTraining($strategyMock, [], []);

        // Assert that execution time is a float and greater than or equal to 0
        $this->assertIsFloat($executionTime);
        $this->assertGreaterThanOrEqual(0, $executionTime);
    }

    public function testBenchmarkPrediction()
    {
        // Mock the IStrategy interface
        $strategyMock = $this->createMock(IStrategy::class);
        $strategyMock->expects($this->once())
                     ->method('predict')
                     ->willReturn('testOutput');

        // Create instance of BenchmarkService
        $benchmarkService = new BenchmarkService();

        // Benchmark prediction
        $result = $benchmarkService->benchmarkPrediction($strategyMock, 'inputData');

        // Assert that output is 'testOutput' and execution time is a float and greater than or equal to 0
        $this->assertEquals('testOutput', $result['output']);
        $this->assertIsFloat($result['executionTime']);
        $this->assertGreaterThanOrEqual(0, $result['executionTime']);
    }
}
