<?php

namespace BlueFission\Automata\Media\Processing;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;

interface IProcessor
{
    public function process(MediaItem $item, Context $context, Result $result, array $options = []): Result;
}
