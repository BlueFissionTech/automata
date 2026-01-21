<?php

namespace BlueFission\Automata\Classification;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

class Gateway
{
    protected OrganizedCollection $_classifiers;
    protected array $_profiles;
    protected ?Context $_context = null;

    public function __construct()
    {
        $this->_classifiers = new OrganizedCollection();
        $this->_profiles = [];
    }

    public function setContext(Context $context): void
    {
        $this->_context = $context;
    }

    public function registerClassifier(IClassifier $classifier, string $name, array $profile = []): void
    {
        $defaults = [
            'types' => [],
            'tags' => [],
            'weight' => null,
        ];

        $profile = array_merge($defaults, $profile);

        $this->_classifiers->add($classifier, $name);
        $this->_profiles[$name] = $profile;

        if ($profile['weight'] !== null) {
            $this->_classifiers->weight($name, (float)$profile['weight']);
            $this->_classifiers->sort();
        }

        Dev::do('classification.gateway.registered', ['name' => $name, 'profile' => $profile]);
    }

    public function classify($input, array $options = []): Result
    {
        $input = Dev::apply('classification.gateway.classify_input', $input);
        $context = $this->resolveContext($options);
        $result = new Result();

        foreach ($this->_classifiers->contents() as $name => $entry) {
            $classifier = $entry['value'] ?? null;
            if (!$classifier instanceof IClassifier) {
                continue;
            }

            $output = $classifier->classify($input, $context, $options);
            if ($output instanceof Result) {
                $result->merge($output);
            }

            Dev::do('classification.gateway.classified', [
                'name' => $name,
                'output' => $output,
            ]);
        }

        return $result;
    }

    protected function resolveContext(array $options): Context
    {
        $context = $options['context'] ?? $this->_context;

        if ($context instanceof Context) {
            return $context;
        }

        $contextObj = new Context();
        if (is_array($context)) {
            foreach ($context as $key => $value) {
                $contextObj->set($key, $value);
            }
        }

        return $contextObj;
    }
}
