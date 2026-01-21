<?php

namespace BlueFission\Tests\Automata\Classification;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Classification\Result;

class ResultTest extends TestCase
{
    public function testAddTagAndScore(): void
    {
        $result = new Result();
        $result->addTag('damage', 0.9);
        $result->addTag('infrastructure', 0.7);

        $this->assertSame(0.9, $result->score('damage'));
        $this->assertSame(0.7, $result->score('infrastructure'));
    }

    public function testTopReturnsHighestScore(): void
    {
        $result = new Result();
        $result->addTag('damage', 0.9);
        $result->addTag('people', 0.4);

        $top = $result->top(1);

        $this->assertCount(1, $top);
        $this->assertSame('damage', $top[0]['label']);
    }
}
