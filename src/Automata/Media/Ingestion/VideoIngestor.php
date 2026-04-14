<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\InputType;

class VideoIngestor extends BinaryIngestor
{
    protected string $type = InputType::VIDEO;
    protected string $mimePrefix = 'video/';
}
