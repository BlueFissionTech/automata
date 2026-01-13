<?php

namespace BlueFission\Tests\Automata\Language;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\Language\ContractionNormalizer;

class ContractionNormalizerTest extends TestCase
{
    public function testNormalizeExpandsCommonContractions(): void
    {
        $input = "I'm sure that's fine, but I can't go.";
        $expected = "I am sure that is fine, but I cannot go.";

        $this->assertSame($expected, ContractionNormalizer::normalize($input));
    }

    public function testNormalizePreservesUppercaseSuffixes(): void
    {
        $input = "YOU'RE READY; SHE'LL GO, WE'D STAY.";
        $expected = "YOU ARE READY; SHE WILL GO, WE WOULD STAY.";

        $this->assertSame($expected, ContractionNormalizer::normalize($input));
    }
}
