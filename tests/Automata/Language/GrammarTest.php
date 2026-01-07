<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\StemmerLemmatizer;

class GrammarTest extends TestCase
{
    private Grammar $grammar;

    protected function setUp(): void
    {
        // Minimal grammar configuration sufficient to tokenize and parse
        // a simple "hello." sentence.
        $rules = [
            'T_DOCUMENT' => [
                ['T_ENTITY', 'T_PUNCTUATION'],
            ],
        ];

        $commands = [
            'T_ENTITY' => [
                'expects' => ['T_PUNCTUATION'],
            ],
            'T_PUNCTUATION' => [
                'expects' => [],
            ],
        ];

        $tokens = [
            'hello' => ['T_ENTITY'],
            '.'     => ['T_PUNCTUATION'],
        ];

        $this->grammar = new Grammar(new StemmerLemmatizer(), $rules, $commands, $tokens);
    }

    public function testTokenizeKnownSentenceProducesTokens(): void
    {
        $tokens = $this->grammar->tokenize('hello.');

        $this->assertNotEmpty($tokens);
        $this->assertSame('hello', $tokens[0]['match']);
        $this->assertContains('T_ENTITY', $tokens[0]['classifications']);
    }

    public function testParseProducesDocumentRoot(): void
    {
        $tokens = $this->grammar->tokenize('hello.');
        $tree = $this->grammar->parse($tokens);

        $this->assertIsArray($tree);
        $this->assertSame('T_DOCUMENT', $tree['type']);
        $this->assertNotEmpty($tree['children']);
    }
}
