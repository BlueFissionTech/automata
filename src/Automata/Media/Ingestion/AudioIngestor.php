<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\InputType;

class AudioIngestor extends BinaryIngestor
{
    protected string $type = InputType::AUDIO;
    protected string $mimePrefix = 'audio/';
}
