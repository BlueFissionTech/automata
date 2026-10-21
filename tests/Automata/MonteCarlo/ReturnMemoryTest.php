<?php

namespace BlueFission\Tests\Automata\MonteCarlo;

use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{Experience, Outcome};
use BlueFission\Automata\MonteCarlo\{ReturnMemory, Search};
use BlueFission\Automata\Support\RecordSnapshot;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

/** Retained empirical returns must remain attributable and separate from rollouts. */
final class ReturnMemoryTest extends TestCase
{
    private function experience(string $id, mixed $action = 'fast', string $origin = 'observed', string $context = 'request:v1', string $outcome = 'review'): Experience
    {
        return Experience::fromStatements($id, [], new Context(['request' => $id]), ['timestamp' => '2026-10-20T00:00:00Z'])
            ->withOutcome(new Outcome($outcome, $id, 'independent-evaluator', true, ['completed' => true],
                ['action' => $action, 'context_key' => $context, 'origin' => $origin]));
    }

    private function searchResult(): \BlueFission\Automata\MonteCarlo\SearchResult
    {
        return (new Search(8, 42))->evaluate(['fast', 'careful'], static fn ($action) => $action === 'fast' ? 0.55 : 0.5);
    }

    public function testRetainedReturnsChangeRankingWithoutChangingRawRolloutsOrExecutionStatus(): void
    {
        $memory = new ReturnMemory();
        $result = $this->searchResult();
        $raw = $result->toArray();
        self::assertSame('fast', $memory->rank($result, 'request:v1')[0]['action']);
        for ($i = 0; $i < 3; ++$i) { $memory->learn($this->experience('e' . $i), 'review', FeedbackSignal::negative()); }
        $ranked = $memory->rank($result, 'request:v1');
        self::assertSame('careful', $ranked[0]['action']);
        self::assertSame($raw, $result->toArray());
        self::assertSame(3, $ranked[1]['retained']['rated_samples']);
        self::assertSame(-1.0, $ranked[1]['retained']['mean_reward']);
        self::assertLessThanOrEqual(0.25, abs($ranked[1]['adjustment']));
        self::assertTrue($memory->receipts()[0]['execution_successful']);
        self::assertSame('negative', $memory->receipts()[0]['evaluation']);
    }

    public function testNeutralAndAbsentReturnsAreDistinctAndSimulationCannotBecomeObservation(): void
    {
        $memory = new ReturnMemory(minimumSamples: 1);
        $memory->learn($this->experience('zero'), 'review', new FeedbackSignal(0));
        $memory->learn($this->experience('none'), 'review', null);
        for ($i = 0; $i < 3; ++$i) {
            $memory->learn($this->experience('sim' . $i, origin: 'simulated'), 'review', FeedbackSignal::negative());
        }
        $observed = $memory->statistics('request:v1', 'fast');
        self::assertSame(1, $observed['rated_samples']);
        self::assertSame(1, $observed['neutral_samples']);
        self::assertSame(1, $observed['unrated_samples']);
        self::assertSame(0.0, $observed['mean_reward']);
        self::assertSame('fast', $memory->rank($this->searchResult(), 'request:v1')[0]['action']);
        self::assertSame('careful', $memory->rank($this->searchResult(), 'request:v1', 'simulated')[0]['action']);
        self::assertSame(-1.0, $memory->statistics('request:v1', 'fast', 'simulated')['mean_reward']);
        self::assertNull($memory->statistics('unseen', 'fast')['mean_reward']);
    }

    public function testSparseEvidenceAndUnseenContextOrActionVersionKeepAnchor(): void
    {
        $memory = new ReturnMemory();
        $memory->learn($this->experience('one'), 'review', FeedbackSignal::negative());
        self::assertSame('fast', $memory->rank($this->searchResult(), 'request:v1')[0]['action']);
        for ($i = 2; $i < 5; ++$i) { $memory->learn($this->experience('e' . $i), 'review', FeedbackSignal::negative()); }
        self::assertSame('fast', $memory->rank($this->searchResult(), 'unseen')[0]['action']);
        $memory->learn($this->experience('version', ['id' => 'fast', 'version' => '1']), 'review', FeedbackSignal::positive());
        self::assertSame(0, $memory->statistics('request:v1', ['id' => 'fast', 'version' => '2'])['rated_samples']);
        $memory->learn($this->experience('integer', 1), 'review', FeedbackSignal::positive());
        self::assertSame(0, $memory->statistics('request:v1', '1')['rated_samples']);
    }

    public function testReplayConflictAndDelayedFeedbackPreserveExactLineage(): void
    {
        $memory = new ReturnMemory();
        $first = $this->experience('session');
        self::assertTrue($memory->learn($first, 'review', null));
        self::assertFalse($memory->learn($first, 'review', null));
        try { $memory->learn($first, 'review', FeedbackSignal::positive()); self::fail('Rewritten outcome accepted.'); }
        catch (InvalidArgumentException) { self::assertCount(1, $memory->receipts()); }
        $later = $first->withOutcome(new Outcome('later', 'session', 'human', false, [],
            ['action' => 'fast', 'context_key' => 'request:v1', 'origin' => 'observed']));
        self::assertTrue($memory->learn($later, 'later', FeedbackSignal::negative()));
        self::assertSame(1, $memory->statistics('request:v1', 'fast')['unrated_samples']);
        self::assertSame(1, $memory->statistics('request:v1', 'fast')['negative_samples']);
    }

    public function testCheckpointRestoresEvidenceAndPolicyWithoutTrustingImportedStatistics(): void
    {
        $memory = new ReturnMemory();
        for ($i = 0; $i < 3; ++$i) { $memory->learn($this->experience('e' . $i), 'review', FeedbackSignal::negative()); }
        $checkpoint = json_decode(json_encode($memory->checkpoint(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
        $restored = (new ReturnMemory())->restore($checkpoint);
        self::assertSame($memory->receipts(), $restored->receipts());
        self::assertSame($memory->rank($this->searchResult(), 'request:v1'), $restored->rank($this->searchResult(), 'request:v1'));
        self::assertFalse($restored->learn($this->experience('e0'), 'review', FeedbackSignal::negative()));
        $checkpoint['evidence'][0]['reward'] = 1.0;
        try { $memory->restore($checkpoint); self::fail('Corrupt evidence accepted.'); }
        catch (InvalidArgumentException) { self::assertCount(3, $memory->receipts()); }
        $this->expectException(InvalidArgumentException::class);
        (new ReturnMemory(policyVersion: '2'))->restore($memory->checkpoint());
    }

    public function testCapacityDoesNotEvictReplayProtectionAndInvalidEvidenceDoesNotWrite(): void
    {
        $memory = new ReturnMemory(maximumObservations: 1);
        foreach (['invalid-origin', 'observed'] as $origin) {
            try { $memory->learn($this->experience('bad', origin: $origin), 'review', new FeedbackSignal(INF)); self::fail('Invalid return accepted.'); }
            catch (InvalidArgumentException) { self::assertSame([], $memory->receipts()); }
        }
        $memory->learn($this->experience('one'), 'review', FeedbackSignal::positive());
        try { $memory->learn($this->experience('two'), 'review', FeedbackSignal::positive()); self::fail('Capacity exceeded.'); }
        catch (OverflowException) { self::assertCount(1, $memory->receipts()); }
        self::assertFalse($memory->learn($this->experience('one'), 'review', FeedbackSignal::positive()));
    }

    public function testUnvisitedActionsHaveNoInventedSimulationAnchor(): void
    {
        $result = (new Search(1))->evaluate(['fast', 'careful'], static fn () => -0.5);
        $ranked = (new ReturnMemory())->rank($result, 'request:v1');
        self::assertSame('fast', $ranked[0]['action']);
        self::assertNull($ranked[1]['score']);
        self::assertNull($ranked[1]['anchor']);
    }

    public function testSearchPreservesScalarActionTypesInsteadOfMergingStatistics(): void
    {
        $result = (new Search(4))->evaluate([1, '1', true, null], static fn ($action) => $action === '1' ? 1.0 : 0.0);
        self::assertCount(4, $result->getStatistics());
        self::assertSame('1', $result->getBestAction());
        self::assertSame([1, 1, 1, 1], Arr::make($result->toArray())->map(static fn ($row) => $row['visits'])->val());
    }

    public function testContradictoryReturnsShrinkToAnchorAndAdjustmentCannotGrowWithoutBound(): void
    {
        $memory = new ReturnMemory(minimumSamples: 1, maximumAdjustment: 0.1);
        for ($i = 0; $i < 10; ++$i) { $memory->learn($this->experience('negative' . $i), 'review', FeedbackSignal::negative()); }
        $rows = $memory->rank($this->searchResult(), 'request:v1');
        $fast = Arr::make($rows)->filter(static fn ($row) => $row['action'] === 'fast')->values()->val()[0];
        self::assertLessThanOrEqual(0.1, abs($fast['adjustment']));
        for ($i = 0; $i < 10; ++$i) { $memory->learn($this->experience('positive' . $i), 'review', FeedbackSignal::positive()); }
        self::assertSame(0.0, $memory->rank($this->searchResult(), 'request:v1')[0]['adjustment']);
        self::assertSame(0.55, $memory->rank($this->searchResult(), 'request:v1')[0]['score']);
    }

    public function testRecomputedChecksumCannotHideDuplicateEvidenceOrChangedSource(): void
    {
        $memory = new ReturnMemory();
        $memory->learn($this->experience('one'), 'review', FeedbackSignal::negative());
        $checkpoint = $memory->checkpoint();
        $checkpoint['evidence'][] = $checkpoint['evidence'][0];
        unset($checkpoint['fingerprint']);
        $checkpoint['fingerprint'] = RecordSnapshot::fingerprint($checkpoint);
        try { $memory->restore($checkpoint); self::fail('Duplicate checkpoint evidence accepted.'); }
        catch (InvalidArgumentException) { self::assertCount(1, $memory->receipts()); }
        $changed = $this->experience('one', outcome: 'later')->toArray();
        $changed['context']['data']['request'] = 'different';
        try { $memory->learn(new Experience($changed), 'later', FeedbackSignal::positive()); self::fail('Changed source accepted.'); }
        catch (InvalidArgumentException) { self::assertCount(1, $memory->receipts()); }
    }

    public function testRolloutUnitsAndExtremeFiniteReturnsAreValidated(): void
    {
        $memory = new ReturnMemory(rewardScale: 1.0e308, maximumAdjustment: 1.0e307, minimumSamples: 1);
        $memory->learn($this->experience('extreme'), 'review', new FeedbackSignal(1.0e308));
        $result = (new Search(1))->evaluate(['fast'], static fn () => 1.0e308);
        self::assertSame(1.0e308, $memory->rank($result, 'request:v1')[0]['score']);
        self::assertSame(1.0e308, $memory->statistics('request:v1', 'fast')['mean_reward']);
        $this->expectException(InvalidArgumentException::class);
        (new ReturnMemory())->rank($result, 'request:v1');
    }
}
