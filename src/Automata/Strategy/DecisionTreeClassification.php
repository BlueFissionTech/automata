<?php

namespace BlueFission\Automata\Strategy;

use BlueFission\{Arr, Flag, Num, Str};
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use LogicException;
use BlueFission\Automata\Strategy\Backend\ClassificationTree;
use Phpml\Estimator;
use Phpml\ModelManager;
use Throwable;

/**
 * Fitted classification strategy, distinct from the DecisionTree traversal API.
 *
 * Each fit builds an isolated bounded-depth tree using the installed PHP-ML
 * backend. Hosts own feature projection, admitted labels, independent evaluation
 * and activation. Rehearse prior rows explicitly when replacing a learned model.
 */
final class DecisionTreeClassification extends Strategy implements Estimator
{
    private ?ClassificationTree $model = null;
    private array $featureTypes = [];
    private array $labels = [];
    private array $holdoutSamples = [];
    private array $holdoutLabels = [];

    /** Depth limits are configured policy, not a promise against overfitting. */
    public function __construct(private int $depthLimit = 10, private ?ModelManager $manager = null)
    {
        if ($depthLimit < 1) { throw new InvalidArgumentException('Tree depth must be positive.'); }
        $this->manager ??= new ModelManager();
    }

    /** Report the retained fit policy, including after artifact restoration. */
    public function maximumDepth(): int { return $this->depthLimit; }

    /**
     * Replace fitted parameters only after validation and fitting complete.
     * A deterministic trailing split supports the ordinary Strategy API; use
     * zero when a separate evaluator owns disjoint held-out sessions. The backend
     * otherwise accumulates rows on train(), so every replacement uses a new tree.
     */
    public function train(array $samples, array $labels, float $testSize = 0.2): void
    {
        $samples = RecordSnapshot::copy($samples);
        $labels = RecordSnapshot::copy($labels);
        if (!Arr::check($samples, 'array_is_list') || !Arr::check($labels, 'array_is_list')
            || $samples === [] || Arr::size($samples) !== Arr::size($labels)
            || !Num::check($testSize, 'is_finite') || $testSize < 0.0 || $testSize >= 1.0) {
            throw new InvalidArgumentException('Training requires aligned nonempty rows and a split in [0, 1).');
        }
        if (!Arr::is($samples[0]) || !Arr::check($samples[0], 'array_is_list') || $samples[0] === []) {
            throw new InvalidArgumentException('Features must be nonempty list vectors.');
        }
        $types = Arr::make($samples[0])->map(self::featureType(...))->val();
        $encodedSamples = Arr::make($samples)->map(static fn ($row) => self::vector($row, $types))->val();
        $labelMap = [];
        $encodedLabels = Arr::make($labels)->map(static function ($label) use (&$labelMap): string {
            if (!Str::is($label) && !Flag::isBool($label) && !Num::isInt($label) && !Num::isFloat($label)) {
                throw new InvalidArgumentException('Classification labels must be finite non-null scalars.');
            }
            // Backend array keys conflate 1, "1" and true; exact typed labels
            // require stable nonnumeric tokens and a retained decoding table.
            $key = 'label:' . RecordSnapshot::fingerprint($label);
            $labelMap[$key] = $label;
            return $key;
        })->val();
        $count = Arr::size($samples);
        $testCount = (int) Num::make($count)->multiply($testSize)->val();
        $trainCount = (int) Num::make($count)->subtract($testCount)->val();
        $candidate = new ClassificationTree($this->depthLimit);
        $candidate->train(Arr::make($encodedSamples)->slice(0, $trainCount)->val(),
            Arr::make($encodedLabels)->slice(0, $trainCount)->val());
        $this->model = $candidate;
        $this->featureTypes = $types;
        $this->labels = $labelMap;
        $this->holdoutSamples = Arr::make($samples)->slice($trainCount)->val();
        $this->holdoutLabels = Arr::make($labels)->slice($trainCount)->val();
    }

    /** Predict one feature vector, preserving the exact scalar type of its label. */
    public function predict($input): mixed
    {
        if ($this->model === null) { throw new LogicException('Fit or restore a tree before predicting.'); }
        $label = $this->model->predict(self::vector($input, $this->featureTypes));
        if (!Str::is($label) || !Arr::hasKey($this->labels, $label)) {
            throw new LogicException('Fitted tree returned an unknown label.');
        }
        return $this->labels[$label];
    }

    /** Measure only the internal trailing holdout; no holdout keeps legacy zero. */
    public function accuracy(): float
    {
        if ($this->holdoutSamples === []) { return 0.0; }
        $correct = 0;
        foreach ($this->holdoutSamples as $index => $sample) {
            $correct += (int) ($this->predict($sample) === $this->holdoutLabels[$index]);
        }
        return (float) Num::make($correct)->divide(Arr::size($this->holdoutSamples))->val();
    }

    /**
     * Save fitted parameters plus typed-label/projection metadata, not a recipe
     * that must refit on load. These backend artifacts are trusted serialized
     * objects; the host must protect and authenticate them before restoration.
     */
    public function saveModel(string $path): bool
    {
        if ($this->model === null) { return false; }
        try { $this->manager->saveToFile($this, $path); return true; }
        catch (Throwable) { return false; }
    }

    /** Restore a matching fitted artifact without training or partial replacement. */
    public function loadModel(string $path): bool
    {
        try {
            $loaded = $this->manager->restoreFromFile($path);
            if (!$loaded instanceof self || !$loaded->model instanceof ClassificationTree || $loaded->depthLimit < 1
                || $loaded->featureTypes === [] || $loaded->labels === []) { return false; }
            $types = RecordSnapshot::copy($loaded->featureTypes);
            foreach ($types as $type) {
                if (!Arr::has(['number', 'category'], $type, true)) { return false; }
            }
            $labels = RecordSnapshot::copy($loaded->labels);
            foreach ($labels as $key => $label) {
                if ($key !== 'label:' . RecordSnapshot::fingerprint($label)) { return false; }
            }
            $samples = RecordSnapshot::copy($loaded->holdoutSamples);
            $targets = RecordSnapshot::copy($loaded->holdoutLabels);
            if (Arr::size($samples) !== Arr::size($targets)) { return false; }
            foreach ($samples as $sample) { self::vector($sample, $types); }
            $this->model = $loaded->model;
            $this->depthLimit = $loaded->depthLimit;
            $this->featureTypes = $types;
            $this->labels = $labels;
            $this->holdoutSamples = $samples;
            $this->holdoutLabels = $targets;
            return true;
        } catch (Throwable) { return false; }
    }

    /** A column has one declared semantic kind; numeric-looking categories stay categories. */
    private static function featureType(mixed $value): string
    {
        if (Str::is($value)) { return 'category'; }
        if ((Num::isInt($value) || Num::isFloat($value)) && Num::check($value, 'is_finite')) { return 'number'; }
        throw new InvalidArgumentException('Features must be finite numbers or categorical strings.');
    }

    /** Validate dimensions/types before encoding categorical values for the backend. */
    private static function vector(mixed $row, array $types): array
    {
        if (!Arr::is($row) || !Arr::check($row, 'array_is_list') || Arr::size($row) !== Arr::size($types)) {
            throw new InvalidArgumentException('Feature vector dimensions do not match the fitted projection.');
        }
        $encoded = [];
        foreach ($row as $column => $value) {
            if (self::featureType($value) !== $types[$column]) { throw new InvalidArgumentException('Feature kind changed.'); }
            $encoded[] = $types[$column] === 'category' ? 'category:' . $value : $value;
        }
        return $encoded;
    }
}
