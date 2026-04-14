<?php

namespace BlueFission\Automata\Media\Processing\Audio;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Obj;

class SimpleClientsTranscriptionHandler extends Obj
{
    protected $client;
    protected array $_config;

    public function __construct($client = null, array $config = [])
    {
        parent::__construct();
        $this->client = $client;
        $this->_config = $config;
    }

    public function isAvailable(): bool
    {
        return $this->client !== null || class_exists('BlueFission\\SimpleClients\\Speech\\TranscriptionClient');
    }

    public function __invoke(MediaItem $item, Context $context, array $options = []): ?string
    {
        $client = $this->resolveClient($options);
        if (!$client) {
            return null;
        }

        $input = $this->resolveInput($item);
        if ($input === null) {
            return null;
        }

        $result = null;
        if (method_exists($client, 'transcribe')) {
            $result = $client->transcribe($input, $options);
        }

        $text = $this->extractText($result);
        return $text !== '' ? $text : null;
    }

    protected function resolveClient(array $options)
    {
        if (isset($options['client'])) {
            return $options['client'];
        }

        if ($this->client) {
            return $this->client;
        }

        $class = 'BlueFission\\SimpleClients\\Speech\\TranscriptionClient';
        if (!class_exists($class)) {
            return null;
        }

        $config = $options['config'] ?? $this->_config;
        return new $class($config);
    }

    protected function resolveInput(MediaItem $item)
    {
        $path = $item->path();
        if ($path && is_file($path)) {
            return $path;
        }

        $content = $item->content();
        if (is_string($content) && $content !== '') {
            return $content;
        }

        return null;
    }

    protected function extractText($result): string
    {
        if (is_array($result)) {
            if (isset($result['text'])) {
                return (string)$result['text'];
            }
            if (isset($result['transcript'])) {
                return (string)$result['transcript'];
            }
            if (isset($result['result']['text'])) {
                return (string)$result['result']['text'];
            }
        }

        if (is_string($result)) {
            return $result;
        }

        return '';
    }
}
