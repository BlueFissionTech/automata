<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Arr;
use BlueFission\Automata\Learning\ClassificationEvaluator;
use BlueFission\Automata\Learning\ModelCandidate;
use BlueFission\Automata\Learning\TrainingBatch;
use BlueFission\Automata\Learning\TrainingExample;
use BlueFission\Automata\Strategy\IStrategy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ClassificationEvaluatorTest extends TestCase
{
    private function batch(array $rows, string $projection = 'intent'): TrainingBatch
    {
        return new TrainingBatch($projection, '1', Arr::make($rows)
            ->map(static fn (array $row) => new TrainingExample(...$row))->values()->val());
    }

    private function model(string $version, callable $predict, ?TrainingBatch $training = null): ModelCandidate
    {
        $strategy = new class($predict) implements IStrategy {
            public int $predictions = 0;
            public function __construct(private $callback) {}
            public function predict($input) { ++$this->predictions; return ($this->callback)($input); }
            public function train(array $samples, array $labels, float $testSize = 0.2) { throw new RuntimeException('Must not train.'); }
            public function accuracy(): float { throw new RuntimeException('Must measure held-out predictions.'); }
            public function saveModel(string $path): bool { throw new RuntimeException('Must not promote.'); }
            public function loadModel(string $path): bool { throw new RuntimeException('Must not replace.'); }
        };
        return new ModelCandidate('intent', $version, $strategy, $training ?? $this->batch([]));
    }

    public function testIndependentImprovementProducesTraceableRecommendationWithoutTrainingOrPromotion(): void
    {
        $old = $this->model('1', static fn () => false);
        $new = $this->model('2', static fn ($input) => $input);
        $holdout = $this->batch([['h1', 'o1', false, false], ['h2', 'o2', 0, 0]]);
        $report = (new ClassificationEvaluator(minimumSamples: 2))->compare($old, $new, $holdout);
        $this->assertTrue($report['recommended']);
        $this->assertSame(0.5, $report['incumbent']['accuracy']);
        $this->assertSame(1.0, $report['candidate']['accuracy']);
        $this->assertSame('2', $report['candidate']['version']);
        $this->assertSame(['h1', 'h2'], Arr::make($report['candidate']['predictions'])
            ->map(static fn (array $row) => $row['experience_id'])->val());
        $this->assertSame([false, 0], Arr::make($report['candidate']['predictions'])
            ->map(static fn (array $row) => $row['predicted'])->val());
        $this->assertNull($report['candidate']['cost']);
        $this->assertNull($report['candidate']['energy']);
        $this->assertGreaterThanOrEqual(0.0, $report['candidate']['mean_latency_ms']);
        $this->assertSame($report, json_decode(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testWorseCandidateAndTieRetainIncumbent(): void
    {
        $holdout = $this->batch([['h1', 'o1', 'x', 'yes']]);
        $evaluator = new ClassificationEvaluator(minimumSamples: 1);
        foreach (['no', 'yes'] as $prediction) {
            $report = $evaluator->compare($this->model('1', static fn () => 'yes'),
                $this->model('2', static fn () => $prediction), $holdout);
            $this->assertFalse($report['recommended']);
            $this->assertContains('no_strict_improvement', $report['reasons']);
        }
    }

    public function testInsufficientOrEmptyEvidenceDoesNotRecommend(): void
    {
        foreach ([[], [['h1', 'o1', 'x', 'yes']]] as $rows) {
            $report = (new ClassificationEvaluator(minimumSamples: 2))->compare(
                $this->model('1', static fn () => 'no'), $this->model('2', static fn () => 'yes'), $this->batch($rows));
            $this->assertFalse($report['recommended']);
            $this->assertContains('insufficient_samples', $report['reasons']);
            if ($rows === []) { $this->assertNull($report['candidate']['accuracy']); }
        }
    }

    public function testFailuresAndInvalidPredictionsAreEvidenceAgainstRecommendation(): void
    {
        foreach ([static fn () => null, static fn () => new \stdClass(), static fn () => INF,
            static fn () => throw new RuntimeException('private diagnostic')] as $prediction) {
            $report = (new ClassificationEvaluator(minimumSamples: 1))->compare(
                $this->model('1', static fn () => 'no'), $this->model('2', $prediction),
                $this->batch([['h1', 'o1', 'x', 'yes']]));
            $this->assertFalse($report['recommended']);
            $this->assertSame(1, $report['candidate']['failures']);
            $this->assertContains('prediction_failure', $report['reasons']);
            $this->assertStringNotContainsString('private diagnostic', json_encode($report));
        }
        $report = (new ClassificationEvaluator(minimumSamples: 1))->compare(
            $this->model('1', static fn () => throw new RuntimeException()), $this->model('2', static fn () => 'yes'),
            $this->batch([['h1', 'o1', 'x', 'yes']]));
        $this->assertFalse($report['recommended'], 'A broken incumbent is not a reliable benchmark.');
    }

    public function testOverlapWithEitherTrainingCorpusIsRejectedBeforePrediction(): void
    {
        foreach (['incumbent', 'candidate'] as $owner) {
            foreach ([['h1', 'other', 'different', 'yes'], ['other', 'o1', 'different', 'yes'],
                ['other', 'other', 'x', 'yes']] as $overlap) {
                $training = $this->batch([$overlap]);
                $old = $this->model('1', static fn () => 'no', $owner === 'incumbent' ? $training : null);
                $new = $this->model('2', static fn () => 'yes', $owner === 'candidate' ? $training : null);
                try {
                    (new ClassificationEvaluator())->compare($old, $new, $this->batch([['h1', 'o1', 'x', 'yes']]));
                    $this->fail('Expected training overlap rejection.');
                } catch (InvalidArgumentException $error) {
                    $this->assertStringContainsString('overlap', $error->getMessage());
                    $this->assertSame(0, $old->strategy()->predictions);
                    $this->assertSame(0, $new->strategy()->predictions);
                }
            }
        }
    }

    public function testDuplicateHoldoutEvidenceIsRejected(): void
    {
        foreach ([['h1', 'o2', 'y', 'yes'], ['h2', 'o1', 'y', 'yes'], ['h2', 'o2', 'x', 'no']] as $duplicate) {
            try {
                (new ClassificationEvaluator())->compare($this->model('1', static fn () => 'no'),
                    $this->model('2', static fn () => 'yes'), $this->batch([['h1', 'o1', 'x', 'yes'], $duplicate]));
                $this->fail('Expected duplicate rejection.');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('Duplicate', $error->getMessage());
            }
        }
    }

    public function testProjectionVersionMismatchIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ClassificationEvaluator())->compare($this->model('1', static fn () => 'no'),
            $this->model('2', static fn () => 'yes'), new TrainingBatch('intent', '2', []));
    }

    public function testExactSampleAndAccuracyPolicyBoundariesCanRecommend(): void
    {
        $report = (new ClassificationEvaluator(minimumSamples: 2, maximumSamples: 2,
            minimumAccuracy: 1.0, minimumImprovement: 0.5))->compare(
                $this->model('1', static fn () => false), $this->model('2', static fn ($input) => $input),
                $this->batch([['h1', 'o1', false, false], ['h2', 'o2', 0, 0]]));
        $this->assertTrue($report['recommended']);
        $this->assertSame([], $report['reasons']);
    }

    public function testInvalidHoldoutLabelIsRejectedBeforePrediction(): void
    {
        $old = $this->model('1', static fn () => 'yes');
        $new = $this->model('2', static fn () => 'yes');
        try {
            (new ClassificationEvaluator())->compare($old, $new, $this->batch([['h1', 'o1', 'x', null]]));
            $this->fail('Expected invalid label rejection.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(0, $old->strategy()->predictions);
            $this->assertSame(0, $new->strategy()->predictions);
        }
    }

    public function testProjectionMismatchIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ClassificationEvaluator())->compare($this->model('1', static fn () => 'no'),
            $this->model('2', static fn () => 'yes'), $this->batch([], 'other'));
    }

    public function testSharedStrategyInstanceIsRejected(): void
    {
        $old = $this->model('1', static fn () => 'yes');
        $new = new ModelCandidate('intent', '2', $old->strategy(), $this->batch([]));
        $this->expectException(InvalidArgumentException::class);
        (new ClassificationEvaluator())->compare($old, $new, $this->batch([]));
    }

    public function testIdenticalStrategyIdentityIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ClassificationEvaluator())->compare($this->model('1', static fn () => 'no'),
            $this->model('1', static fn () => 'yes'), $this->batch([]));
    }

    public function testPolicyBoundsAndLatencyLimitAreEnforced(): void
    {
        $holdout = $this->batch([['h1', 'o1', 'x', 'yes'], ['h2', 'o2', 'y', 'no']]);
        $report = (new ClassificationEvaluator(minimumSamples: 2, minimumAccuracy: 1.0))->compare(
            $this->model('1', static fn () => 'other'), $this->model('2', static fn () => 'yes'), $holdout);
        $this->assertContains('accuracy_below_minimum', $report['reasons']);
        $report = (new ClassificationEvaluator(minimumSamples: 2, minimumImprovement: 0.6))->compare(
            $this->model('1', static fn () => 'yes'), $this->model('2', static fn ($input) => $input === 'x' ? 'yes' : 'no'), $holdout);
        $this->assertContains('improvement_below_minimum', $report['reasons']);
        $report = (new ClassificationEvaluator(minimumSamples: 2, maximumMeanLatencyMs: 0.0))->compare(
            $this->model('1', static fn () => 'other'), $this->model('2', static function ($input) {
                usleep(1000);
                return $input === 'x' ? 'yes' : 'no';
            }), $holdout);
        $this->assertContains('latency_limit_exceeded', $report['reasons']);
        $this->assertFalse($report['recommended']);
    }

    public function testOversizedEvaluationIsRejectedBeforePrediction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ClassificationEvaluator(minimumSamples: 1, maximumSamples: 1))->compare(
            $this->model('1', static fn () => 'no'), $this->model('2', static fn () => 'yes'),
            $this->batch([['h1', 'o1', 'x', 'yes'], ['h2', 'o2', 'y', 'yes']]));
    }

    public function testInvalidPolicyIsRejected(): void
    {
        foreach ([['minimumSamples' => 0], ['minimumAccuracy' => NAN], ['minimumAccuracy' => 1.1],
            ['minimumImprovement' => -0.1], ['maximumMeanLatencyMs' => INF], ['maximumSamples' => 1]] as $policy) {
            try { new ClassificationEvaluator(...$policy); $this->fail('Expected invalid policy.'); }
            catch (InvalidArgumentException $error) { $this->assertNotEmpty($error->getMessage()); }
        }
    }
}
