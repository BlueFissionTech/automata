<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Context;
use BlueFission\Automata\DataGroup;
use BlueFission\DevElation as Dev;

class Gateway
{
    protected OrganizedCollection $_detectors;
    protected array $_profiles;
    protected ?Context $_context = null;

    public function __construct()
    {
        $this->_detectors = new OrganizedCollection();
        $this->_profiles = [];
    }

    public function setContext(Context $context): void
    {
        $this->_context = $context;
    }

    public function registerDetector(IAnomalyDetector $detector, string $name, array $profile = []): void
    {
        $defaults = [
            'tags' => [],
            'type' => null,
            'threshold' => null,
            'weight' => null,
        ];

        $profile = array_merge($defaults, $profile);
        $this->_detectors->add($detector, $name);
        $this->_profiles[$name] = $profile;

        if ($profile['threshold'] !== null) {
            $detector->setThreshold((float)$profile['threshold']);
        }

        if ($profile['weight'] !== null) {
            $this->_detectors->weight($name, (float)$profile['weight']);
            $this->_detectors->sort();
        }

        Dev::do('anomaly.gateway.registered', ['name' => $name, 'profile' => $profile]);
    }

    public function registerGroup(DataGroup $group, array $profile = []): void
    {
        $prefix = $group->getName();
        foreach ($group->getStrategies() as $index => $strategy) {
            if (!$strategy instanceof IAnomalyDetector) {
                continue;
            }

            $name = $prefix . ':' . $index;
            $this->registerDetector($strategy, $name, $profile);
        }
    }

    public function analyze($input, array $options = []): Result
    {
        $input = Dev::apply('anomaly.gateway.analyze_input', $input);
        $context = $this->resolveContext($input, $options);
        $result = new Result();

        foreach ($this->_detectors->contents() as $name => $entry) {
            $detector = $entry['value'] ?? null;
            if (!$detector instanceof IAnomalyDetector) {
                continue;
            }

            $score = $detector->score($input, $context, $options);
            $profile = $this->_profiles[$name] ?? [];
            $threshold = $profile['threshold'] ?? $detector->threshold();
            $flagged = $threshold !== null && $score >= (float)$threshold;

            $meta = [
                'threshold' => $threshold,
                'flagged' => $flagged,
                'profile' => $profile,
            ];

            $result->addIndicator($name, $score, $meta);

            Dev::do('anomaly.gateway.scored', [
                'name' => $name,
                'score' => $score,
                'flagged' => $flagged,
            ]);
        }

        return $result;
    }

    protected function resolveContext($input, array $options): Context
    {
        if ($input instanceof Activity) {
            return $input->context();
        }

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
