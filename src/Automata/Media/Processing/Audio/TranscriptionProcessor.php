<?php

namespace BlueFission\Automata\Media\Processing\Audio;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class TranscriptionProcessor implements IProcessor
{
    protected $handler;

    public function __construct($handler = null)
    {
        $this->handler = $handler;
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $handler = $options['speech_to_text']
            ?? $options['transcriber']
            ?? $this->handler;
        $registry = $options['registry'] ?? $options['handler_registry'] ?? null;
        if (!$handler && $registry instanceof HandlerRegistry) {
            $handler = $registry->resolve('speech_to_text');
        }

        if (is_callable($handler)) {
            $text = call_user_func($handler, $item, $context, $options);
            $text = Dev::apply('media.processing.audio.transcript', $text);
            if (is_string($text) && $text !== '') {
                $result->setMeta('transcript', $text);
            }
        }

        return $result;
    }
}
