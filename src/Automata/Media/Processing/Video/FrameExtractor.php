<?php

namespace BlueFission\Automata\Media\Processing\Video;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class FrameExtractor implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['frame_extractor'] ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('frame_extractor');
        }

        if (is_callable($handler)) {
            $frames = call_user_func($handler, $item, $context, $options);
            $frames = Dev::apply('media.processing.video.frames', $frames);
            if (is_array($frames)) {
                $result->addFeature('frames', $frames);
            }
        }

        return $result;
    }
}
