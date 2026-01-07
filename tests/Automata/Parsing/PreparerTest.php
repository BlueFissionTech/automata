<?php

namespace BlueFission\Tests\Automata\Parsing;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\FillIn;
use BlueFission\Automata\Parsing\Preparers\LLMPreparer;
use BlueFission\Automata\Parsing\Preparers\ToolPreparer;
use BlueFission\Automata\Parsing\Elements\PromptElement;
use BlueFission\Parsing\Element;

class LLMPreparerElement extends Element
{
    public $driver;

    public function __construct()
    {
        parent::__construct('eval', '', '', []);
    }

    public function setDriver($driver): void
    {
        $this->driver = $driver;
    }
}

class ToolPreparerElement extends Element
{
    public array $tools = [];

    public function __construct()
    {
        parent::__construct('eval', '', '', []);
    }

    public function addTool(string $name, $tool): void
    {
        $this->tools[$name] = $tool;
    }
}

class PreparerTest extends TestCase
{
    public function testLLMPreparerSetsDriverFromFillIn(): void
    {
        $fakeLlm = new \stdClass();
        $fillIn  = new FillIn($fakeLlm, 'Test {=expression}');

        $preparer = new LLMPreparer($fillIn);
        $element  = new LLMPreparerElement();

        $preparer->prepare($element);

        $this->assertSame($fakeLlm, $element->driver);
    }

    public function testToolPreparerAddsToolsToElement(): void
    {
        $tools = [
            't1' => new \stdClass(),
            't2' => new \ArrayObject(),
        ];

        $preparer = new ToolPreparer($tools);
        $element  = new ToolPreparerElement();

        $preparer->prepare($element);

        $this->assertArrayHasKey('t1', $element->tools);
        $this->assertArrayHasKey('t2', $element->tools);
    }

    public function testPromptElementDescriptionIsRenderable(): void
    {
        $promptElement = new PromptElement('eval', '', '', []);

        // Initialize the inherited $name property to avoid uninitialized access.
        $ref = new \ReflectionProperty(PromptElement::class, 'name');
        $ref->setAccessible(true);
        $ref->setValue($promptElement, 'test_expression');

        $description = $promptElement->getDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('Evalute the expression', $description);
    }
}
