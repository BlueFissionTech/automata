<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\Qualification\FollowUpPlan;
use BlueFission\Automata\Qualification\NurtureSuggestion;
use BlueFission\Automata\Qualification\QualificationAudit;
use BlueFission\Automata\Qualification\QualificationResult;
use BlueFission\Automata\Qualification\QualificationScorer;

$score = (new QualificationScorer([
    ['key' => 'fit', 'weight' => 2, 'required' => true, 'minimum' => 0.5],
    ['key' => 'intent', 'weight' => 1],
    ['key' => 'consent', 'weight' => 1, 'required' => true, 'minimum' => 1.0],
]))->score('subject-42', [
    'fit' => ['value' => 0.85, 'confidence' => 0.9, 'evidence' => ['profile-match']],
    'intent' => ['value' => 0.65, 'confidence' => 0.8],
    'consent' => ['value' => 1.0, 'confidence' => 1.0, 'evidence' => ['consent-record']],
], ['trace_id' => 'trace-qualification-42']);

$suggestion = new NurtureSuggestion([
    'id' => 'resource-education',
    'action' => 'offer_relevant_resource',
    'reason' => 'Fit is strong and intent is still developing.',
    'allowed_when' => ['consent' => true],
    'prohibited_when' => ['do_not_contact' => true],
]);
$plan = new FollowUpPlan([
    'id' => 'plan-42',
    'subject_id' => 'subject-42',
    'max_steps' => 2,
    'stop_conditions' => ['converted' => true],
    'steps' => [
        ['id' => 'review-evidence', 'action' => 'review_new_evidence', 'requires_review' => false],
        ['id' => 'human-review', 'action' => 'request_human_review', 'requires_review' => true],
    ],
]);

$result = new QualificationResult([
    'score' => $score,
    'suggestions' => $suggestion->isAllowed(['consent' => true, 'do_not_contact' => false]) ? [$suggestion] : [],
    'follow_up_plan' => $plan,
    'audit' => new QualificationAudit([
        'trace_id' => 'trace-qualification-42',
        'subject_id' => 'subject-42',
        'rule_version' => 'qualification-rules-1',
        'evidence' => $score->toArray()['evidence'],
    ]),
]);

print_r($result->toArray());
