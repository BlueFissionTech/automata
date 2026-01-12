<?php
namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Sensory\InputArray;
use PHPUnit\Framework\TestCase;

class InputArrayTest extends TestCase
{
    public function testCreateRegistersInputAndSense(): void
    {
        $inputArray = new InputArray('test-sensor');
        $inputArray->create(InputType::TEXT);

        $reflection = new \ReflectionObject($inputArray);
        $inputsProp = $reflection->getProperty('_inputs');
        $inputsProp->setAccessible(true);
        $inputs = $inputsProp->getValue($inputArray);

        $sensesProp = $reflection->getProperty('_senses');
        $sensesProp->setAccessible(true);
        $senses = $sensesProp->getValue($inputArray);

        $this->assertArrayHasKey(InputType::TEXT, $inputs);
        $this->assertArrayHasKey(InputType::TEXT, $senses);
    }
}
