<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\System\CommandLocator;
use PHPUnit\Framework\TestCase;

class CommandLocatorTest extends TestCase
{
    public function testFindReturnsNullForMissingBinary(): void
    {
        $path = CommandLocator::find('definitely-not-a-binary');
        $this->assertNull($path);
    }
}
