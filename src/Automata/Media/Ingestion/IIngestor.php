<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\Media\MediaItem;

interface IIngestor
{
    public function supports($input, array $options = []): bool;

    public function ingest($input, array $options = []): MediaItem;
}
