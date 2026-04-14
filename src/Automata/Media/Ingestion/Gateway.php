<?php

namespace BlueFission\Automata\Media\Ingestion;

use BlueFission\Arr;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\InputTypeDetector;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\Automata\Media\Ingestion\TextIngestor;
use BlueFission\DevElation as Dev;

class Gateway
{
    protected OrganizedCollection $_ingestors;
    protected array $_profiles;

    public function __construct()
    {
        $this->_ingestors = new OrganizedCollection();
        $this->_profiles = [];
    }

    public function registerIngestor(IIngestor $ingestor, string $name, array $profile = []): void
    {
        $defaults = [
            'types' => [],
            'weight' => null,
        ];

        $profile = Arr::merge($defaults, $profile);

        $this->_ingestors->add($ingestor, $name);
        $this->_profiles[$name] = $profile;

        if ($profile['weight'] !== null) {
            $this->_ingestors->weight($name, (float)$profile['weight']);
            $this->_ingestors->sort();
        }

        Dev::do('media.ingestion.gateway.registered', ['name' => $name, 'profile' => $profile]);
    }

    public function ingest($input, array $options = []): MediaItem
    {
        $input = Dev::apply('media.ingestion.gateway.input', $input);
        $typeHint = $options['type'] ?? InputTypeDetector::detect($input);

        foreach ($this->_ingestors->contents() as $name => $entry) {
            $ingestor = $entry['value'] ?? null;
            if (!$ingestor instanceof IIngestor) {
                continue;
            }

            $profile = $this->_profiles[$name] ?? [];
            $types = $profile['types'] ?? [];

            if (Arr::size($types) > 0 && $typeHint && !Arr::hasValue($types, $typeHint, true)) {
                continue;
            }

            if ($ingestor->supports($input, $options)) {
                $item = $ingestor->ingest($input, $options);
                Dev::do('media.ingestion.gateway.ingested', ['name' => $name, 'type' => $item->type()]);
                return $item;
            }
        }

        Dev::do('media.ingestion.gateway.fallback', ['type' => $typeHint]);
        $fallback = new TextIngestor();
        return $fallback->ingest($input, $options);
    }
}
