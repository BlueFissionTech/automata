<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;

class DocumentIngestor extends BinaryIngestor
{
    protected string $type = InputType::DOCUMENT;
    protected string $mimePrefix = 'application/';

    public function supports($input, array $options = []): bool
    {
        $type = $this->resolveType($input, $options);
        if ($type === InputType::DOCUMENT) {
            return true;
        }

        $mime = $this->detectMimeType($input);
        return $mime === 'application/pdf';
    }

    public function ingest($input, array $options = []): MediaItem
    {
        return parent::ingest($input, $options);
    }
}
