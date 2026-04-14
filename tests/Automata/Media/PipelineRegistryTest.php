<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\Context;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\Image\ImagePipeline;
use PHPUnit\Framework\TestCase;

class PipelineRegistryTest extends TestCase
{
    public function testPipelineUsesRegistryByDefault(): void
    {
        $registry = new HandlerRegistry();
        $registry->register('ocr', new class {
            public function isAvailable(): bool { return true; }
            public function __invoke(MediaItem $item, Context $context, array $options = [])
            {
                return 'registry text';
            }
        });

        $pipeline = new ImagePipeline();
        $pipeline->setRegistry($registry);

        $item = new MediaItem(InputType::IMAGE, 'binary');
        $result = $pipeline->process($item);

        $this->assertSame('registry text', $result->meta()['ocr_text'] ?? null);
    }
}
