<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Walker;
use BlueFission\Automata\Language\Statement;

class WalkerTest extends TestCase
{
    public function testWalkerCanCollectSimpleStatement(): void
    {
        $statement = new Statement();
        $statement->field('subject', 'A');
        $statement->field('behavior', 'does');
        $statement->field('object', 'B');

        $walker = new Walker();
        $walker->addStatement($statement);
        $walker->process();

        $log = $walker->log();

        $this->assertCount(1, $log);
        $entry = $log[0];
        $this->assertSame('A', $entry['subject']);
        $this->assertSame('does', $entry['behavior']);
        $this->assertSame('B', $entry['object']);
    }
}
