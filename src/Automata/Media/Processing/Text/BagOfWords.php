<?php

namespace BlueFission\Automata\Media\Processing\Text;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Processing\IProcessor;
use BlueFission\Automata\Media\Processing\Result;
use BlueFission\DevElation as Dev;

class BagOfWords implements IProcessor
{
    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result
    {
        $tokens = $result->tokens();
        $bag = [];

        foreach ($tokens as $token) {
            $token = (string)$token;
            if ($token === '') {
                continue;
            }
            $bag[$token] = ($bag[$token] ?? 0) + 1;
        }

        $bag = Dev::apply('media.processing.text.bag_of_words', $bag);

        $result->addFeature('bag_of_words', $bag);

        return $result;
    }
}
