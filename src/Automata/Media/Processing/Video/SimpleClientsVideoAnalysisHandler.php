<?php

namespace BlueFission\Automata\Media\Processing\Video;

use BlueFission\Automata\Context;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Obj;

class SimpleClientsVideoAnalysisHandler extends Obj
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
        return $this->client !== null || class_exists('BlueFission\\SimpleClients\\Video\\AnalysisClient');
    }

    public function __invoke(MediaItem $item, Context $context, array $options = []): ?array
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
        if (method_exists($client, 'analyze')) {
            $result = $client->analyze($input, $options);
        }

        if (is_array($result)) {
            return $result;
        }

        return null;
    }

    protected function resolveClient(array $options)
    {
        if (isset($options['client'])) {
            return $options['client'];
        }

        if ($this->client) {
            return $this->client;
        }

        $class = 'BlueFission\\SimpleClients\\Video\\AnalysisClient';
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
}
