<?php

namespace BlueFission\Automata\Media\Processing\Image;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class OcrProcessor implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['ocr'] ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('ocr');
        }

        if (is_callable($handler)) {
            $text = call_user_func($handler, $item, $context, $options);
            $text = Dev::apply('media.processing.image.ocr', $text);
            if (is_string($text) && $text !== '') {
                $result->setMeta('ocr_text', $text);
            }
        }

        return $result;
    }
}
