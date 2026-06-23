<?php

namespace BlueFission\Tests\Automata\Feedback;

use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Feedback\ReviewRecord;
use PHPUnit\Framework\TestCase;

class ReviewRecordTest extends TestCase
{
    public function testCorrectionRecordPreservesOriginalCorrectedAndEvidence(): void
    {
        $record = ReviewRecord::correction(
            ['label' => 'maybe'],
            ['label' => 'yes'],
            'reviewer-1',
            'Reviewer confirmed the label.',
            0.82,
            [
                'trace' => ['task_id' => 'task-1'],
                'evidence' => ['source' => 'fixture'],
                'policy_strategy' => 'human_review',
                'tags' => ['classification'],
                'context' => ['surface' => 'test'],
            ]
        );

        $this->assertSame(ReviewRecord::STATUS_CORRECTED, $record->status());
        $this->assertSame(['label' => 'maybe'], $record->originalValue());
        $this->assertSame(['label' => 'yes'], $record->correctedValue());
        $this->assertSame('reviewer-1', $record->actor());
        $this->assertSame('Reviewer confirmed the label.', $record->reason());
        $this->assertSame(0.82, $record->confidence());
        $this->assertTrue($record->hasCorrection());
        $this->assertSame('task-1', $record->trace()['task_id']);
        $this->assertSame('fixture', $record->evidence()['source']);
        $this->assertSame('human_review', $record->policyStrategy());
        $this->assertSame('test', $record->context()->get('surface'));
    }

    public function testTrainingSignalRecordCapturesSignalValue(): void
    {
        $record = ReviewRecord::trainingSignal('trainer', FeedbackSignal::negative(0.4));

        $this->assertSame(ReviewRecord::STATUS_TRAINING_SIGNAL, $record->status());
        $this->assertSame('trainer', $record->actor());
        $this->assertSame(0.4, $record->confidence());
        $this->assertSame(-0.4, $record->evidence()['signal_value']);
    }

    public function testRecordArrayClampsConfidenceAndExportsStableFields(): void
    {
        $record = new ReviewRecord([
            'status' => ReviewRecord::STATUS_APPROVED,
            'actor' => 'policy',
            'confidence' => 2.0,
            'timestamp' => 123.45,
        ]);

        $payload = $record->toArray();

        $this->assertSame(ReviewRecord::STATUS_APPROVED, $payload['status']);
        $this->assertSame('policy', $payload['actor']);
        $this->assertSame(1.0, $payload['confidence']);
        $this->assertSame(123.45, $payload['timestamp']);
        $this->assertArrayHasKey('original_value', $payload);
        $this->assertArrayHasKey('corrected_value', $payload);
        $this->assertArrayHasKey('policy_strategy', $payload);
    }
}
