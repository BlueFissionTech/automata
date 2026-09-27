<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\ClassificationEvaluator;
use BlueFission\Automata\Learning\ModelCandidate;
use BlueFission\Automata\Learning\ModelLifecycle;
use BlueFission\Automata\Learning\TrainingBatch;
use BlueFission\Automata\Learning\TrainingExample;
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Strategy\IStrategy;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ModelLifecycleTest extends TestCase
{
    private function model(string $version, callable $predict): ModelCandidate
    {
        $strategy = new class($predict) implements IStrategy {
            public int $calls = 0;
            public function __construct(private $predictor) {}
            public function predict($input) { ++$this->calls; return ($this->predictor)($input); }
            public function train(array $samples, array $labels, float $testSize = 0.2) { throw new LogicException(); }
            public function accuracy(): float { throw new LogicException(); }
            public function saveModel(string $path): bool { throw new LogicException(); }
            public function loadModel(string $path): bool { throw new LogicException(); }
        };
        return new ModelCandidate('intent', $version, $strategy, new TrainingBatch('intent', '1', []));
    }

    private function holdout(string $id = 'h1'): TrainingBatch
    {
        return new TrainingBatch('intent', '1', [new TrainingExample($id, 'o1', 'query', 'yes')]);
    }

    private function lifecycle(?ModelCandidate $old = null, int $maximumRequests = 1000): ModelLifecycle
    {
        return new ModelLifecycle($old ?? $this->model('1', static fn () => 'no'),
            new ClassificationEvaluator(minimumSamples: 1), $maximumRequests);
    }

    public function testPromotionAndRollbackChangeActualPredictionsWithSeparateApproval(): void
    {
        $old = $this->model('1', static fn () => 'no');
        $new = $this->model('2', static fn () => 'yes');
        $lifecycle = $this->lifecycle($old);
        $seen = [];
        $approve = static function (array $request) use (&$seen): GovernanceDecision {
            $seen[] = $request;
            return GovernanceDecision::approved('reviewed', ['review_id' => 'r1']);
        };
        $receipt = $lifecycle->promote('p1', 0, $new, $this->holdout(), $approve);
        $this->assertTrue($receipt['applied']);
        $this->assertSame(1, $lifecycle->revision());
        $this->assertSame($new, $lifecycle->active());
        $this->assertSame('yes', $lifecycle->active()->strategy()->predict('query'));
        $this->assertSame('2', $seen[0]['to']['version']);
        $this->assertTrue($seen[0]['evaluation']['recommended']);
        $this->assertSame(['review_id' => 'r1'], $receipt['decision']['payload']);
        $rollback = $lifecycle->rollback('r1', 1, $approve);
        $this->assertTrue($rollback['applied']);
        $this->assertSame(2, $lifecycle->revision());
        $this->assertSame($old, $lifecycle->active());
        $this->assertSame('no', $lifecycle->active()->strategy()->predict('query'));
        $this->assertSame('rollback', $seen[1]['operation']);
        $this->assertSame([$receipt, $rollback], $lifecycle->receipts());
    }

    public function testHistoricalRetryAfterRollbackDoesNotReactivateOrCallCallbacks(): void
    {
        $old = $this->model('1', static fn () => 'no');
        $new = $this->model('2', static fn () => 'yes');
        $lifecycle = $this->lifecycle($old);
        $receipt = $lifecycle->promote('p', 0, $new, $this->holdout(), static fn () => GovernanceDecision::approved());
        $rollback = $lifecycle->rollback('r', 1, static fn () => GovernanceDecision::approved());
        $calls = $new->strategy()->calls;
        $never = static fn () => throw new LogicException('Must not be invoked.');
        $this->assertSame($receipt, $lifecycle->promote('p', 0, $new, $this->holdout(), $never));
        $this->assertSame($rollback, $lifecycle->rollback('r', 1, $never));
        $this->assertSame($calls, $new->strategy()->calls);
        $this->assertSame($old, $lifecycle->active());
        $this->assertSame(2, $lifecycle->revision());
    }

    public function testRegressionNeverRequestsApproval(): void
    {
        $old = $this->model('1', static fn () => 'yes');
        $lifecycle = $this->lifecycle($old);
        $receipt = $lifecycle->promote('p', 0, $this->model('2', static fn () => 'no'), $this->holdout(),
            static fn () => throw new LogicException('Must not approve a regression.'));
        $this->assertFalse($receipt['applied']);
        $this->assertSame('evaluation_rejected', $receipt['reason']);
        $this->assertNull($receipt['decision']);
        $this->assertSame($old, $lifecycle->active());
    }

    public function testOnlyExplicitApprovedDecisionCanActivate(): void
    {
        foreach ([GovernanceDecision::denied(), GovernanceDecision::pending(),
            GovernanceDecision::steered(['version' => 'other']), new GovernanceDecision(['status' => 'unknown'])] as $decision) {
            $old = $this->model('1', static fn () => 'no');
            $lifecycle = $this->lifecycle($old);
            $receipt = $lifecycle->promote('p', 0, $this->model('2', static fn () => 'yes'), $this->holdout(),
                static fn () => $decision);
            $this->assertFalse($receipt['applied']);
            $this->assertSame('governance_not_approved', $receipt['reason']);
            $this->assertSame($old, $lifecycle->active());
            $this->assertSame(0, $lifecycle->revision());
        }
    }

    public function testInvalidOrThrowingAuthorizationCannotMutateAndReleasesGuard(): void
    {
        foreach ([static fn () => true, static fn () => throw new RuntimeException('failed'),
            static fn () => GovernanceDecision::approved('', ['invalid' => new \stdClass()])] as $authorize) {
            $old = $this->model('1', static fn () => 'no');
            $lifecycle = $this->lifecycle($old);
            $new = $this->model('2', static fn () => 'yes');
            try {
                $lifecycle->promote('p', 0, $new, $this->holdout(), $authorize);
                $this->fail('Expected invalid authorization failure.');
            } catch (InvalidArgumentException | RuntimeException $error) {
                $this->assertSame($old, $lifecycle->active());
                $this->assertSame(0, $lifecycle->revision());
                $this->assertSame([], $lifecycle->receipts());
            }
            $this->assertTrue($lifecycle->promote('p', 0, $new, $this->holdout(),
                static fn () => GovernanceDecision::approved())['applied']);
        }
    }

    public function testStaleRevisionAfterRollbackFailsBeforePrediction(): void
    {
        $lifecycle = $this->lifecycle();
        $new = $this->model('2', static fn () => 'yes');
        $approve = static fn () => GovernanceDecision::approved();
        $lifecycle->promote('p', 0, $new, $this->holdout(), $approve);
        $lifecycle->rollback('r', 1, $approve);
        $calls = $new->strategy()->calls;
        try {
            $lifecycle->promote('new-request', 0, $new, $this->holdout(), $approve);
            $this->fail('Expected stale revision.');
        } catch (LogicException $error) {
            $this->assertSame($calls, $new->strategy()->calls);
            $this->assertSame(2, $lifecycle->revision());
        }
    }

    public function testConflictingReplayIsRejected(): void
    {
        $lifecycle = $this->lifecycle();
        $new = $this->model('2', static fn () => 'yes');
        $approve = static fn () => GovernanceDecision::approved();
        $lifecycle->promote('p', 0, $new, $this->holdout(), $approve);
        $attempts = [
            fn () => $lifecycle->promote('p', 1, $new, $this->holdout(), $approve),
            fn () => $lifecycle->promote('p', 0, $new, $this->holdout('other'), $approve),
            fn () => $lifecycle->promote('p', 0, $this->model('2', static fn () => 'yes'), $this->holdout(), $approve),
            fn () => $lifecycle->rollback('p', 0, $approve),
        ];
        foreach ($attempts as $attempt) {
            try { $attempt(); $this->fail('Expected conflicting request.'); }
            catch (LogicException $error) { $this->assertSame(1, $lifecycle->revision()); }
        }
    }

    public function testRegisteredVersionAndStrategyCannotBeRelabelled(): void
    {
        $old = $this->model('1', static fn () => 'no');
        $lifecycle = $this->lifecycle($old);
        foreach ([$this->model('1', static fn () => 'yes'),
            new ModelCandidate('intent', '2', $old->strategy(), $old->training())] as $candidate) {
            try {
                $lifecycle->promote('p', 0, $candidate, $this->holdout(), static fn () => GovernanceDecision::approved());
                $this->fail('Expected version binding rejection.');
            } catch (InvalidArgumentException $error) { $this->assertSame(0, $old->strategy()->calls); }
        }
    }

    public function testReentrantMutationDuringAuthorizationIsRejected(): void
    {
        $lifecycle = $this->lifecycle();
        $receipt = $lifecycle->promote('p', 0, $this->model('2', static fn () => 'yes'), $this->holdout(),
            function () use ($lifecycle): GovernanceDecision {
                try { $lifecycle->rollback('nested', 0, static fn () => GovernanceDecision::approved()); }
                catch (LogicException $error) {
                    $this->assertSame(0, $lifecycle->revision());
                    return GovernanceDecision::approved();
                }
                throw new RuntimeException('Reentrant mutation was allowed.');
            });
        $this->assertTrue($receipt['applied']);
        $this->assertCount(1, $lifecycle->receipts());
    }

    public function testFullRetentionAllowsReplayButRejectsNewWorkBeforeCallbacks(): void
    {
        $lifecycle = $this->lifecycle(maximumRequests: 1);
        $never = static fn () => throw new RuntimeException('Must not call.');
        $receipt = $lifecycle->rollback('r', 0, $never);
        $this->assertSame('no_previous_model', $receipt['reason']);
        $this->assertSame($receipt, $lifecycle->rollback('r', 0, $never));
        $new = $this->model('2', $never);
        $this->expectException(LogicException::class);
        $lifecycle->promote('p', 0, $new, $this->holdout(), $never);
    }

    public function testRollbackDenialAndLaterRepromotionPreserveOrder(): void
    {
        $old = $this->model('1', static fn () => 'no');
        $lifecycle = $this->lifecycle($old);
        $new = $this->model('2', static fn () => 'yes');
        $approve = static fn () => GovernanceDecision::approved();
        $lifecycle->promote('p', 0, $new, $this->holdout(), $approve);
        $this->assertFalse($lifecycle->rollback('denied', 1, static fn () => GovernanceDecision::denied())['applied']);
        $this->assertSame($new, $lifecycle->active());
        $lifecycle->rollback('r', 1, $approve);
        $this->assertTrue($lifecycle->promote('again', 2, $new, $this->holdout(), $approve)['applied']);
        $this->assertSame(3, $lifecycle->revision());
        $lifecycle->rollback('r2', 3, $approve);
        $this->assertSame($old, $lifecycle->active());
    }

    public function testMultipleActivationLevelsRollbackInOrder(): void
    {
        $old = $this->model('1', static fn () => 'no');
        $middle = $this->model('2', static fn ($input) => $input === 'q1' ? 'yes' : 'no');
        $best = $this->model('3', static fn () => 'yes');
        $lifecycle = new ModelLifecycle($old, new ClassificationEvaluator(minimumSamples: 2, minimumAccuracy: 0.5));
        $holdout = new TrainingBatch('intent', '1', [
            new TrainingExample('h1', 'o1', 'q1', 'yes'), new TrainingExample('h2', 'o2', 'q2', 'yes'),
        ]);
        $approve = static fn () => GovernanceDecision::approved();
        $this->assertTrue($lifecycle->promote('p1', 0, $middle, $holdout, $approve)['applied']);
        $this->assertTrue($lifecycle->promote('p2', 1, $best, $holdout, $approve)['applied']);
        $this->assertSame($best, $lifecycle->active());
        $lifecycle->rollback('r1', 2, $approve);
        $this->assertSame($middle, $lifecycle->active());
        $lifecycle->rollback('r2', 3, $approve);
        $this->assertSame($old, $lifecycle->active());
        $this->assertSame(4, $lifecycle->revision());
    }

    public function testReentrantPredictionIsRecordedAsFailureWithoutApproval(): void
    {
        $lifecycle = $this->lifecycle();
        $new = $this->model('2', static function () use ($lifecycle) {
            $lifecycle->rollback('nested', 0, static fn () => GovernanceDecision::approved());
            return 'yes';
        });
        $receipt = $lifecycle->promote('p', 0, $new, $this->holdout(),
            static fn () => throw new RuntimeException('Must not approve failed prediction.'));
        $this->assertFalse($receipt['applied']);
        $this->assertContains('prediction_failure', $receipt['evaluation']['reasons']);
        $this->assertSame(0, $lifecycle->revision());
        $this->assertCount(1, $lifecycle->receipts());
        $this->assertSame('no_previous_model', $lifecycle->rollback('after', 0,
            static fn () => GovernanceDecision::approved())['reason']);
    }

    public function testInvalidIdentifiersAndRevisionsFailBeforePrediction(): void
    {
        $lifecycle = $this->lifecycle();
        $new = $this->model('2', static fn () => throw new RuntimeException('Must not predict.'));
        foreach ([[' ', 0], ['p', -1]] as [$id, $revision]) {
            try {
                $lifecycle->promote($id, $revision, $new, $this->holdout(), static fn () => GovernanceDecision::approved());
                $this->fail('Expected invalid request rejection.');
            } catch (InvalidArgumentException $error) { $this->assertSame(0, $new->strategy()->calls); }
        }
    }

    public function testInvalidBoundsFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->lifecycle(maximumRequests: 0);
    }
}
