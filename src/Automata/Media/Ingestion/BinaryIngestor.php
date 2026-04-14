<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\Media\MediaItem;
use BlueFission\DevElation as Dev;
use BlueFission\Str;

abstract class BinaryIngestor extends AbstractIngestor
{
    protected string $type;
    protected string $mimePrefix;

    public function supports($input, array $options = []): bool
    {
        $type = $this->resolveType($input, $options);
        if ($type === $this->type) {
            return true;
        }

        $mime = $this->detectMimeType($input);
        if ($mime && Str::pos($mime, $this->mimePrefix) === 0) {
            return true;
        }

        return false;
    }

    public function ingest($input, array $options = []): MediaItem
    {
        $mime = $this->detectMimeType($input);
        $meta = $this->baseMeta($input, $mime);

        $content = null;
        if ($this->shouldLoadContent($options)) {
            $content = $this->readContent($input, $options);
        }

        $meta = Dev::apply('media.ingestion.binary.meta', $meta);

        return $this->buildItem($this->type, $content, $meta, $options);
    }

    protected function shouldLoadContent(array $options): bool
    {
        if (array_key_exists('load_content', $options)) {
            return (bool)$options['load_content'];
        }

        return false;
    }
}
