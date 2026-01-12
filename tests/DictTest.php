<?php

namespace BlueFission\Tests;

use PHPUnit\Framework\TestCase;
use BlueFission\Dict;
use Ds\Map;

class DictTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('ds')) {
            $this->markTestSkipped('The ds extension is required for Dict tests.');
        }
    }

    public function testCanInstantiateDict(): void
    {
        $dict = new Dict();
        $this->assertInstanceOf(Dict::class, $dict);
        $dict->cast();

        $ref = new \ReflectionClass($dict);
        $prop = $ref->getProperty('_data');
        $prop->setAccessible(true);
        $this->assertInstanceOf(Map::class, $prop->getValue($dict));
    }

    public function testPutAndGetElement(): void
    {
        $dict = new Dict();
        $dict->put('key', 'value');

        $this->assertEquals('value', $dict->get('key'));
    }

    public function testUpdateElement(): void
    {
        $dict = new Dict();
        $dict->put('key', 'initial');
        $dict->put('key', 'updated');

        $this->assertEquals('updated', $dict->get('key'));
    }

    public function testRemoveElement(): void
    {
        $dict = new Dict();
        $dict->put('key', 'value');
        $dict->remove('key');

        $this->assertNull($dict->get('key'));
    }

    public function testHasKey(): void
    {
        $dict = new Dict();
        $dict->put('key', 'value');

        $this->assertTrue($dict->hasKey('key'));
        $this->assertFalse($dict->hasKey('nonexistent'));
    }

    public function testHasValue(): void
    {
        $dict = new Dict();
        $dict->put('key', 'unique');

        $this->assertTrue($dict->hasValue('unique'));
        $this->assertFalse($dict->hasValue('nonexistent'));
    }

    public function testClearDict(): void
    {
        $dict = new Dict();
        $dict->put('key1', 'value1');
        $dict->put('key2', 'value2');
        $dict->clear();

        $this->assertEquals(0, $dict->count());
    }

    public function testCountElementsInDict(): void
    {
        $dict = new Dict();
        $dict->put('key1', 'value1');
        $dict->put('key2', 'value2');
        $dict->put('key3', 'value3');

        $this->assertEquals(3, $dict->count());
    }
}
