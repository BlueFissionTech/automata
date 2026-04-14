<?php

namespace BlueFission\Automata\Media\Processing\Audio;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Pipeline;
use BlueFission\Automata\Media\Processing\Result;

class AudioPipeline extends Pipeline
{
    public function __construct()
    {
        parent::__construct();
        $this->addProcessor(new MetadataProcessor());
        $this->addProcessor(new VolumeNormalizer());
        $this->addProcessor(new TranscriptionProcessor());
        $this->addProcessor(new EventProcessor());
    }

    public function process(MediaItem $item, ?Context $context = null, array $options = []): Result
    {
        $result = parent::process($item, $context, $options);

        if (empty($result->segments())) {
            $result->addSegment($item->type() ?? 'audio', $item->path() ?? 'audio', [
                'features' => $result->features(),
            ]);
        }

        return $result;
    }
}
