<?php

namespace BlueFission\Automata\Media\Processing\Video;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Pipeline;
use BlueFission\Automata\Media\Processing\Result;

class VideoPipeline extends Pipeline
{
    public function __construct()
    {
        parent::__construct();
        $this->addProcessor(new FrameExtractor());
        $this->addProcessor(new TimelineProcessor());
    }

    public function process(MediaItem $item, ?Context $context = null, array $options = []): Result
    {
        $result = parent::process($item, $context, $options);

        if (empty($result->segments())) {
            $result->addSegment($item->type() ?? 'video', $item->path() ?? 'video', [
                'features' => $result->features(),
            ]);
        }

        return $result;
    }
}
