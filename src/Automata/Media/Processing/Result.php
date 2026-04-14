<?php

namespace BlueFission\Automata\Media\Processing;

use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

class Result
{
    protected ?string $type = null;
    protected array $meta = [];
    protected array $features = [];
    protected array $entities = [];
    protected array $segments = [];
    protected array $tokens = [];
    protected array $metrics = [];
    protected ?Context $context = null;

    public function type(?string $type = null): ?string
    {
        if ($type === null) {
            return $this->type;
        }

        $this->type = $type;
        return $this->type;
    }

    public function context(?Context $context = null): ?Context
    {
        if ($context !== null) {
            $this->context = $context;
        }

        return $this->context;
    }

    public function meta(?array $meta = null): array
    {
        if ($meta !== null) {
            $this->meta = $meta;
        }

        return $this->meta;
    }

    public function setMeta(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

    public function addFeature(string $name, $value): void
    {
        $this->features[$name] = $value;
    }

    public function features(): array
    {
        return $this->features;
    }

    public function addEntity(string $label, $value): void
    {
        if (!isset($this->entities[$label])) {
            $this->entities[$label] = [];
        }

        if (is_array($value)) {
            $this->entities[$label] = array_values(array_unique(array_merge($this->entities[$label], $value)));
        } else {
            $this->entities[$label][] = $value;
        }
    }

    public function entities(): array
    {
        return $this->entities;
    }

    public function tokens(?array $tokens = null): array
    {
        if ($tokens !== null) {
            $this->tokens = $tokens;
        }

        return $this->tokens;
    }

    public function setMetric(string $name, $value): void
    {
        $this->metrics[$name] = $value;
    }

    public function metrics(): array
    {
        return $this->metrics;
    }

    public function addSegment(string $type, $payload, array $meta = []): void
    {
        $this->segments[] = [
            'type' => $type,
            'payload' => $payload,
            'meta' => $meta,
        ];
    }

    public function segments(): array
    {
        return $this->segments;
    }

    public function toSegments(): array
    {
        if (!empty($this->segments)) {
            return $this->segments;
        }

        $payload = $this->meta;
        $meta = [
            'features' => $this->features,
            'entities' => $this->entities,
            'metrics' => $this->metrics,
        ];

        $segment = [
            'type' => $this->type ?? 'text',
            'payload' => $payload,
            'meta' => $meta,
        ];

        $segment = Dev::apply('media.processing.result.segment', $segment);

        return [$segment];
    }
}
