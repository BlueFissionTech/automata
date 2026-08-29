<?php

namespace BlueFission\Automata\LLM\Generation;

use BlueFission\Arr;

class GeneratedArtifact extends GenerationValue
{
    public function id(): string
    {
        return (string)$this->field('artifact_id');
    }

    public function kind(): string
    {
        return (string)$this->field('kind');
    }

    public function content(): mixed
    {
        return $this->field('content');
    }

    public function reference(): ?string
    {
        $reference = $this->field('reference');

        return $reference === null ? null : (string)$reference;
    }

    public function evidence(): array
    {
        return Arr::make($this->field('evidence') ?? [])->toArray();
    }

    public function isInline(): bool
    {
        return $this->content() !== null;
    }

    protected function defaults(): array
    {
        return [
            'artifact_id' => '',
            'kind' => 'text',
            'media_type' => 'text/plain',
            'content' => null,
            'reference' => null,
            'hash' => null,
            'size' => null,
            'evidence' => [],
            'metadata' => [],
        ];
    }
}
