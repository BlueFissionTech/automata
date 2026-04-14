<?php

namespace BlueFission\Automata\Media;

use BlueFission\Automata\Context;
use BlueFission\Obj;

class MediaItem extends Obj
{
    protected ?string $_mediaType = null;
    protected $content = null;
    protected ?string $_path = null;
    protected $stream = null;
    protected array $_meta = [];
    protected ?Context $_context = null;
    protected array $_features = [];

    public function __construct(?string $type = null, $content = null, array $meta = [], ?Context $context = null)
    {
        parent::__construct();
        $this->_mediaType = $type;
        $this->content = $content;
        $this->_meta = $meta;
        $this->_context = $context ?? new Context();
    }

    public function type(?string $type = null): ?string
    {
        if ($type === null) {
            return $this->_mediaType;
        }

        $this->_mediaType = $type;
        return $this->_mediaType;
    }

    public function content($content = null)
    {
        if ($content === null) {
            return $this->content;
        }

        $this->content = $content;
        return $this->content;
    }

    public function path(?string $path = null): ?string
    {
        if ($path === null) {
            return $this->_path;
        }

        $this->_path = $path;
        return $this->_path;
    }

    public function stream($stream = null)
    {
        if ($stream === null) {
            return $this->stream;
        }

        $this->stream = $stream;
        return $this->stream;
    }

    public function meta($meta = null)
    {
        if ($meta === null) {
            return $this->_meta;
        }

        if (is_array($meta)) {
            $this->_meta = $meta;
        }

        return $this->_meta;
    }

    public function setMeta(string $key, $value): void
    {
        $this->_meta[$key] = $value;
    }

    public function context(?Context $context = null): Context
    {
        if ($context !== null) {
            $this->_context = $context;
        }

        return $this->_context ?? new Context();
    }

    public function addFeature(string $name, $value): void
    {
        $this->_features[$name] = $value;
    }

    public function features(?array $features = null): array
    {
        if ($features !== null) {
            $this->_features = $features;
        }

        return $this->_features;
    }

    public function tag(string $tag): void
    {
        $this->context()->addTag($tag);
    }

    public function tags(): array
    {
        return $this->context()->tags();
    }
}
