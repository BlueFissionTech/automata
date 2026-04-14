<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\Media\Processing\HandlerRegistry;
use PHPUnit\Framework\TestCase;

class HandlerRegistryTest extends TestCase
{
    public function testRegistryResolvesAvailableHandler(): void
    {
        $registry = new HandlerRegistry();

        $registry->register('ocr', new class {
            public function isAvailable(): bool { return false; }
            public function __invoke() { return 'skip'; }
        }, 'unavailable', 10);

        $registry->register('ocr', new class {
            public function isAvailable(): bool { return true; }
            public function __invoke() { return 'ok'; }
        }, 'available', 1);

        $handler = $registry->resolve('ocr');

        $this->assertNotNull($handler);
        $this->assertSame('ok', $handler());
    }
}
