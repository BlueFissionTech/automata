<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Documenter;

class DocumenterTest extends TestCase
{
    public function testUnexpectedTokenThrowsException(): void
    {
        $documenter = new Documenter();

        // Simple rule: handle T_ENTITY tokens and expect punctuation next.
        $documenter->addRule(['T_ENTITY'], function (array $cmd, $statement): void {
            $statement->field('subject', $cmd['match']);
        });

        // First token is accepted and sets expectations to T_PUNCTUATION.
        $first = [
            'match' => 'Person',
            'classifications' => ['T_ENTITY'],
            'expects' => [
                'T_ENTITY' => ['T_PUNCTUATION'],
            ],
        ];

        $documenter->push($first);

        // Second token violates expectations (classification T_OPERATOR
        // while the documenter expects T_PUNCTUATION), which should
        // trigger an exception from isExpected().
        $this->expectException(\Exception::class);

        $bad = [
            'match' => 'runs',
            'classifications' => ['T_OPERATOR'],
            'expects' => [
                'T_OPERATOR' => ['T_ENTITY'],
            ],
        ];

        $documenter->push($bad);
    }
}
