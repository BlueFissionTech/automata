<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Image\ImagePipeline;
use PHPUnit\Framework\TestCase;

class ImagePipelineTest extends TestCase
{
    public function testImagePipelineUsesHandlers(): void
    {
        $item = new MediaItem(InputType::IMAGE, 'binary', ['width' => 10, 'height' => 5]);
        $pipeline = new ImagePipeline();

        $result = $pipeline->process($item, null, [
            'ocr' => function () {
                return 'sample text';
            },
            'boundary_detector' => function () {
                return [['x' => 1, 'y' => 2, 'w' => 3, 'h' => 4]];
            },
        ]);

        $this->assertSame('sample text', $result->meta()['ocr_text'] ?? null);
        $this->assertSame(10, $result->features()['width'] ?? null);
        $this->assertSame(5, $result->features()['height'] ?? null);
        $this->assertNotEmpty($result->segments());
    }
}
