<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\{LearningCoordinator, ModelCandidate, TrainingBatch, TrainingExample, TrainingPolicy, TrainingTrigger};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\IStrategy;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/** Restart controls distinguish restored evidence from another training attempt. */
final class LearningCheckpointTest extends TestCase
{
    private function batch(string $id, mixed $label = 'wait'): TrainingBatch
    {
        return new TrainingBatch('features', '1', [new TrainingExample($id, 'review', [$id], $label)]);
    }

    private function model(): IStrategy
    {
        return new class implements IStrategy {
            public int $trains = 0;
            public function train(array $samples, array $labels, float $testSize = 0.2) { ++$this->trains; }
            public function predict($input) { return 'wait'; }
            public function accuracy(): float { return 0.0; }
            public function saveModel(string $path): bool { return false; }
            public function loadModel(string $path): bool { return false; }
        };
    }

    private function owner(?TrainingPolicy $policy = null): LearningCoordinator
    {
        return new LearningCoordinator(new ModelCandidate('intent', 'anchor', $this->model(), $this->batch('anchor')),
            $policy ?? new TrainingPolicy(minimumExamples: 1, minimumNewExamples: 1));
    }

    private function fit(LearningCoordinator $owner, string $id, TrainingBatch $batch)
    {
        return $owner->train($id, $id, $batch, new TrainingTrigger(), fn () => $this->model(),
            static function (IStrategy $model, TrainingBatch $rows): void { $model->train($rows->samples(), $rows->labels(), 0); },
            static fn () => GovernanceDecision::approved());
    }

    public function testRestoreRetainsLineageRequestsAndContinuesWithAnchorRehearsal(): void
    {
        $owner = $this->owner();
        $batch = $owner->evidence()->merge($this->batch('session1', 'assist'));
        $this->fit($owner, 'v1', $batch);
        $snapshot = $owner->checkpoint();
        $loaded = [];
        $restored = $this->owner()->restore($snapshot, function (array $identity, TrainingBatch $training) use (&$loaded) {
            $loaded[] = [$identity, $training->samples()];
            return $this->model();
        });
        self::assertSame($snapshot, $restored->checkpoint());
        self::assertCount(2, $loaded);
        $never = static fn () => throw new LogicException('Replay must not invoke a callback.');
        $retry = $restored->train('v1', 'v1', $batch, new TrainingTrigger(), $never, $never, $never);
        self::assertSame('trained', $retry->status());
        self::assertSame(0, $retry->candidate()->strategy()->trains);
        $next = $this->fit($restored, 'v2', $restored->evidence()->merge($this->batch('session2', 'decline')));
        self::assertSame(['wait', 'assist', 'decline'], $next->candidate()->training()->labels());
        self::assertSame(1, $next->toArray()['assessment']['new_examples']);
        self::assertSame(['wait', 'assist'], $owner->evidence()->labels());
        self::assertSame('deferred', $restored->train('duplicate-window', 'v3', $batch, new TrainingTrigger(), $never, $never, $never)->status());
    }

    public function testMergeDetachesAndRejectsConflictingLineageOrProjection(): void
    {
        $anchor = $this->batch('anchor');
        self::assertSame($anchor->toArray(), $anchor->merge($anchor)->toArray());
        self::assertSame($anchor->toArray(), TrainingBatch::fromArray($anchor->toArray())->toArray());
        foreach ([$this->batch('anchor', 'changed'), new TrainingBatch('foreign', '1', [])] as $bad) {
            try { $anchor->merge($bad); self::fail('Incompatible evidence accepted.'); }
            catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        }
        $bad = $anchor->toArray();
        $bad['examples'][0]['authority'] = true;
        $this->expectException(InvalidArgumentException::class);
        TrainingBatch::fromArray($bad);
    }

    public function testWrongPolicyScopeAndCorruptCheckpointsFailBeforeModelLoading(): void
    {
        $owner = $this->owner();
        $this->fit($owner, 'v1', $owner->evidence()->merge($this->batch('new')));
        $snapshot = $owner->checkpoint();
        $corrupt = $snapshot;
        $corrupt['fingerprint'] = 'wrong';
        $never = static fn () => throw new LogicException('Invalid checkpoints must not load models.');
        foreach ([$corrupt, [...$snapshot, 'schema_version' => 2]] as $bad) {
            try { $owner->restore($bad, $never); self::fail('Corruption accepted.'); }
            catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        }
        $changed = $this->owner(new TrainingPolicy(minimumExamples: 1, minimumNewExamples: 2));
        $this->expectException(InvalidArgumentException::class);
        $changed->restore($snapshot, $never);
    }

    public function testUncertainTrainingRemainsBlockedAfterRestartAndCanReplayItsReceipt(): void
    {
        $owner = $this->owner();
        $batch = $this->batch('partial');
        $failure = static fn () => throw new LogicException('partially trained');
        $first = $owner->train('partial', 'v1', $batch, new TrainingTrigger(), fn () => $this->model(), $failure,
            static fn () => GovernanceDecision::approved());
        $restored = $this->owner()->restore($owner->checkpoint(), fn () => $this->model());
        self::assertSame($first->toArray(), $restored->train('partial', 'v1', $batch, new TrainingTrigger(), $failure, $failure, $failure)->toArray());
        $this->expectException(LogicException::class);
        $this->fit($restored, 'v2', $this->batch('next'));
    }

    public function testLoaderCannotAliasModelsAndRestoreDoesNotMutateTheReceiver(): void
    {
        $owner = $this->owner();
        $this->fit($owner, 'v1', $this->batch('new'));
        $snapshot = $owner->checkpoint();
        $shared = $this->model();
        try { $owner->restore($snapshot, static fn () => $shared); self::fail('Aliased models accepted.'); }
        catch (InvalidArgumentException) { self::assertSame($snapshot, $owner->checkpoint()); }
        $this->expectException(InvalidArgumentException::class);
        $owner->restore($snapshot, static fn () => null);
    }

    public function testCheckpointCannotCaptureAnInFlightTrainingOperation(): void
    {
        $owner = $this->owner();
        $result = $owner->train('capture', 'v1', $this->batch('new'), new TrainingTrigger(), fn () => $this->model(),
            static function () use ($owner): void { $owner->checkpoint(); }, static fn () => GovernanceDecision::approved());
        self::assertSame('uncertain', $result->status());
        self::assertSame(LogicException::class, $result->toArray()['failure']);
    }

    public function testDeferredAndDeniedRequestsSurviveWithoutReservingTheirCandidateVersion(): void
    {
        $owner = $this->owner();
        $never = static fn () => throw new LogicException('Must not train.');
        $owner->train('small', 'v1', new TrainingBatch('features', '1', []), new TrainingTrigger(), $never, $never, $never);
        $owner->train('denied', 'v1', $this->batch('new'), new TrainingTrigger(), $never, $never, static fn () => GovernanceDecision::denied());
        $restored = $this->owner()->restore($owner->checkpoint(), fn () => $this->model());
        self::assertSame(['deferred', 'denied'], array_map(static fn ($row) => $row->status(), $restored->results()));
        self::assertSame('trained', $this->fit($restored, 'v1', $this->batch('new'))->status());
        self::assertCount(3, $restored->results());
        $this->expectException(LogicException::class);
        $restored->train('denied', 'v1', $this->batch('changed'), new TrainingTrigger(), $never, $never, $never);
    }

    public function testRehashedInvalidHistoryStillFailsBeforeLoadingArtifacts(): void
    {
        $owner = $this->owner();
        $this->fit($owner, 'v1', $this->batch('new'));
        $snapshot = $owner->checkpoint();
        $changes = [
            static function (&$data): void { $data['requests'][0]['result']['candidate']['version'] = 'wrong'; },
            static function (&$data): void { $data['requests'][0]['result']['decision']['status'] = 'denied'; },
            static function (&$data): void { $data['requests'][0]['result']['assessment']['new_examples'] = 99; },
            static function (&$data): void { $data['requests'][] = $data['requests'][0]; },
            static function (&$data): void { $data['requests'][0]['request']['batch']['projection_version'] = '2'; },
            static function (&$data): void { $data['requests'][0]['request']['trigger']['operator_requested'] = 'yes'; },
            static function (&$data): void { $data['event_errors'][] = ['request_id' => 'missing', 'event' => 'started', 'error_type' => 'Failure']; },
        ];
        $never = static fn () => throw new LogicException('Malformed history reached the loader.');
        foreach ($changes as $change) {
            $bad = $snapshot;
            unset($bad['fingerprint']);
            $change($bad);
            $bad['fingerprint'] = \BlueFission\Automata\Support\RecordSnapshot::fingerprint($bad);
            try { $owner->restore($bad, $never); self::fail('Invalid history accepted.'); }
            catch (InvalidArgumentException) { self::addToAssertionCount(1); }
        }
    }

    public function testLoaderCannotReenterTrainingOnTheReceiver(): void
    {
        $owner = $this->owner();
        $snapshot = $owner->checkpoint();
        try {
            $owner->restore($snapshot, function () use ($owner) {
                $this->fit($owner, 'nested', $this->batch('nested'));
                return $this->model();
            });
            self::fail('Reentrant training during restore accepted.');
        } catch (LogicException) { self::assertSame($snapshot, $owner->checkpoint()); }
    }

    public function testObserverDiagnosticFromAnInterruptedAuthorizationCanBeRestored(): void
    {
        $hooks = new \ReflectionClass(\BlueFission\DevElation::class);
        $saved = $hooks->getStaticProperties();
        try {
            \BlueFission\DevElation::up();
            \BlueFission\DevElation::action('automata.learning.training.requested',
                static fn () => throw new LogicException('observer unavailable'));
            $owner = $this->owner();
            $never = static fn () => throw new LogicException('authorization unavailable');
            try { $owner->train('interrupted', 'v1', $this->batch('new'), new TrainingTrigger(), $never, $never, $never); }
            catch (LogicException) { /* No training began and no result was committed. */ }
            self::assertCount(0, $owner->results());
            self::assertCount(1, $owner->eventErrors());
            $restored = $this->owner()->restore($owner->checkpoint(), fn () => $this->model());
            self::assertSame($owner->checkpoint(), $restored->checkpoint());
        } finally {
            foreach (['_isActive', '_filters', '_actions'] as $field) { $hooks->setStaticPropertyValue($field, $saved[$field]); }
        }
    }
}
