<?php

namespace BlueFission\Automata\Media\Processing;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\InputTypeDetector;
use BlueFission\Automata\Media\MediaItem;
use BlueFission\DevElation as Dev;
use BlueFission\Automata\Media\Processing\HandlerRegistry;

class Gateway
{
    protected OrganizedCollection $_pipelines;
    protected array $_profiles;
    protected ?HandlerRegistry $_registry = null;

    public function __construct()
    {
        $this->_pipelines = new OrganizedCollection();
        $this->_profiles = [];
    }

    public function registerPipeline(Pipeline $pipeline, string $name, array $profile = []): void
    {
        $defaults = [
            'types' => [],
            'weight' => null,
        ];

        $profile = array_merge($defaults, $profile);

        $this->_pipelines->add($pipeline, $name);
        $this->_profiles[$name] = $profile;

        if ($profile['weight'] !== null) {
            $this->_pipelines->weight($name, (float)$profile['weight']);
            $this->_pipelines->sort();
        }

        Dev::do('media.processing.gateway.registered', ['name' => $name, 'profile' => $profile]);
    }

    public function setRegistry(HandlerRegistry $registry): void
    {
        $this->_registry = $registry;
    }

    public function registry(): ?HandlerRegistry
    {
        return $this->_registry;
    }

    public function process(MediaItem $item, array $options = []): Result
    {
        $typeHint = $item->type() ?? InputTypeDetector::detect($item->content());
        if (!isset($options['handler_registry']) && !isset($options['registry']) && $this->_registry) {
            $options['handler_registry'] = $this->_registry;
        }

        foreach ($this->_pipelines->contents() as $name => $entry) {
            $pipeline = $entry['value'] ?? null;
            if (!$pipeline instanceof Pipeline) {
                continue;
            }

            $profile = $this->_profiles[$name] ?? [];
            $types = $profile['types'] ?? [];

            if (!empty($types) && $typeHint && !in_array($typeHint, $types, true)) {
                continue;
            }

            if (method_exists($pipeline, 'setRegistry') && $this->_registry) {
                $pipeline->setRegistry($this->_registry);
            }

            $result = $pipeline->process($item, $item->context(), $options);
            Dev::do('media.processing.gateway.processed', ['name' => $name, 'type' => $typeHint]);
            return $result;
        }

        Dev::do('media.processing.gateway.fallback', ['type' => $typeHint]);
        $fallback = new Pipeline();
        return $fallback->process($item, $item->context(), $options);
    }
}
