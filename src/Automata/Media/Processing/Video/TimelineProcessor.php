<?php

namespace BlueFission\Automata\Media\Processing\Video;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class TimelineProcessor implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['timeline_analyzer'] ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('timeline_analyzer');
        }

        if (is_callable($handler)) {
            $timeline = call_user_func($handler, $item, $context, $options);
            $timeline = Dev::apply('media.processing.video.timeline', $timeline);
            if (is_array($timeline)) {
                $result->addFeature('timeline', $timeline);
            }
        }

        return $result;
    }
}
