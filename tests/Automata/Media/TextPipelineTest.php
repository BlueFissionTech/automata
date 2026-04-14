<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Text\TextPipeline;
use PHPUnit\Framework\TestCase;

class TextPipelineTest extends TestCase
{
    public function testTextPipelineBuildsTokensAndBag(): void
    {
        $item = new MediaItem(InputType::TEXT, 'Hello world hello');
        $pipeline = new TextPipeline();

        $result = $pipeline->process($item);
        $tokens = $result->tokens();
        $features = $result->features();
        $bag = $features['bag_of_words'] ?? [];

        $this->assertContains('hello', $tokens);
        $this->assertSame(2, $bag['hello'] ?? 0);
        $this->assertNotEmpty($result->segments());
    }
}
