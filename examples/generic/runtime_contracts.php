<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Feedback\FeedbackSignal;
use BlueFission\Automata\Feedback\ReviewRecord;
use BlueFission\Automata\Language\Statement;
use BlueFission\Automata\LLM\Agent\Integration\AgentIntegrationContract;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureManager;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureProfile;
use BlueFission\Automata\Security\HerdSignalContract;
use BlueFission\Net\HTTP;

$contract = AgentIntegrationContract::standard();
$capabilityKeys = ['statement', 'feedback', 'domain_evaluation', 'lane_pressure'];
$capabilities = Arr::map($capabilityKeys, fn (string $key): array => [
    'key' => $key,
    'feature' => $contract->capabilityVocabulary($key)['feature'],
    'fields' => $contract->capabilityVocabulary($key)['stable_fields'],
]);

$statement = new Statement();
$statement->assign([
    'subject' => ['name' => 'request.context', 'description' => 'Resolved request context'],
    'behavior' => 'requires',
    'relationship' => 'needs',
    'object' => ['name' => 'step_up_review', 'description' => 'Bounded review action'],
    'context' => [
        'confidence' => 0.88,
        'phase' => 'resolved',
        'scope' => 'runtime.contract',
        'provenance' => [
            'source' => 'example',
            'grammar' => 'adapter-owned',
        ],
        'status' => 'resolved',
    ],
]);

$review = ReviewRecord::correction(
    ['decision' => 'allow'],
    ['decision' => 'challenge'],
    'policy-reviewer',
    'Risk evidence exceeded the challenge threshold.',
    0.84,
    [
        'trace' => ['task_id' => 'example-runtime-contracts'],
        'evidence' => ['source' => 'herd.result'],
        'policy_strategy' => 'human_review',
        'tags' => ['policy', 'review'],
        'context' => ['surface' => 'example'],
    ]
);

$trainingSignal = ReviewRecord::trainingSignal(
    'trainer',
    FeedbackSignal::positive(0.72),
    ['trace' => ['task_id' => 'example-runtime-contracts']]
);

$signals = [
    HerdSignalContract::signal([
        'id' => 'signal.device_ref',
        'type' => 'device_reputation',
        'score' => 0.44,
        'confidence' => 0.9,
        'sensitivity' => HerdSignalContract::SENSITIVITY_INTERNAL,
        'evidence' => ['device_ref' => 'device:hash'],
    ]),
    HerdSignalContract::signal([
        'id' => 'signal.geo_velocity',
        'type' => 'geo_velocity',
        'score' => 0.67,
        'confidence' => 0.8,
        'sensitivity' => HerdSignalContract::SENSITIVITY_SENSITIVE,
        'evidence' => ['event_ref' => 'event:geo-summary'],
    ]),
];

$herdResult = HerdSignalContract::result(0.67, $signals, [
    'subject_ref' => 'principal:example',
    'session_ref' => 'session:example',
    'action' => 'update_recovery_channel',
    'privacy' => HerdSignalContract::privacyGuidance(),
]);

$pressure = LanePressureManager::standard()->assess(
    LanePressureProfile::longHorizonTask([
        'spec' => true,
        'source_map' => true,
        'durable_memory' => true,
        'runbook' => false,
        'milestones' => 0.5,
        'audit_log' => true,
        'verification' => 0.75,
        'observability' => 0.4,
        'isolated_workspace' => true,
        'repair_loop' => true,
        'rollback_plan' => false,
        'local_governance' => 0.8,
    ]),
    ['task_id' => 'example-runtime-contracts']
);

print HTTP::jsonEncode([
    'capability_vocabulary' => $capabilities,
    'statement_bundle' => [
        'name' => $statement->snapshot()['name'],
        'relationship' => array_key_first($statement->snapshot()['relations']),
        'context' => $statement->snapshot()['context']['data'],
    ],
    'feedback' => [
        'review' => $review->toArray(),
        'training_signal' => $trainingSignal->toArray(),
    ],
    'herd' => [
        'decision' => $herdResult['decision'],
        'challenge' => $herdResult['challenge'],
        'restrict' => $herdResult['restrict'],
        'reasons' => $herdResult['reasons'],
        'retention_rules' => HerdSignalContract::retentionRules(),
    ],
    'lane_pressure' => [
        'dominant_lane' => $pressure['dominant_lane'],
        'overall_level' => $pressure['overall_level'],
        'recommendations' => $pressure['recommendations'],
    ],
]) . PHP_EOL;
