<?php

namespace BlueFission\Automata\Behavioral;

use BlueFission\Automata\Learning\ExactLookupStrategy;
use BlueFission\Automata\Learning\MemoryTrainingRegistry;
use BlueFission\Automata\Learning\TrainingObservation;
use BlueFission\Automata\Learning\TrainingRegistry;
use BlueFission\Automata\Learning\TrainingStrategy;
use BlueFission\Automata\Learning\TrainingValue;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\DevElation as Dev;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Opt-in method observation and conservative model pass-through for Obj hosts.
 *
 * No constructor or __call override: this composes with Configurable,
 * Programmable and StateMachine without changing their initialization or dispatch.
 */
trait Trainable
{
    private ?TrainingRegistry $trainableRegistry = null;
    /** @var array<string, TrainableMethod> */
    private array $trainableOptions = [];
    /** @var array<string, callable> */
    private array $trainableOperations = [];
    /** @var array<string, TrainingStrategy> */
    private array $trainableStrategies = [];
    /** @var array<string, array{reader: callable, feature: bool}> */
    private array $trainableStateReaders = [];
    private array $trainablePredictionAttempts = [];

    public function setTrainingRegistry(TrainingRegistry $registry): static
    {
        $this->trainableRegistry = $registry;
        return $this;
    }

    public function trainingRegistry(): TrainingRegistry
    {
        return $this->trainableRegistry ??= new MemoryTrainingRegistry();
    }

    /** Call after the host constructor when an eager registry is desired. */
    public function initializeTrainable(?TrainingRegistry $registry = null): static
    {
        if ($registry !== null) {
            $this->setTrainingRegistry($registry);
        } else {
            $this->trainingRegistry();
        }
        return $this;
    }

    /** Register a public declared method. Attributes supply options, not interception. */
    public function registerTrainableMethod(
        string $method,
        ?TrainableMethod $options = null,
        ?TrainingStrategy $strategy = null,
    ): static {
        if (!method_exists($this, $method) || $method === 'trainableInvoke') {
            throw new \InvalidArgumentException("Method {$method} is not a declared trainable method.");
        }
        $reflection = new ReflectionMethod($this, $method);
        if (!$reflection->isPublic() || $reflection->isStatic() || $reflection->returnsReference()) {
            throw new \InvalidArgumentException("Method {$method} must be public, non-static and return by value.");
        }
        foreach ($reflection->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                throw new \InvalidArgumentException('Reference parameters cannot be observed safely.');
            }
        }
        if ($options === null) {
            $attribute = $reflection->getAttributes(TrainableMethod::class)[0] ?? null;
            $options = $attribute?->newInstance() ?? new TrainableMethod();
        }
        return $this->registerTrainableOperation(
            $method,
            fn (...$args) => $this->{$method}(...$args),
            $options,
            $strategy,
        );
    }

    /** Explicit callable seam for Programmable tasks and other composed behaviors. */
    public function registerTrainableOperation(
        string $name,
        callable $operation,
        ?TrainableMethod $options = null,
        ?TrainingStrategy $strategy = null,
    ): static {
        if ($name === '' || $name === 'trainableInvoke') {
            throw new \InvalidArgumentException('A distinct operation name is required.');
        }
        $this->trainableOperations[$name] = $operation;
        $this->trainableOptions[$name] = $options ?? new TrainableMethod();
        $this->trainableStrategies[$name] = $strategy ?? new ExactLookupStrategy();
        $this->trainablePredictionAttempts[$name] = 0;
        return $this;
    }

    public function registerAttributedTrainableMethods(): static
    {
        foreach ((new ReflectionClass($this))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getAttributes(TrainableMethod::class) !== []) {
                $this->registerTrainableMethod($method->getName());
            }
        }
        return $this;
    }

    /** A reader is called before every invocation; metadata is never a feature. */
    public function registerTrainableState(string $name, callable $reader, bool $asFeature = true): static
    {
        if ($name === '' || $name === 'invoked_at_utc') {
            throw new \InvalidArgumentException('A non-reserved state name is required.');
        }
        $this->trainableStateReaders[$name] = ['reader' => $reader, 'feature' => $asFeature];
        return $this;
    }

    /**
     * Invoke the registered source or return an adequate model output. Both
     * paths run Trainable input/output filters, hooks and Obj behavior dispatch.
     * Effects hidden inside the source method cannot be replayed by this trait.
     */
    public function trainableInvoke(string $name, mixed ...$args): mixed
    {
        if (!isset($this->trainableOperations[$name])) {
            throw new \InvalidArgumentException("Operation {$name} is not registered.");
        }

        $hook = 'automata.trainable.v1.' . strtolower(str_replace('\\', '.', static::class)) . '.' . $name;
        $filteredArgs = Dev::apply($hook . '.input', $args);
        if (!is_array($filteredArgs) || !array_is_list($filteredArgs)) {
            throw new \UnexpectedValueException('The Trainable input filter must return a list of arguments.');
        }
        $args = $filteredArgs;
        $context = [];
        $metadata = ['schema_version' => 1, 'invoked_at_utc' => gmdate('Y-m-d\TH:i:s\Z')];
        $recordable = TrainingValue::supported($args);
        try {
            foreach ($this->trainableStateReaders as $stateName => $state) {
                $value = ($state['reader'])($this);
                $recordable = $recordable && TrainingValue::supported($value);
                if ($state['feature']) {
                    $context[$stateName] = $value;
                } else {
                    $metadata[$stateName] = $value;
                }
            }
        } catch (Throwable) {
            $recordable = false;
        }

        $before = ['operation' => $name, 'input' => $args, 'context' => $context];
        Dev::do($hook . '.before', [$before]);
        $this->dispatch('OnTrainableInvoked', new Meta(info: $name, data: $before));

        $source = 'method';
        $prediction = null;
        $options = $this->trainableOptions[$name];
        if ($recordable && $options->allowPrediction) {
            try {
                $rows = $this->trainingRegistry()->observations(static::class, $name);
                if (count($rows) >= $options->minimumSamples) {
                    $strategy = $this->trainableStrategies[$name];
                    $strategy->train($rows);
                    $candidate = $strategy->predict($args, $context);
                    if ($candidate->available && TrainingValue::supported($candidate->output)
                        && is_finite($candidate->confidence)
                        && $candidate->confidence >= $options->minimumConfidence
                        && $candidate->support >= $options->minimumMatches) {
                        $attempt = ++$this->trainablePredictionAttempts[$name];
                        if ($attempt % $options->probeEvery !== 0) {
                            $output = $candidate->output;
                            $source = 'model';
                            $prediction = $candidate;
                        }
                    }
                }
            } catch (Throwable) {
                // A failing registry or model never suppresses the source method.
            }
        }

        if ($source === 'method') {
            $output = ($this->trainableOperations[$name])(...$args);
        }
        $rawOutput = $output;
        $output = Dev::apply($hook . '.output', $output);
        $after = [
            'operation' => $name,
            'source' => $source,
            'output' => $output,
            'confidence' => $prediction?->confidence,
            'support' => $prediction?->support,
        ];
        Dev::do($hook . '.after', [$after]);
        $this->dispatch('OnTrainableReturned', new Meta(info: $source, data: $after));
        foreach ($options->behaviors as $behavior) {
            $this->dispatch($behavior, new Meta(info: $source, data: $after));
        }
        if ($source === 'model') {
            $this->dispatch('OnTrainablePredicted', new Meta(info: $name, data: $after));
        } elseif ($recordable && TrainingValue::supported($rawOutput)) {
            try {
                $this->trainingRegistry()->record(static::class, $name,
                    new TrainingObservation($args, $rawOutput, $context, $metadata));
                $this->dispatch('OnTrainableObserved', new Meta(info: $name, data: $after));
            } catch (Throwable) {
                // Observation is best effort; the source method already succeeded.
            }
        }
        return $output;
    }
}
