<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\DevElation as Dev;
use BlueFission\Str;

class TextIngestor extends AbstractIngestor
{
    public function supports($input, array $options = []): bool
    {
        $type = $this->resolveType($input, $options);
        if ($type === InputType::TEXT || $type === InputType::DOCUMENT) {
            return true;
        }

        if (Str::is($input) && !is_file($input)) {
            return true;
        }

        $mime = $this->detectMimeType($input);
        return $mime ? $this->isTextMime($mime) : false;
    }

    public function ingest($input, array $options = []): MediaItem
    {
        $type = InputType::TEXT;
        $mime = $this->detectMimeType($input);
        $meta = $this->baseMeta($input, $mime);

        $content = $this->readContent($input, $options);
        if ($mime) {
            $meta['mime'] = $mime;
        }

        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($content, mb_detect_order(), true);
            if ($encoding) {
                $meta['encoding'] = $encoding;
            }
        }

        $content = Dev::apply('media.ingestion.text.content', $content);

        return $this->buildItem($type, $content, $meta, $options);
    }

    protected function isTextMime(string $mime): bool
    {
        if (Str::pos($mime, 'text/') === 0) {
            return true;
        }

        return in_array($mime, ['application/json', 'application/xml', 'application/xhtml+xml'], true);
    }
}
