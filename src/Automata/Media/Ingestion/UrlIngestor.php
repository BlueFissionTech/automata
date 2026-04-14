<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\DevElation as Dev;
use BlueFission\Str;

class UrlIngestor extends AbstractIngestor
{
    public function supports($input, array $options = []): bool
    {
        if (!Str::is($input)) {
            return false;
        }

        return (bool)preg_match('#^https?://#i', $input);
    }

    public function ingest($input, array $options = []): MediaItem
    {
        $meta = ['url' => (string)$input];
        $content = (string)$input;
        $content = Dev::apply('media.ingestion.url.content', $content);

        return $this->buildItem(InputType::URL, $content, $meta, $options);
    }
}
