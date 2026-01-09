<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\Preparer;

class TokenizerTest extends TestCase
{
    public function testPreparerTokenizesCommandString(): void
    {
        $preparer = new Preparer();

        $input = "& TYPE Person EXPECTS {'name'}";
        $tokens = $preparer->tokenize($input);

        $this->assertIsArray($tokens);
        $this->assertNotEmpty($tokens);

        // Noise words and punctuation are stripped; core symbols remain.
        $this->assertContains('type', $tokens);
        $this->assertContains('person', $tokens);
        $this->assertContains('expects', $tokens);
    }
}
