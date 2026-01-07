<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Interpreter;
use BlueFission\Automata\Language\Grammar;
use BlueFission\Automata\Language\StemmerLemmatizer;
use BlueFission\Automata\Language\Documenter;
use BlueFission\Automata\Language\Walker;

class InterpreterTest extends TestCase
{
    private Interpreter $interpreter;

    protected function setUp(): void
    {
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

        $grammar = new Grammar(new StemmerLemmatizer(), $rules, $commands, $tokens);

        $documenter = new Documenter();
        $documenter->addRule(['T_ENTITY'], function (array $cmd, $statement): void {
            $statement->field('subject', $cmd['match']);
        });

        $walker = new Walker();

        $this->interpreter = new Interpreter($grammar, $documenter, $walker);
    }

    public function testInterpreterRecognizesValidCode(): void
    {
        $this->assertTrue($this->interpreter->isValid('hello.'));
    }

    public function testInterpreterRunProducesTree(): void
    {
        $this->interpreter->run('hello.');
        $tree = $this->interpreter->getTree();

        $this->assertIsArray($tree);
    }
}
