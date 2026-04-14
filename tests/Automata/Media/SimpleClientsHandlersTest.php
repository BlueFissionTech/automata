<?php

namespace BlueFission\Tests\Automata\Media;

use BlueFission\Automata\Context;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Audio\SimpleClientsTranscriptionHandler;
use BlueFission\Automata\Media\Processing\Image\SimpleClientsOcrHandler;
use BlueFission\Automata\Media\Processing\Video\SimpleClientsVideoAnalysisHandler;
use PHPUnit\Framework\TestCase;

class SimpleClientsHandlersTest extends TestCase
{
    public function testOcrHandlerUsesInjectedClient(): void
    {
        $client = new class {
            public function analyze($input, array $options = [])
            {
                return ['text' => 'hello'];
            }
        };

        $handler = new SimpleClientsOcrHandler($client);
        $item = new MediaItem(InputType::IMAGE, 'binary');
        $text = $handler($item, new Context(), []);

        $this->assertSame('hello', $text);
    }

    public function testTranscriptionHandlerUsesInjectedClient(): void
    {
        $client = new class {
            public function transcribe($input, array $options = [])
            {
                return ['text' => 'voice'];
            }
        };

        $handler = new SimpleClientsTranscriptionHandler($client);
        $item = new MediaItem(InputType::AUDIO, 'binary');
        $text = $handler($item, new Context(), []);

        $this->assertSame('voice', $text);
    }

    public function testVideoHandlerUsesInjectedClient(): void
    {
        $client = new class {
            public function analyze($input, array $options = [])
            {
                return ['entities' => ['car']];
            }
        };

        $handler = new SimpleClientsVideoAnalysisHandler($client);
        $item = new MediaItem(InputType::VIDEO, 'binary');
        $result = $handler($item, new Context(), []);

        $this->assertSame(['entities' => ['car']], $result);
    }
}
