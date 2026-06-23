<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\StatementClassifier;
use PHPUnit\Framework\TestCase;

class StatementClassifierTest extends TestCase
{
    public function testExtractClassificationUsesStrictAllowlist(): void
    {
        $classifier = new StatementClassifier();
        $method = new \ReflectionMethod(StatementClassifier::class, 'extractClassification');
        $method->setAccessible(true);

        $this->assertSame('question', $method->invoke($classifier, ['text' => ' question ']));
        $this->assertNull($method->invoke($classifier, ['text' => 'questionable']));
    }
}
