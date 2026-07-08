<?php

namespace BlueFission\Tests;

use BlueFission\Deq;
use BlueFission\Dict;
use BlueFission\Pile;
use BlueFission\Pri;
use BlueFission\Set;
use BlueFission\Vec;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ChroniclerStructureResolutionTest extends TestCase
{
    public function testRootDataStructuresResolveFromChronicler(): void
    {
        $classes = [
            Vec::class,
            Set::class,
            Dict::class,
            Deq::class,
            Pri::class,
            Pile::class,
        ];

        foreach ($classes as $class) {
            $file = str_replace('\\', '/', (new ReflectionClass($class))->getFileName());

            $this->assertStringContainsString('/vendor/bluefission/chronicler/src/', $file, $class);
        }
    }
}
