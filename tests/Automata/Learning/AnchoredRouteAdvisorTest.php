<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Context;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Learning\{AnchoredRouteAdvisor, Experience, Outcome};
use BlueFission\Automata\Strategy\Routing\{StrategyRouteRequest, StrategyRouteResult};
use BlueFission\Automata\Strategy\Routing\{IStrategyRouteAdapter, StrategyAdapterResult, StrategyDefinition, StrategyEligibility, StrategyRouter, StrategyUsage};
use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

/** Reinforcement changes advisory preference, not execution status or authority. */
final class AnchoredRouteAdvisorTest extends TestCase
{
    private const DEFINITIONS = [
        ['id' => 'inspect.text', 'version' => '1'],
        ['id' => 'inspect.media', 'version' => '1'],
    ];

    /** Fixture policy values are deliberately separate from the library defaults. */
    private function advisor(): AnchoredRouteAdvisor
    {
        return new AnchoredRouteAdvisor([
            ['id' => 'inspect.text', 'version' => '1', 'weight' => 0.6],
            ['id' => 'inspect.media', 'version' => '1', 'weight' => 0.5],
        ], policyId: 'fixture', policyVersion: '2', rewardScale: 10.0,
            priorWeight: 2.0, maximumAdjustment: 0.2, minimumSamples: 2);
    }

    /** Outcome.successful is intentionally independent from the supplied reward. */
    private function experience(string $id, bool $successful = true, string $context = 'compound.v1'): Experience
    {
        return Experience::fromStatements($id, [], new Context(['kind' => 'compound']),
            ['timestamp' => '2026-10-20T00:00:00Z', 'trace_id' => 'trace-' . $id])
            ->withOutcome(new Outcome('review', $id, 'fixture-reviewer', $successful, [], [
                'strategy_id' => 'inspect.text', 'strategy_version' => '1', 'context_key' => $context,
            ]));
    }

    /** Query real advisor output for one exact request context. */
    private function ranking(AnchoredRouteAdvisor $advisor, string $context = 'compound.v1'): array
    {
        return $advisor->advise(new StrategyRouteRequest(['context_key' => $context]),
            self::DEFINITIONS)->ranking('inspect.text', '1');
    }

    public function testNeutralAndAbsentDoNotBecomeNegativeOrPositiveExecutionRewards(): void
    {
        $advisor = $this->advisor();
        $advisor->learn($this->experience('neutral', false), 'review', new FeedbackSignal(0.0));
        $advisor->learn($this->experience('unrated', true), 'review', null);
        $rank = $this->ranking($advisor);
        $this->assertSame(0.6, $rank['score']);
        $this->assertSame(0, $rank['metrics']['directional_samples']);
        $this->assertSame(1, $rank['metrics']['neutral_samples']);
        $this->assertSame(1, $rank['metrics']['unrated_samples']);
        $this->assertNull($rank['confidence']);
        $this->assertSame(['neutral', 'absent'], array_column($advisor->receipts(), 'evaluation'));
    }

    public function testSparseEvidenceKeepsAnchorThenNegativeFeedbackLowersSuccessfulRoute(): void
    {
        $advisor = $this->advisor();
        $advisor->learn($this->experience('one'), 'review', FeedbackSignal::negative(10));
        $this->assertSame(0.6, $this->ranking($advisor)['score']);
        $advisor->learn($this->experience('two'), 'review', FeedbackSignal::negative(10));
        $this->assertEqualsWithDelta(0.5, $this->ranking($advisor)['score'], 0.0000001);
        $this->assertSame(0.6, $this->ranking($advisor)['anchor']);
    }

    public function testFeedbackStaysWithinItsContextAndExactModelVersion(): void
    {
        $advisor = $this->advisor();
        foreach (['a', 'b', 'c'] as $id) {
            $advisor->learn($this->experience($id, false), 'review', FeedbackSignal::positive(10));
        }
        $this->assertGreaterThan(0.6, $this->ranking($advisor)['score']);
        $this->assertSame(0.6, $this->ranking($advisor, 'unseen.video')['score']);
        $this->assertSame(0.6, $this->ranking($advisor, 'global')['score']);
        $advice = $advisor->advise(new StrategyRouteRequest(['context_key' => 'compound.v1']),
            [['id' => 'inspect.text', 'version' => '2']]);
        $this->assertSame(0, $advice->ranking('inspect.text', '2')['metrics']['directional_samples']);
        $this->assertCount(1, $advice->rankings);
    }

    public function testRepeatedAndConflictingEvidenceCannotDoubleLearn(): void
    {
        $advisor = $this->advisor();
        $experience = $this->experience('replayed');
        $this->assertTrue($advisor->learn($experience, 'review', FeedbackSignal::positive(4)));
        $this->assertFalse($advisor->learn(new Experience($experience->toArray()),
            'review', FeedbackSignal::positive(4)));
        $before = $advisor->receipts();
        try {
            $advisor->learn($experience, 'review', FeedbackSignal::negative(4));
            $this->fail('Conflicting feedback must not reuse the same outcome identity.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $advisor->receipts());
            $this->assertSame(1, $this->ranking($advisor)['metrics']['directional_samples']);
        }
    }

    public function testNeutralFollowupsDoNotDiluteOrStrengthenAnExistingPreference(): void
    {
        $advisor = $this->advisor();
        foreach (['a', 'b'] as $id) {
            $advisor->learn($this->experience($id), 'review', FeedbackSignal::positive(10));
        }
        $before = $this->ranking($advisor)['score'];
        foreach (['c', 'd', 'e'] as $id) {
            $advisor->learn($this->experience($id), 'review', new FeedbackSignal(0));
        }
        $this->assertSame($before, $this->ranking($advisor)['score']);
    }

    public function testConflictingDirectionsCancelAndRepeatedSupportCannotEraseAnchor(): void
    {
        $advisor = $this->advisor();
        $advisor->learn($this->experience('good'), 'review', FeedbackSignal::positive(10));
        $advisor->learn($this->experience('bad'), 'review', FeedbackSignal::negative(10));
        $this->assertSame(0.6, $this->ranking($advisor)['score']);
        for ($i = 0; $i < 50; ++$i) {
            $advisor->learn($this->experience('support-' . $i), 'review', FeedbackSignal::positive(10));
        }
        $this->assertGreaterThan(0.6, $this->ranking($advisor)['score']);
        $this->assertLessThanOrEqual(0.8, $this->ranking($advisor)['score']);
    }

    public function testTransportCompletionDoesNotProvideAnUnobservedReward(): void
    {
        $advisor = $this->advisor();
        $advisor->observe(new StrategyRouteRequest(['context_key' => 'compound.v1']),
            new StrategyRouteResult(['status' => 'completed']));
        $this->assertSame([], $advisor->receipts());
        $this->assertSame(0.6, $this->ranking($advisor)['score']);
    }

    public function testBadRewardsAndChangedSourceAreRejectedWithoutMutation(): void
    {
        $advisor = $this->advisor();
        foreach ([INF, NAN, -11.0, 11.0] as $value) {
            try {
                $advisor->learn($this->experience('bad'), 'review', new FeedbackSignal($value));
                $this->fail('Invalid reward must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertSame([], $advisor->receipts());
            }
        }
        $experience = $this->experience('changed');
        $advisor->learn($experience, 'review', null);
        $changed = $experience->toArray();
        $changed['trace_id'] = 'different-source';
        $this->expectException(InvalidArgumentException::class);
        $advisor->learn(new Experience($changed), 'review', null);
    }

    public function testInvalidConfigurationAndAmbiguousIdentitiesAreRejected(): void
    {
        $cases = [
            ['rewardScale' => 0.0], ['rewardScale' => INF], ['priorWeight' => 0.0],
            ['priorWeight' => NAN], ['maximumAdjustment' => 1.1], ['maximumAdjustment' => -0.1],
            ['minimumSamples' => 0], ['maximumFeedback' => 0], ['defaultAnchor' => 1.1],
            ['policyId' => ' '], ['policyVersion' => ''],
            ['anchors' => [['id' => 'a@b', 'version' => '1', 'weight' => 0.5]]],
            ['anchors' => [['id' => 'a', 'version' => '1', 'weight' => '0.5']]],
            ['anchors' => [['id' => 'a', 'version' => '1', 'weight' => 0.5],
                ['id' => 'a', 'version' => '1', 'weight' => 0.6]]],
        ];
        foreach ($cases as $configuration) {
            try {
                new AnchoredRouteAdvisor(...['anchors' => [], ...$configuration]);
                $this->fail('Invalid configuration must be rejected.');
            } catch (InvalidArgumentException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testCapacityRejectsNewEvidenceButKeepsReplayProtection(): void
    {
        $advisor = new AnchoredRouteAdvisor([], maximumFeedback: 1);
        $first = $this->experience('first');
        $advisor->learn($first, 'review', FeedbackSignal::positive(1));
        $before = $advisor->receipts();
        try {
            $advisor->learn($this->experience('second'), 'review', null);
            $this->fail('Capacity must not silently evict learned identities.');
        } catch (OverflowException) {
            $this->assertSame($before, $advisor->receipts());
            $this->assertFalse($advisor->learn($first, 'review', FeedbackSignal::positive(1)));
        }
    }

    public function testLaterAssessmentsAppendWithoutRewritingAnEarlierNeutralOutcome(): void
    {
        $advisor = $this->advisor();
        $first = $this->experience('same-session');
        $advisor->learn($first, 'review', new FeedbackSignal(0));
        $later = $first->withOutcome(new Outcome('followup', 'same-session', 'human-reviewer', true, [], [
            'strategy_id' => 'inspect.text', 'strategy_version' => '1', 'context_key' => 'compound.v1',
        ]));
        $this->assertTrue($advisor->learn($later, 'followup', FeedbackSignal::positive(5)));
        $this->assertFalse($advisor->learn($later, 'review', new FeedbackSignal(0)));
        $this->assertSame(1, $this->ranking($advisor)['metrics']['neutral_samples']);
        $this->assertSame(1, $this->ranking($advisor)['metrics']['directional_samples']);
        $before = $advisor->receipts();
        $changed = $later->toArray();
        $changed['trace_id'] = 'different';
        try {
            $advisor->learn(new Experience($changed), 'followup', FeedbackSignal::positive(5));
            $this->fail('Appending outcomes cannot permit altered source identity.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $advisor->receipts());
        }
    }

    public function testMissingOutcomeAndMalformedAttributionCannotMutateEvidence(): void
    {
        $advisor = $this->advisor();
        $bad = Experience::fromStatements('bad', [], new Context())
            ->withOutcome(new Outcome('review', 'bad', 'reviewer', true));
        foreach ([[$this->experience('missing'), 'unknown'], [$bad, 'review']] as [$experience, $outcome]) {
            try {
                $advisor->learn($experience, $outcome, FeedbackSignal::positive(1));
                $this->fail('Unattributable feedback must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertSame([], $advisor->receipts());
            }
        }
    }

    public function testRealRouterChangesItsChoiceButPreservesDenialAndBudget(): void
    {
        $advisor = $this->advisor();
        $calls = 0;
        $adapters = [];
        foreach (self::DEFINITIONS as $identity) {
            $adapter = $this->createStub(IStrategyRouteAdapter::class);
            $adapter->method('definition')->willReturn(new StrategyDefinition([
                ...$identity, 'capability_id' => 'inspect', 'capability_version' => '1',
                'mode' => 'deterministic', 'side_effect_free' => true, 'availability' => 'available',
            ]));
            $adapter->method('eligibility')->willReturn(new StrategyEligibility(['eligible' => true]));
            $adapter->method('estimate')->willReturn(new StrategyUsage(['invocations' => 1]));
            $adapter->method('execute')->willReturnCallback(static function () use (&$calls, $identity): StrategyAdapterResult {
                ++$calls;
                return new StrategyAdapterResult(['status' => 'completed', 'output' => $identity['id'],
                    'usage' => ['invocations' => 1]]);
            });
            $adapters[] = $adapter;
        }
        $router = new StrategyRouter($adapters, $advisor);
        $request = static fn (array $override = []): StrategyRouteRequest => new StrategyRouteRequest([
            'id' => 'request', 'subject_id' => 'agent', 'capability_id' => 'inspect', 'capability_version' => '1',
            'candidates' => self::DEFINITIONS, 'context_key' => 'compound.v1',
            'selection_policy' => 'adaptive', ...$override,
        ]);
        $authority = static fn (bool $allowed): AutonomyDecision => new AutonomyDecision([
            'allowed' => $allowed, 'subject_id' => 'agent', 'capability_id' => 'inspect', 'capability_version' => '1',
        ]);
        $this->assertSame('inspect.text', $router->route($request(), $authority(true))->output);
        foreach (['a', 'b', 'c'] as $id) {
            $advisor->learn($this->experience($id), 'review', FeedbackSignal::negative(10));
        }
        $this->assertSame('inspect.media', $router->route($request(), $authority(true))->output);
        $this->assertSame('inspect.text', $router->route($request(['context_key' => 'unseen']), $authority(true))->output);
        $this->assertSame(StrategyRouter::CODE_AUTHORIZATION_DENIED,
            $router->route($request(), $authority(false))->code);
        $this->assertFalse($router->route($request(['limits' => ['max_invocations' => 0]]), $authority(true))->completed());
        $this->assertSame(3, $calls);
        $this->assertCount(3, $advisor->receipts(), 'Router completion must not manufacture reinforcement.');
    }

    public function testCheckpointRebuildsLearningAndReplayIdentityWithoutImportingTrustedScores(): void
    {
        $advisor = $this->advisor();
        $first = $this->experience('a');
        foreach ([$first, $this->experience('b'), $this->experience('c')] as $experience) {
            $advisor->learn($experience, 'review', FeedbackSignal::negative(10));
        }
        // Real wire round-trip, preserving float types used by evidence fingerprints.
        $snapshot = json_decode(json_encode($advisor->checkpoint(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
        $restored = $this->advisor()->restore($snapshot);
        $this->assertSame($advisor->receipts(), $restored->receipts());
        $this->assertSame($this->ranking($advisor), $this->ranking($restored));
        $this->assertFalse($restored->learn($first, 'review', FeedbackSignal::negative(10)));
        $this->assertSame(0.6, $this->ranking($restored, 'unseen')['score']);
        $restored->learn($this->experience('d'), 'review', FeedbackSignal::positive(10));
        $this->assertCount(3, $advisor->receipts(), 'Restoration creates an isolated candidate.');
    }

    public function testWrongPolicyAndCorruptCheckpointLeaveActiveLearnerUntouched(): void
    {
        $advisor = $this->advisor();
        $advisor->learn($this->experience('a'), 'review', FeedbackSignal::positive(10));
        $checkpoint = $advisor->checkpoint();
        $before = $advisor->receipts();
        $mutations = [
            static function (array &$data): void { $data['schema_version'] = 99; },
            static function (array &$data): void { $data['policy']['reward_scale'] = 100.0; },
            static function (array &$data): void { $data['evidence'][0]['reward'] = 11.0; },
            static function (array &$data): void { $data['evidence'][] = $data['evidence'][0]; },
            static function (array &$data): void { unset($data['evidence'][0]['reward']); },
            static function (array &$data): void { $data['evidence'][0]['experience']['trace_id'] = 'tampered'; },
        ];
        foreach ($mutations as $index => $mutate) {
            $changed = $checkpoint;
            $mutate($changed);
            if ($index < 5) {
                // Exercise semantic validation even when a writer recomputes the
                // non-authenticating integrity hash over an invalid payload.
                unset($changed['fingerprint']);
                $changed['fingerprint'] = \BlueFission\Automata\Support\RecordSnapshot::fingerprint($changed);
            }
            try {
                $advisor->restore($changed);
                $this->fail('Invalid checkpoint must be rejected before activation.');
            } catch (InvalidArgumentException) {
                $this->assertSame($before, $advisor->receipts());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        (new AnchoredRouteAdvisor([], policyId: 'another-scope'))->restore($checkpoint);
    }
}
