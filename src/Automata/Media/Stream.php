<?php

namespace BlueFission\Automata\Media;

class Stream
{
    protected $resource;
    protected array $meta = [];

    public function __construct($resource)
    {
        $this->resource = $resource;
        if (is_resource($resource)) {
            $this->meta = stream_get_meta_data($resource) ?: [];
        }
    }

    public function resource()
    {
        return $this->resource;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function read(?int $length = null): string
    {
        if (!is_resource($this->resource)) {
            return '';
        }

        $data = $length === null ? stream_get_contents($this->resource) : stream_get_contents($this->resource, $length);
        return $data === false ? '' : $data;
    }

    public function rewind(): void
    {
        if (is_resource($this->resource)) {
            rewind($this->resource);
        }
    }

    public function length(): ?int
    {
        if (!is_resource($this->resource)) {
            return null;
        }

        $stats = fstat($this->resource);
        return isset($stats['size']) ? (int)$stats['size'] : null;
    }
}
