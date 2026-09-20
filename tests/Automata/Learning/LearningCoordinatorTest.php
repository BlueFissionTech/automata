<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\{LearningCoordinator, ModelCandidate, TrainingBatch, TrainingExample, TrainingPolicy, TrainingTrigger};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\IStrategy;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LearningCoordinatorTest extends TestCase
{
    private function batch(array $labels = ['a', 'b'], string $prefix = 'new'): TrainingBatch
    {
        $rows = [];
        foreach ($labels as $i => $label) { $rows[] = new TrainingExample($prefix . $i, 'label-' . $i, 'input-' . $i, $label); }
        return new TrainingBatch('intent', '1', $rows);
    }

    private function model(): IStrategy
    {
        return new class implements IStrategy {
            public int $trains = 0;
            public function train(array $samples, array $labels, float $testSize = 0.2) { ++$this->trains; }
            public function predict($input) { return 'a'; }
            public function accuracy(): float { return 0.0; }
            public function saveModel(string $path): bool { return false; }
            public function loadModel(string $path): bool { return false; }
        };
    }

    private function owner(?ModelCandidate $initial = null, int $limit = 1000): LearningCoordinator
    {
        return new LearningCoordinator($initial ?? new ModelCandidate('intent', 'prior', $this->model(), $this->batch([])),
            new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 2), $limit);
    }

    private function approve(): GovernanceDecision { return GovernanceDecision::approved(); }
    private function trainer(): \Closure { return static fn (IStrategy $model, TrainingBatch $batch) => $model->train($batch->samples(), $batch->labels()); }

    public function testTrainingWaitsForEvidenceAndRequiresSeparateApproval(): void
    {
        $owner = $this->owner();
        $calls = 0;
        $never = static function () use (&$calls) { ++$calls; throw new RuntimeException('must not execute'); };
        $deferred = $owner->train('small', 'v1', $this->batch(['a']), new TrainingTrigger(), $never, $never, $never);
        self::assertSame('deferred', $deferred->status());
        self::assertNull($deferred->candidate());
        $denied = $owner->train('denied', 'v1', $this->batch(), new TrainingTrigger(), $never, $never,
            static fn () => GovernanceDecision::denied());
        self::assertSame('denied', $denied->status());
        self::assertSame(0, $calls);
    }

    public function testTrainingCreatesIsolatedCandidateAndIdenticalRetryDoesNotInvokeCallbacks(): void
    {
        $prior = new ModelCandidate('intent', 'prior', $this->model(), $this->batch([]));
        $owner = $this->owner($prior);
        $batch = $this->batch();
        $result = $owner->train('train', 'v1', $batch, new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $this->approve(...));
        self::assertSame('trained', $result->status());
        self::assertSame(['id' => 'intent', 'version' => 'v1'], $result->candidate()->identity());
        self::assertSame($batch, $result->candidate()->training());
        self::assertSame(1, $result->candidate()->strategy()->trains);
        self::assertSame(0, $prior->strategy()->trains);
        $never = static fn () => throw new RuntimeException('replay invoked callback');
        self::assertSame($result, $owner->train('train', 'v1', $this->batch(), new TrainingTrigger(), $never, $never, $never));
        self::assertSame('deferred', $owner->train('same-evidence', 'v2', $batch, new TrainingTrigger(), $never, $never, $never)->status());
        self::assertCount(2, $owner->results());
    }

    public function testConflictingRequestAndIncumbentAliasingCannotTrain(): void
    {
        $prior = new ModelCandidate('intent', 'prior', $this->model(), $this->batch([]));
        $owner = $this->owner($prior);
        $failed = $owner->train('alias', 'v1', $this->batch(), new TrainingTrigger(), fn () => $prior->strategy(),
            $this->trainer(), $this->approve(...));
        self::assertSame('uncertain', $failed->status());
        self::assertSame(0, $prior->strategy()->trains);
        $this->expectException(LogicException::class);
        $owner->train('alias', 'v2', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $this->approve(...));
    }

    public function testPartialTrainingIsRetainedWithoutBlindRetry(): void
    {
        $owner = $this->owner();
        $calls = 0;
        $train = static function () use (&$calls): void { ++$calls; throw new RuntimeException('sensitive payload'); };
        $result = $owner->train('partial', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $train, $this->approve(...));
        self::assertSame('uncertain', $result->status());
        self::assertNull($result->candidate());
        self::assertSame(RuntimeException::class, $result->toArray()['failure']);
        self::assertStringNotContainsString('sensitive payload', json_encode($result->toArray()));
        self::assertSame($result, $owner->train('partial', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $train, $this->approve(...)));
        self::assertSame(1, $calls);
        $this->expectException(LogicException::class);
        $owner->train('blind-new-id', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $train, $this->approve(...));
    }

    public function testCapacityStillPermitsExistingReceiptReplay(): void
    {
        $owner = $this->owner(limit: 1);
        $factory = fn () => $this->model();
        $result = $owner->train('one', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...));
        self::assertSame($result, $owner->train('one', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...)));
        $this->expectException(LogicException::class);
        $owner->train('two', 'v2', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...));
    }

    public function testReentrantApprovalCannotRunNestedTraining(): void
    {
        $owner = $this->owner();
        $authorize = function () use ($owner) {
            $owner->train('nested', 'v2', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $this->approve(...));
            return $this->approve();
        };
        $this->expectException(LogicException::class);
        $owner->train('outer', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $authorize);
    }

    public function testPolicyCountsNewLineageAndExplicitCorrections(): void
    {
        $policy = new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 10, minimumCorrections: 1);
        $prior = $this->batch(['a'], 'old');
        $trigger = new TrainingTrigger(correctedOutcomes: [['experience_id' => 'new1', 'outcome_id' => 'label-1']]);
        $report = $policy->assess($this->batch(), $prior, $trigger);
        self::assertSame(2, $report['new_examples']);
        self::assertSame(1, $report['corrected_examples']);
        self::assertTrue($report['eligible']);
        self::assertGreaterThanOrEqual(1, $report['pressure']);
        self::assertFalse($policy->assess($this->batch(), $this->batch(), new TrainingTrigger())['eligible']);
    }

    public function testPressureAndOperatorRequestsDoNotBypassSampleFloor(): void
    {
        $policy = new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 10);
        self::assertTrue($policy->assess($this->batch(), $this->batch([]), new TrainingTrigger(['drift' => 1.0]))['eligible']);
        self::assertFalse($policy->assess($this->batch(['a']), $this->batch([]), new TrainingTrigger(operatorRequested: true))['eligible']);
        self::assertTrue($policy->assess($this->batch(), $this->batch(), new TrainingTrigger(operatorRequested: true))['eligible']);
    }

    public function testDuplicateLineageCannotInflateTrainingPressure(): void
    {
        $row = new TrainingExample('same', 'outcome', 'input', 'label');
        $batch = new TrainingBatch('intent', '1', [$row, $row]);
        $this->expectException(InvalidArgumentException::class);
        (new TrainingPolicy())->assess($batch, $this->batch([]), new TrainingTrigger());
    }

    public function testRewrittenHistoricalOutcomeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TrainingPolicy())->assess($this->batch(['changed']), $this->batch(['a']), new TrainingTrigger());
    }

    public function testUnknownOrMalformedPressureIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TrainingTrigger(['drift' => '1']);
    }

    public function testCorrectionMustReferToNewEvidenceInThisBatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TrainingPolicy())->assess($this->batch(), $this->batch(),
            new TrainingTrigger(correctedOutcomes: [['experience_id' => 'new0', 'outcome_id' => 'label-0']]));
    }
    public function testSilentTrainerFailureIsNotReportedAsTrained(): void
    {
        $owner = $this->owner();
        $result = $owner->train('silent', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(),
            static fn () => false, $this->approve(...));
        self::assertSame('uncertain', $result->status());
        self::assertNull($result->candidate());
    }

    public function testPendingAndSteeredDecisionsCannotConstructCandidates(): void
    {
        $owner = $this->owner();
        $never = static fn () => throw new RuntimeException('Candidate construction must not occur.');
        foreach (['pending', 'steered', 'unknown'] as $status) {
            $result = $owner->train($status, 'v1', $this->batch(), new TrainingTrigger(), $never, $never,
                static fn () => new GovernanceDecision(['status' => $status]));
            self::assertSame('denied', $result->status());
            self::assertSame($status, $result->toArray()['decision']['status']);
        }
    }

    public function testMalformedAuthorizationFailsBeforeFactoryAndCanBeReviewedAgain(): void
    {
        $owner = $this->owner();
        $factoryCalls = 0;
        $factory = function () use (&$factoryCalls) { ++$factoryCalls; return $this->model(); };
        try {
            $owner->train('review', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), static fn () => true);
            self::fail('Boolean approval must not authorize training.');
        } catch (InvalidArgumentException) { self::assertSame(0, $factoryCalls); }
        self::assertCount(0, $owner->results());
        self::assertSame('trained', $owner->train('review', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...))->status());
    }

    public function testLearnedLineageSurvivesMovingTrainingWindows(): void
    {
        $owner = $this->owner();
        $factory = fn () => $this->model();
        $first = $owner->train('first', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...));
        $second = $owner->train('second', 'v2', $this->batch(prefix: 'later'), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...));
        $repeat = $owner->train('old-window', 'v3', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...));
        self::assertSame('trained', $first->status());
        self::assertSame('trained', $second->status());
        self::assertSame('deferred', $repeat->status());
        self::assertSame(0, $repeat->toArray()['assessment']['new_examples']);
    }

    public function testEvidenceRetentionExhaustionPrecedesApprovalAndConstruction(): void
    {
        $owner = new LearningCoordinator(new ModelCandidate('intent', 'prior', $this->model(), $this->batch([])),
            new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 2), maximumEvidence: 2);
        $owner->train('first', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $this->approve(...));
        $never = static fn () => throw new RuntimeException('Capacity must be checked first.');
        $this->expectException(LogicException::class);
        $owner->train('more', 'v2', $this->batch(prefix: 'more'), new TrainingTrigger(), $never, $never, $never);
    }

    public function testPreviouslyTrainedStrategyCannotBeReusedUnderANewVersion(): void
    {
        $owner = $this->owner();
        $first = $owner->train('first', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $this->approve(...));
        $second = $owner->train('reuse', 'v2', $this->batch(), new TrainingTrigger(operatorRequested: true),
            fn () => $first->candidate()->strategy(), $this->trainer(), $this->approve(...));
        self::assertSame('uncertain', $second->status());
        self::assertSame(1, $first->candidate()->strategy()->trains);
    }

    public function testReentrantTrainerCannotStartAnotherJob(): void
    {
        $owner = $this->owner();
        $trainer = function () use ($owner): void {
            $owner->train('nested', 'v2', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $this->trainer(), $this->approve(...));
        };
        $result = $owner->train('outer', 'v1', $this->batch(), new TrainingTrigger(), fn () => $this->model(), $trainer, $this->approve(...));
        self::assertSame('uncertain', $result->status());
        self::assertSame(LogicException::class, $result->toArray()['failure']);
        self::assertCount(1, $owner->results());
    }

    public function testSampleCeilingAndProjectionMismatchPrecedePressure(): void
    {
        $policy = new TrainingPolicy(minimumExamples: 1, maximumExamples: 1);
        try {
            $policy->assess($this->batch(), $this->batch([]), new TrainingTrigger(operatorRequested: true));
            self::fail('Operator request cannot bypass maximum samples.');
        } catch (InvalidArgumentException) { self::assertTrue(true); }
        $this->expectException(InvalidArgumentException::class);
        $policy->assess(new TrainingBatch('other', '1', []), $this->batch([]), new TrainingTrigger());
    }

    public function testUnknownAndNonfiniteSignalsAreRejectedWithoutCoercion(): void
    {
        foreach ([['unknown' => 1], ['drift' => null], ['drift' => true], ['drift' => INF], ['drift' => NAN], ['drift' => -1], ['drift' => 1.1]] as $signals) {
            try { new TrainingTrigger($signals); self::fail('Invalid signal accepted.'); }
            catch (InvalidArgumentException) { self::assertTrue(true); }
        }
    }

    public function testExplicitTrainingCostCanDeferAccumulatedEvidence(): void
    {
        $policy = new TrainingPolicy(minimumExamples: 2, minimumNewExamples: 2);
        $report = $policy->assess($this->batch(), $this->batch([]), new TrainingTrigger(['training_cost' => 1.0]));
        self::assertFalse($report['eligible']);
        self::assertSame(0.0, $report['pressure']);
        self::assertArrayNotHasKey('cost', $report['trigger']['signals']);
    }
    private function withHooks(callable $test): void
    {
        $reflection = new \ReflectionClass(\BlueFission\DevElation::class);
        $saved = $reflection->getStaticProperties();
        try { \BlueFission\DevElation::up(); $test(); }
        finally {
            foreach (['_isActive', '_filters', '_actions'] as $field) { $reflection->setStaticPropertyValue($field, $saved[$field]); }
        }
    }

    public function testPressureFilterCannotBypassSampleFloorOrApproval(): void
    {
        $this->withHooks(function (): void {
            \BlueFission\DevElation::filter('automata.learning.pressure', static fn () => 100.0);
            $owner = $this->owner();
            $never = static fn () => throw new RuntimeException('Must not construct or train.');
            self::assertSame('deferred', $owner->train('small', 'v1', $this->batch(['a']), new TrainingTrigger(), $never, $never, $never)->status());
            self::assertSame('denied', $owner->train('denied', 'v1', $this->batch(), new TrainingTrigger(), $never, $never, static fn () => GovernanceDecision::denied())->status());
        });
    }

    public function testInvalidPressureFilterFailsBeforeInvocation(): void
    {
        $this->withHooks(function (): void {
            \BlueFission\DevElation::filter('automata.learning.pressure', static fn () => NAN);
            $this->expectException(InvalidArgumentException::class);
            (new TrainingPolicy())->assess($this->batch(), $this->batch([]), new TrainingTrigger());
        });
    }

    public function testTrainingHooksAreObservedOnceAndCannotEraseCompletedWork(): void
    {
        $this->withHooks(function (): void {
            $events = [];
            foreach (['requested', 'started', 'completed'] as $event) {
                \BlueFission\DevElation::action('automata.learning.training.' . $event, static function (array $record) use (&$events, $event): void {
                    $events[] = [$event, $record['request_id']];
                    if ($event === 'completed') { throw new RuntimeException('telemetry unavailable'); }
                });
            }
            $owner = $this->owner();
            $factory = fn () => $this->model();
            $result = $owner->train('hooks', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...));
            self::assertSame('trained', $result->status());
            self::assertSame([['requested', 'hooks'], ['started', 'hooks'], ['completed', 'hooks']], $events);
            self::assertCount(1, $owner->eventErrors());
            self::assertSame(RuntimeException::class, $owner->eventErrors()[0]['error_type']);
            self::assertSame($result, $owner->train('hooks', 'v1', $this->batch(), new TrainingTrigger(), $factory, $this->trainer(), $this->approve(...)));
            self::assertCount(3, $events);
        });
    }
}
