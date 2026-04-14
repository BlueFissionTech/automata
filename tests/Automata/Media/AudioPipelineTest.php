<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Audio\AudioPipeline;
use PHPUnit\Framework\TestCase;

class AudioPipelineTest extends TestCase
{
    public function testAudioPipelineUsesHandlers(): void
    {
        $item = new MediaItem(InputType::AUDIO, 'binary', ['size' => 123]);
        $pipeline = new AudioPipeline();

        $result = $pipeline->process($item, null, [
            'volume_normalizer' => function () {
                return 0.5;
            },
            'speech_to_text' => function () {
                return 'hello';
            },
            'audio_event_detector' => function () {
                return ['speech'];
            },
        ]);

        $this->assertSame(0.5, $result->features()['volume_level'] ?? null);
        $this->assertSame('hello', $result->meta()['transcript'] ?? null);
        $this->assertSame(['speech'], $result->features()['events'] ?? null);
        $this->assertNotEmpty($result->segments());
    }
}
