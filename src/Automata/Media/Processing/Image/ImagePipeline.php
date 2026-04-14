<?php

namespace BlueFission\Automata\Media\Processing\Image;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\Pipeline;
use BlueFission\Automata\Media\Processing\Result;

class ImagePipeline extends Pipeline
{
    public function __construct()
    {
        parent::__construct();
        $this->addProcessor(new MetadataProcessor());
        $this->addProcessor(new OcrProcessor());
        $this->addProcessor(new BoundaryProcessor());
        $this->addProcessor(new ConvolutionProcessor());
    }

    public function process(MediaItem $item, ?Context $context = null, array $options = []): Result
    {
        $result = parent::process($item, $context, $options);

        if (empty($result->segments())) {
            $result->addSegment($item->type() ?? 'image', $item->path() ?? 'image', [
                'features' => $result->features(),
                'entities' => $result->entities(),
            ]);
        }

        return $result;
    }
}
