<?php

namespace BlueFission\Tests\Automata\Language;

use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\StemmerLemmatizer;
use BlueFission\Automata\Language\Token;
use PHPUnit\Framework\TestCase;

class GrammarEnhancementsTest extends TestCase
{
    private function buildDslGrammar(bool $withBoundaries = false, int $statementCount = 1): Grammar
    {
        $rules = [
            'T_DOCUMENT' => $this->buildDocumentRules($withBoundaries, $statementCount),
            'STATEMENT' => [
                ['DEFINE'],
                ['SET'],
                ['COMMAND'],
            ],
            'DEFINE' => [
                ['T_DEFINE', 'T_SYMBOL'],
            ],
            'SET' => [
                ['T_SET', 'T_SYMBOL', 'T_TO', 'T_SYMBOL'],
            ],
            'COMMAND' => [
                ['T_VERB', 'T_SYMBOL', 'T_CONTEXT', 'T_SYMBOL', 'T_BINDING', 'T_SYMBOL'],
                ['T_VERB', 'T_SYMBOL', 'T_CONTEXT', 'T_SYMBOL'],
                ['T_VERB', 'T_SYMBOL', 'T_BINDING', 'T_SYMBOL'],
                ['T_VERB', 'T_SYMBOL'],
            ],
        ];

        $commands = [
            'T_DEFINE' => ['expects' => []],
            'T_SET' => ['expects' => []],
            'T_TO' => ['expects' => []],
            'T_VERB' => [
                'expects' => [],
                'aliasOf' => ['T_SYMBOL'],
                'soft' => true,
            ],
            'T_CONTEXT' => [
                'expects' => [],
                'soft' => true,
            ],
            'T_FLOW' => ['expects' => []],
            'T_LINK' => ['expects' => []],
            'T_BINDING' => ['expects' => []],
            'T_SYMBOL' => ['expects' => []],
        ];

        $tokens = [
            'define' => ['T_DEFINE'],
            'set' => ['T_SET'],
            'to' => ['T_TO'],
            'with' => ['T_CONTEXT', 'T_FLOW', 'T_LINK'],
            'via' => ['T_BINDING'],
            'given' => ['T_BINDING'],
            'open' => ['T_VERB'],
            'next' => ['T_VERB'],
            'route' => ['T_SYMBOL'],
            'file' => ['T_SYMBOL'],
            'system' => ['T_SYMBOL'],
            'net' => ['T_SYMBOL'],
        ];

        $grammar = new Grammar(new StemmerLemmatizer(), $rules, $commands, $tokens);
        if ($withBoundaries) {
            $grammar->enableStatementBoundaries(true, Token::STATEMENT);
        }

        return $grammar;
    }

    private function buildDocumentRules(bool $withBoundaries, int $statementCount): array
    {
        if (!$withBoundaries) {
            return [['STATEMENT']];
        }

        $rules = [];
        if ($statementCount < 1) {
            $statementCount = 1;
        }

        $sequence = [];
        for ($i = 0; $i < $statementCount; $i++) {
            $sequence[] = 'STATEMENT';
            if ($i < ($statementCount - 1)) {
                $sequence[] = Token::STATEMENT;
            }
        }

        $rules[] = $sequence;

        return $rules;
    }

    public function testSoftKeywordFallbackInIdentifierPosition(): void
    {
        $grammar = $this->buildDslGrammar();
        $tokens = $grammar->tokenize('define next');

        $this->assertNotEmpty($tokens);
        $this->assertContains('T_VERB', $tokens[1]['classifications']);
        $this->assertContains('T_SYMBOL', $tokens[1]['classifications']);

        $tree = $grammar->parse($tokens);
        $this->assertSame('T_DOCUMENT', $tree['type']);
    }

    public function testSoftKeywordInValuePosition(): void
    {
        $grammar = $this->buildDslGrammar();
        $tokens = $grammar->tokenize('set route to next');

        $this->assertNotEmpty($tokens);
        $this->assertContains('T_SYMBOL', $tokens[3]['classifications']);

        $tree = $grammar->parse($tokens);
        $this->assertSame('T_DOCUMENT', $tree['type']);
    }

    public function testVerbAliasingInIdentifierSlot(): void
    {
        $grammar = $this->buildDslGrammar();
        $tokens = $grammar->tokenize('define open');

        $this->assertNotEmpty($tokens);
        $this->assertContains('T_VERB', $tokens[1]['classifications']);
        $this->assertContains('T_SYMBOL', $tokens[1]['classifications']);

        $tree = $grammar->parse($tokens);
        $this->assertSame('T_DOCUMENT', $tree['type']);
    }

    public function testStatementBoundariesSeparateStatements(): void
    {
        $grammar = $this->buildDslGrammar(true, 3);
        $tokens = $grammar->tokenize("define next\nset route to next\nopen file");

        $this->assertNotEmpty($tokens);
        $this->assertContains(Token::STATEMENT, $tokens[2]['classifications']);

        $tree = $grammar->parse($tokens);
        $this->assertSame('T_DOCUMENT', $tree['type']);
    }

    public function testMultiClassTokenPreservedInGrammar(): void
    {
        $grammar = $this->buildDslGrammar();
        $tokens = $grammar->tokenize('open file with system via net');

        $this->assertNotEmpty($tokens);
        $this->assertContains('T_CONTEXT', $tokens[2]['classifications']);
        $this->assertContains('T_FLOW', $tokens[2]['classifications']);
        $this->assertContains('T_LINK', $tokens[2]['classifications']);

        $tree = $grammar->parse($tokens);
        $this->assertSame('T_DOCUMENT', $tree['type']);
    }
}
