<?php

namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Sense;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Behavioral\Behaviors\Event;
use PHPUnit\Framework\TestCase;

class SenseTest extends TestCase
{
    protected $sense;

    protected function setUp(): void
    {
        $this->sense = new Sense();
    }

    public function testReset()
    {
        $this->sense->reset();
        $reflection = new \ReflectionClass($this->sense);
        $settings = $reflection->getProperty('_settings');
        $settings->setAccessible(true);
        $map = $reflection->getProperty('_map');
        $map->setAccessible(true);
        $depth = $reflection->getProperty('_depth');
        $depth->setAccessible(true);

        $this->assertEquals($this->sense->_config, $settings->getValue($this->sense));
        $this->assertEmpty($map->getValue($this->sense));
        $this->assertEquals(-1, $depth->getValue($this->sense));
    }

    public function testSetPreparation()
    {
        $customPreparation = function ($input) {
            return explode(' ', $input);
        };

        $this->sense->setPreparation($customPreparation);

        $reflection = new \ReflectionClass($this->sense);
        $preparation = $reflection->getProperty('_preparation');
        $preparation->setAccessible(true);

        $this->assertEquals($customPreparation, $preparation->getValue($this->sense));
    }

    public function testPrepare()
    {
        $input = "This is a test.";
        $preparedInput = $this->sense->prepare($input);

        $this->assertIsArray($preparedInput);
        $this->assertNotEmpty($preparedInput);
    }

    public function testBuffer()
    {
        $data = "sample";
        $translation = $this->sense->buffer($data);

        $reflection = new \ReflectionClass($this->sense);
        $buffer = $reflection->getProperty('_buffer');
        $buffer->setAccessible(true);

        $this->assertContains($data, $buffer->getValue($this->sense));
        $this->assertIsInt($translation);
    }

    public function testInvoke()
    {
        $input = "This is a test sentence to process.";
        $this->sense->invoke($input);

        $reflection = new \ReflectionClass($this->sense);
        $map = $reflection->getProperty('_map');
        $map->setAccessible(true);
        $data = $map->getValue($this->sense)->data();

        $this->assertNotEmpty($data);
    }

    public function testSetParent()
    {
        $parent = new Sense();
        $this->sense->setParent($parent);

        $reflection = new \ReflectionClass($this->sense);
        $parentProperty = $reflection->getProperty('_parent');
        $parentProperty->setAccessible(true);

        $this->assertSame($parent, $parentProperty->getValue($this->sense));
    }

    public function testCallback()
    {
        $obj = new \stdClass();
        $this->sense->callback($obj);

        // Test is a placeholder for actual callback functionality
        $this->assertTrue(true);
    }

    public function testFocus()
    {
        $input = "This is a test sentence to process.";
        $this->sense->invoke($input);

        $reflection = new \ReflectionClass($this->sense);
        $focusMethod = $reflection->getMethod('focus');
        $focusMethod->setAccessible(true);
        $map = $reflection->getProperty('_map');
        $map->setAccessible(true);

        $data = $map->getValue($this->sense)->data();
        $focusMethod->invoke($this->sense, $data);

        $this->assertNotEmpty($data);
    }

    public function testTranslate()
    {
        $chunk = "sample";
        $translation = $this->sense->translate($chunk);

        $this->assertIsInt($translation);
    }

    public function testLongestCommonSubstring()
    {
        $words = ["testing", "tester", "tested"];
        $reflection = new \ReflectionClass($this->sense);
        $longestCommonSubstringMethod = $reflection->getMethod('longest_common_substring');
        $longestCommonSubstringMethod->setAccessible(true);

        $result = $longestCommonSubstringMethod->invoke($this->sense, $words);

        $this->assertEquals("test", $result);
    }

    public function testTweak()
    {
        $reflection = new \ReflectionClass($this->sense);
        $tweakMethod = $reflection->getMethod('tweak');
        $tweakMethod->setAccessible(true);

        $tweakMethod->invoke($this->sense);

        $settings = $reflection->getProperty('_settings');
        $settings->setAccessible(true);
        $this->assertNotEmpty($settings->getValue($this->sense));
    }
}
