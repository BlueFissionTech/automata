<?php

namespace BlueFission\Tests\Automata\Qualification;

use BlueFission\Automata\Qualification\FollowUpPlan;
use BlueFission\Automata\Qualification\NurtureSuggestion;
use BlueFission\Automata\Qualification\QualificationAudit;
use BlueFission\Automata\Qualification\QualificationCriterion;
use BlueFission\Automata\Qualification\QualificationResult;
use BlueFission\Automata\Qualification\QualificationScore;
use BlueFission\Automata\Qualification\QualificationScorer;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QualificationPrimitivesTest extends TestCase
{
    public function testScorerProducesWeightedAdvisoryDecision(): void
    {
        $scorer = new QualificationScorer([
            new QualificationCriterion(['key' => 'fit', 'weight' => 2, 'required' => true, 'minimum' => 0.5]),
            new QualificationCriterion(['key' => 'intent', 'weight' => 1]),
        ]);

        $score = $scorer->score('subject-1', [
            'fit' => ['value' => 0.9, 'confidence' => 0.8, 'evidence' => ['profile-match']],
            'intent' => ['value' => 0.6, 'confidence' => 0.9],
        ], ['trace_id' => 'trace-1']);

        $this->assertSame(QualificationScore::STATUS_QUALIFIED, $score->status());
        $this->assertEqualsWithDelta(0.8, $score->score(), 0.0001);
        $this->assertEqualsWithDelta(0.8333, $score->confidence(), 0.0001);
        $this->assertSame(['profile-match'], $score->toArray()['evidence']['fit']);
    }

    public function testMissingRequiredEvidenceRoutesToReview(): void
    {
        $score = (new QualificationScorer([
            ['key' => 'consent', 'required' => true, 'minimum' => 1.0],
            ['key' => 'fit', 'weight' => 2],
        ]))->score('subject-2', ['fit' => 0.9]);

        $this->assertSame(QualificationScore::STATUS_REVIEW, $score->status());
        $this->assertSame(['consent'], $score->unmetCriteria());
        $this->assertEqualsWithDelta(0.6667, $score->confidence(), 0.0001);
    }

    public function testNurtureSuggestionHonorsAllowedAndProhibitedConditions(): void
    {
        $suggestion = new NurtureSuggestion([
            'action' => 'offer_relevant_resource',
            'allowed_when' => ['consent' => true],
            'prohibited_when' => ['do_not_contact' => true],
        ]);

        $this->assertTrue($suggestion->isAllowed(['consent' => true, 'do_not_contact' => false]));
        $this->assertFalse($suggestion->isAllowed(['consent' => true, 'do_not_contact' => true]));
        $this->assertFalse($suggestion->isAllowed(['consent' => false]));
    }

    public function testFollowUpPlanIsBoundedAndStopsOnContext(): void
    {
        $plan = new FollowUpPlan([
            'max_steps' => 2,
            'stop_conditions' => ['converted' => true],
            'steps' => [
                ['id' => 'step-1', 'action' => 'review_new_evidence', 'not_before' => '2026-08-28T00:00:00Z'],
                ['id' => 'step-2', 'action' => 'request_human_review'],
            ],
        ]);

        $this->assertSame('step-1', $plan->next([], new DateTimeImmutable('2026-08-29T00:00:00Z'))['id']);
        $this->assertNull($plan->next(['converted' => true], new DateTimeImmutable('2026-08-29T00:00:00Z')));
    }

    public function testFollowUpPlanRejectsUnboundedSteps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FollowUpPlan(['max_steps' => 1, 'steps' => [['id' => 'a'], ['id' => 'b']]]);
    }

    public function testResultPreservesScorePlanSuggestionsAndAudit(): void
    {
        $result = new QualificationResult([
            'score' => new QualificationScore(['subject_id' => 'subject-3', 'score' => 0.5, 'status' => 'nurture']),
            'suggestions' => [new NurtureSuggestion(['id' => 'suggestion-1', 'action' => 'offer_resource'])],
            'follow_up_plan' => new FollowUpPlan(['steps' => [['id' => 'step-1', 'action' => 'review']]]),
            'audit' => new QualificationAudit(['trace_id' => 'trace-3', 'rule_version' => 'rules-1']),
        ]);
        $payload = $result->toArray();

        $this->assertSame('nurture', $payload['score']['status']);
        $this->assertSame('suggestion-1', $payload['suggestions'][0]['id']);
        $this->assertSame('step-1', $payload['follow_up_plan']['steps'][0]['id']);
        $this->assertSame('trace-3', $payload['audit']['trace_id']);
    }
}
