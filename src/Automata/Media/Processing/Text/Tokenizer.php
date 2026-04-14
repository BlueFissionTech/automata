<?php

namespace BlueFission\Automata\Media\Processing\Text;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\Automata\Language\Preparer;
use BlueFission\DevElation as Dev;

class Tokenizer implements IProcessor
{
    protected Preparer $preparer;

    public function __construct(?Preparer $preparer = null)
    {
        $this->preparer = $preparer ?? new Preparer();
    }

    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $text = $result->meta()['normalized_text'] ?? $item->content();
        $boundary = $options['boundary'] ?? '/\s+/';

        if (isset($options['blacklist']) && is_array($options['blacklist'])) {
            $this->preparer->setBlacklist($options['blacklist']);
        }

        $tokens = $this->preparer->tokenize((string)$text, $boundary);
        $tokens = Dev::apply('media.processing.text.tokens', $tokens);

        $result->tokens($tokens);
        $context->setNormalization('tokens', $tokens);

        return $result;
    }
}
