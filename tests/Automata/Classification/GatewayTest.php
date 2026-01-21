<?php

namespace BlueFission\Tests\Automata\Classification;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;

class GatewayStubClassifierA implements IClassifier
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $result->addTag('damage', 0.9);
        return $result;
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

class GatewayStubClassifierB implements IClassifier
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $result->addTag('people', 0.6);
        return $result;
    }

    public function accuracy(): float
    {
        return 0.4;
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

class GatewayTest extends TestCase
{
    public function testGatewayMergesClassifierResults(): void
    {
        $gateway = new Gateway();
        $gateway->registerClassifier(new GatewayStubClassifierA(), 'a');
        $gateway->registerClassifier(new GatewayStubClassifierB(), 'b');

        $result = $gateway->classify(['id' => 'dr-001']);

        $tags = $result->tags();
        $this->assertArrayHasKey('damage', $tags);
        $this->assertArrayHasKey('people', $tags);
        $this->assertSame(0.9, $result->score('damage'));
    }
}
