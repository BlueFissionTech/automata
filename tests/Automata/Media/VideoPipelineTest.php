<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Video\VideoPipeline;
use PHPUnit\Framework\TestCase;

class VideoPipelineTest extends TestCase
{
    public function testVideoPipelineUsesHandlers(): void
    {
        $item = new MediaItem(InputType::VIDEO, 'binary');
        $pipeline = new VideoPipeline();

        $result = $pipeline->process($item, null, [
            'frame_extractor' => function () {
                return [['index' => 0, 'path' => 'frame_0001.png']];
            },
            'timeline_analyzer' => function () {
                return ['entities' => ['car']];
            },
        ]);

        $this->assertSame([['index' => 0, 'path' => 'frame_0001.png']], $result->features()['frames'] ?? null);
        $this->assertSame(['entities' => ['car']], $result->features()['timeline'] ?? null);
        $this->assertNotEmpty($result->segments());
    }
}
