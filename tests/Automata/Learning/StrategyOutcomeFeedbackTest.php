<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Context;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\Learning\Experience;
use BlueFission\Automata\Learning\Outcome;
use BlueFission\Automata\Learning\StrategyOutcomeFeedback;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StrategyOutcomeFeedbackTest extends TestCase
{
    private function episode(array $metrics = [], array $attribution = [], bool $successful = true,
        string $experienceId = 'episode', string $outcomeId = 'review'): Experience
    {
        return Experience::fromStatements($experienceId, [], new Context(), ['trace_id' => 'trace-1'])
            ->withOutcome(new Outcome($outcomeId, $experienceId, 'fixture-reviewer', $successful,
                ['feedback' => $metrics], [...['strategy_id' => 'intent', 'strategy_version' => '2',
                    'context_key' => 'concierge'], ...$attribution]));
    }

    public function testExactOutcomeUpdatesOnlyItsVersionAndRetainsReceipts(): void
    {
        $learner = new Intelligence();
        $bridge = new StrategyOutcomeFeedback($learner);
        $episode = $this->episode(['prediction_accuracy' => 1.0, 'cost' => null]);
        $this->assertTrue($bridge->apply($episode, 'review'));
        $this->assertSame(1, $learner->strategyPerformance('intent', '2', 'concierge')['feedback_samples']);
        $this->assertSame(0, $learner->strategyPerformance('intent', '1', 'concierge')['feedback_samples']);
        $this->assertSame(0, $learner->strategyPerformance('intent', '2', 'other')['feedback_samples']);
        // Preserve Intelligence's existing global aggregation of contextual evidence.
        $this->assertSame(1, $learner->strategyPerformance('intent', '2')['feedback_samples']);
        $receipt = $bridge->receipts()[0];
        $this->assertSame('episode', $receipt['experience_id']);
        $this->assertSame('review', $receipt['outcome_id']);
        $this->assertSame('trace-1', $receipt['trace_id']);
        $this->assertSame('applied', $receipt['status']);
        $this->assertArrayNotHasKey('cost', $receipt['feedback']);
        $this->assertArrayNotHasKey('energy', $receipt['feedback']);
        $this->assertSame(['successful' => true, 'prediction_accuracy' => 1.0], $receipt['feedback']);
        $this->assertSame($bridge->receipts(), json_decode(json_encode($bridge->receipts(),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testIdenticalReplayAndUnrelatedNewOutcomeDoNotDoubleCount(): void
    {
        $learner = new Intelligence();
        $bridge = new StrategyOutcomeFeedback($learner);
        $episode = $this->episode();
        $this->assertTrue($bridge->apply($episode, 'review'));
        $this->assertFalse($bridge->apply(new Experience($episode->toArray()), 'review'));
        $grown = $episode->withOutcome(new Outcome('unrelated', 'episode', 'reviewer', false));
        $this->assertFalse($bridge->apply($grown, 'review'));
        $this->assertSame(1, $learner->strategyPerformance('intent', '2')['feedback_samples']);
        $this->assertCount(1, $bridge->receipts());
    }

    public function testConflictingEvidenceCannotReuseAnAppliedIdentity(): void
    {
        $learner = new Intelligence();
        $bridge = new StrategyOutcomeFeedback($learner);
        $bridge->apply($this->episode(['score' => 0.4]), 'review');
        try {
            $bridge->apply($this->episode(['score' => 0.9]), 'review');
            $this->fail('Expected conflicting evidence rejection.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(1, $learner->strategyPerformance('intent', '2')['feedback_samples']);
            $this->assertSame(0.4, $learner->strategyPerformance('intent', '2')['average_score']);
        }
    }

    public function testOutcomeMustExistInItsExperience(): void
    {
        $bridge = new StrategyOutcomeFeedback(new Intelligence());
        $this->expectException(InvalidArgumentException::class);
        $bridge->apply($this->episode(), 'invented');
    }

    public function testFalseAndZeroFeedbackAreNotDropped(): void
    {
        $learner = new Intelligence();
        $bridge = new StrategyOutcomeFeedback($learner);
        $bridge->apply($this->episode(['score' => 0, 'cost' => 0.0], successful: false), 'review');
        $feedback = $bridge->receipts()[0]['feedback'];
        $this->assertSame(false, $feedback['successful']);
        $this->assertSame(0, $feedback['score']);
        $this->assertSame(0.0, $feedback['cost']);
        $this->assertSame(1, $learner->strategyPerformance('intent', '2')['failures']);
    }

    public function testMalformedAttributionIsRejectedBeforeLearning(): void
    {
        foreach ([['strategy_id' => ''], ['strategy_id' => 'ambiguous@id'], ['strategy_version' => 'v@2'],
            ['strategy_version' => 2], ['context_key' => ''], ['context_key' => []]] as $attribution) {
            $learner = new Intelligence();
            $bridge = new StrategyOutcomeFeedback($learner);
            try {
                $bridge->apply($this->episode(attribution: $attribution), 'review');
                $this->fail('Expected malformed attribution rejection.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame([], $bridge->receipts());
                $this->assertSame(0, $learner->strategyPerformance('intent', '2')['feedback_samples']);
            }
        }
    }

    public function testMalformedMetricsAndAuthorityFieldsAreRejectedBeforeLearning(): void
    {
        foreach ([['score' => '0.5'], ['score' => false], ['accuracy' => 1.1], ['confidence' => -0.1],
            ['cost' => -1], ['latency_ms' => []], ['allowed' => true], ['successful' => false],
            ['energy' => 'unknown']] as $metrics) {
            $learner = new Intelligence();
            $bridge = new StrategyOutcomeFeedback($learner);
            try {
                $bridge->apply($this->episode($metrics), 'review');
                $this->fail('Expected invalid metric rejection.');
            } catch (InvalidArgumentException $error) {
                $this->assertSame([], $bridge->receipts());
                $this->assertSame(0, $learner->strategyPerformance('intent', '2')['feedback_samples']);
            }
        }
    }

    public function testEmptyFeedbackObjectStillRecordsObservedSuccess(): void
    {
        $bridge = new StrategyOutcomeFeedback(new Intelligence());
        $episode = Experience::fromStatements('episode', [], new Context())->withOutcome(
            new Outcome('review', 'episode', 'reviewer', true, [], [
                'strategy_id' => 'intent', 'strategy_version' => '2', 'context_key' => 'global']));
        $this->assertTrue($bridge->apply($episode, 'review'));
        $this->assertSame(['successful' => true], $bridge->receipts()[0]['feedback']);
    }

    public function testMalformedFeedbackContainerIsRejected(): void
    {
        $episode = Experience::fromStatements('episode', [], new Context())->withOutcome(
            new Outcome('review', 'episode', 'reviewer', true, ['feedback' => 'bad'], [
                'strategy_id' => 'intent', 'strategy_version' => '2', 'context_key' => 'global']));
        $this->expectException(InvalidArgumentException::class);
        (new StrategyOutcomeFeedback(new Intelligence()))->apply($episode, 'review');
    }

    public function testUncertainPartialLearnerFailureCannotBeAutomaticallyReplayed(): void
    {
        $learner = new class extends Intelligence {
            public int $calls = 0;
            public function recordStrategyFeedback(string $strategyId, string $strategyVersion,
                array $feedback, string $contextKey = 'global'): void
            {
                ++$this->calls;
                parent::recordStrategyFeedback($strategyId, $strategyVersion, $feedback, $contextKey);
                throw new RuntimeException('Failure after partial application.');
            }
        };
        $bridge = new StrategyOutcomeFeedback($learner);
        $episode = $this->episode();
        try { $bridge->apply($episode, 'review'); $this->fail('Expected learner failure.'); }
        catch (RuntimeException $error) { $this->assertSame('uncertain', $bridge->receipts()[0]['status']); }
        try { $bridge->apply($episode, 'review'); $this->fail('Expected retry rejection.'); }
        catch (LogicException $error) {
            $this->assertSame(1, $learner->calls);
            $this->assertSame(1, $learner->strategyPerformance('intent', '2')['feedback_samples']);
        }
    }

    public function testReceiptIdentitySeparatesExperienceAndOutcomeComponents(): void
    {
        $bridge = new StrategyOutcomeFeedback(new Intelligence());
        $bridge->apply($this->episode(experienceId: 'a:b', outcomeId: 'c'), 'c');
        $bridge->apply($this->episode(experienceId: 'a', outcomeId: 'b:c'), 'b:c');
        $this->assertCount(2, $bridge->receipts());
    }
}
