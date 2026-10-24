<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\{IBundleStore, LearningBundle, BundleLifecycle, BundleSnapshot};
use BlueFission\Automata\LLM\Agent\Governance\GovernanceDecision;
use BlueFission\Automata\Support\RecordSnapshot;
use LogicException;
use InvalidArgumentException;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/** Controlled store faults distinguish committed state from an acknowledgement. */
final class BundleTestStore implements IBundleStore
{
    public ?array $state = null;
    public string $fault = '';
    public int $writes = 0;
    public function load(): ?array { return RecordSnapshot::copy($this->state); }
    public function compareAndSwap(?string $expectedDigest, array $replacement): void
    {
        if ($expectedDigest !== ($this->state === null ? null : RecordSnapshot::fingerprint($this->state))) { throw new LogicException('Stale store snapshot.'); }
        if ($this->fault === 'before') { throw new RuntimeException('Interrupted before commit.'); }
        $this->state = RecordSnapshot::copy($replacement); ++$this->writes;
        if ($this->fault === 'after') { throw new RuntimeException('Lost acknowledgement after commit.'); }
    }
}

/** All component bindings, activation history and replay receipts publish as one state. */
final class BundleLifecycleTest extends TestCase
{
    private function bundle(string $version, string $scope = 'agent'): LearningBundle
    {
        $components = [];
        foreach (['model', 'policy', 'workflow'] as $slot) {
            $components[] = ['slot' => $slot, 'kind' => $slot, 'id' => $slot, 'version' => $version,
                'artifact' => ['key' => $slot . '-' . $version, 'format' => 'fixture.v1', 'sha256' => hash('sha256', $slot . '-' . $version)], 'requires' => []];
        }
        return new LearningBundle('test', $version, $scope, $components);
    }
    private function read(array $descriptor): string { return $descriptor['artifact']['key']; }
    private function evaluate(BundleSnapshot $from, BundleSnapshot $to): array
    {
        return ['accepted' => true, 'evidence' => ['held_out' => ['baseline' => 2, 'candidate' => 4]]];
    }
    private function approve(array $request): GovernanceDecision { return GovernanceDecision::approved(); }

    public function testPublishRestoresCompatibleBindingsAndReplaysWithoutAuthorization(): void
    {
        $store = new BundleTestStore();
        $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        $receipt = $owner->activate('promote-2', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), $this->read(...));
        self::assertTrue($receipt['applied']);
        $restored = new BundleLifecycle($store, 'agent');
        self::assertSame(1, $restored->revision());
        self::assertSame('model-2', $restored->active($this->read(...))->artifact('model'));
        $never = static fn () => throw new RuntimeException('Replay invoked user code.');
        self::assertSame($receipt, $restored->activate('promote-2', 0, $this->bundle('2'), $never, $never, $never));
        self::assertSame(2, $store->writes);
        $rollback = $restored->rollback('rollback', 1, $this->approve(...), $this->read(...));
        self::assertTrue($rollback['applied']);
        self::assertSame('model-1', (new BundleLifecycle($store, 'agent'))->active($this->read(...))->artifact('model'));
        self::assertSame(2, $restored->revision());
        self::assertCount(2, $restored->receipts());
    }

    public function testFailedEvaluationAndDeniedAuthorizationDoNotChangeActiveBundle(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        $never = static fn () => throw new RuntimeException('Rejected evaluation requested authority.');
        $receipt = $owner->activate('bad', 0, $this->bundle('2'), static fn () => ['accepted' => false, 'evidence' => ['reason' => 'regression']], $never, $this->read(...));
        self::assertSame('evaluation_rejected', $receipt['reason']);
        $denied = $owner->activate('denied', 0, $this->bundle('3'), $this->evaluate(...), static fn () => GovernanceDecision::denied(), $this->read(...));
        self::assertSame('governance_not_approved', $denied['reason']);
        self::assertSame(0, $owner->revision());
        self::assertSame('workflow-1', $owner->active($this->read(...))->artifact('workflow'));
    }

    public function testStaleRevisionAndChangedRequestCannotPublish(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        $owner->activate('two', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), $this->read(...));
        foreach ([['three', 0], ['two', 1]] as [$id, $revision]) {
            try { $owner->activate($id, $revision, $this->bundle('3'), $this->evaluate(...), $this->approve(...), $this->read(...)); self::fail('Conflicting transition accepted.'); }
            catch (LogicException) { self::assertSame(2, $store->writes); }
        }
    }

    public function testLostAcknowledgementRequiresRecoveryWithoutRepeatingActivation(): void
    {
        foreach (['before', 'after'] as $fault) {
            $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
            $owner->initialize($this->bundle('1'), $this->read(...));
            $store->fault = $fault;
            try { $owner->activate('two', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), $this->read(...)); self::fail('Fault not raised.'); }
            catch (RuntimeException) { self::addToAssertionCount(1); }
            try { $owner->active($this->read(...)); self::fail('Uncertain owner remained usable.'); }
            catch (LogicException) { self::addToAssertionCount(1); }
            $store->fault = '';
            $recovered = $owner->recover($this->read(...));
            self::assertSame($fault === 'after' ? 'committed' : 'not_committed', $recovered['status']);
            self::assertSame($fault === 'after' ? 1 : 0, $owner->revision());
        }
    }

    public function testConcurrentConflictingRequestCannotBeRecoveredAsOwnCommit(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        $other = new BundleLifecycle($store, 'agent');
        $authorize = function () use ($other): GovernanceDecision {
            $other->activate('same-id', 0, $this->bundle('3'), $this->evaluate(...), $this->approve(...), $this->read(...));
            return GovernanceDecision::approved();
        };
        try { $owner->activate('same-id', 0, $this->bundle('2'), $this->evaluate(...), $authorize, $this->read(...)); self::fail('Stale store write succeeded.'); }
        catch (LogicException) { self::assertSame('model-3', $other->active($this->read(...))->artifact('model')); }
        $this->expectException(LogicException::class);
        $owner->recover($this->read(...));
    }

    public function testCorruptArtifactsWrongScopeAndReentrancyCannotPublish(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        try { $owner->activate('foreign', 0, $this->bundle('2', 'other'), $this->evaluate(...), $this->approve(...), $this->read(...)); self::fail('Wrong scope accepted.'); }
        catch (InvalidArgumentException) { self::assertSame(1, $store->writes); }
        try { $owner->activate('corrupt', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), static fn () => 'changed'); self::fail('Changed artifact accepted.'); }
        catch (InvalidArgumentException) { self::assertSame(1, $store->writes); }
        $evaluate = static function () use ($owner): array { $owner->revision(); return []; };
        try { $owner->activate('nested', 0, $this->bundle('2'), $evaluate, $this->approve(...), $this->read(...)); self::fail('Reentrant owner accepted.'); }
        catch (LogicException) { self::assertSame(1, $store->writes); }
        self::assertSame(0, $owner->revision());
    }

    public function testRestorationReconstructsHistoryRatherThanTrustingImportedRevision(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        $owner->activate('two', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), $this->read(...));
        $store->state['revision'] = 0;
        $this->expectException(InvalidArgumentException::class);
        (new BundleLifecycle($store, 'agent'))->active($this->read(...));
    }

    /** Initialization has the same uncertain-write boundary as later promotions. */
    public function testInitializationRecoveryDistinguishesPublicationFromAcknowledgement(): void
    {
        foreach (['before', 'after'] as $fault) {
            $store = new BundleTestStore(); $store->fault = $fault;
            $owner = new BundleLifecycle($store, 'agent');
            try { $owner->initialize($this->bundle('1'), $this->read(...)); self::fail('Fault not raised.'); }
            catch (RuntimeException) { self::addToAssertionCount(1); }
            $store->fault = '';
            self::assertSame($fault === 'after' ? 'committed' : 'not_committed', $owner->recover($this->read(...))['status']);
            if ($fault === 'before') { $owner->initialize($this->bundle('1'), $this->read(...)); }
            self::assertSame(0, $owner->revision());
            self::assertSame(1, $store->writes);
        }
    }

    /** Missing rollback bytes must not publish a manifest that cannot be executed. */
    public function testUnavailableRollbackArtifactPreservesCurrentBundleAndHistory(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent');
        $owner->initialize($this->bundle('1'), $this->read(...));
        $owner->activate('two', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), $this->read(...));
        try { $owner->rollback('back', 1, $this->approve(...), static fn () => throw new RuntimeException('Missing old bytes.')); self::fail('Unavailable rollback succeeded.'); }
        catch (RuntimeException) { self::assertSame(2, $store->writes); }
        self::assertSame('model-2', $owner->active($this->read(...))->artifact('model'));
        self::assertCount(1, $owner->receipts());
    }

    /** Capacity stops new transitions without sacrificing replay of retained decisions. */
    public function testRetentionCapacityPreservesReplayAndRejectsNewRequests(): void
    {
        $store = new BundleTestStore(); $owner = new BundleLifecycle($store, 'agent', 1);
        $owner->initialize($this->bundle('1'), $this->read(...));
        $receipt = $owner->activate('two', 0, $this->bundle('2'), $this->evaluate(...), $this->approve(...), $this->read(...));
        $never = static fn () => throw new RuntimeException('Replay invoked user code.');
        self::assertSame($receipt, $owner->activate('two', 0, $this->bundle('2'), $never, $never, $never));
        try { $owner->rollback('back', 1, $this->approve(...), $this->read(...)); self::fail('Capacity ignored.'); }
        catch (LogicException) { self::assertSame(2, $store->writes); }
        self::assertSame(1, $owner->revision());
    }
}
