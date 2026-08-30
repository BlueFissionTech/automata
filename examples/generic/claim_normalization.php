<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\Language\Claims\ClaimEnvelope;
use BlueFission\Automata\Language\Claims\ClaimNormalizer;
use BlueFission\Automata\Language\Claims\ClaimSourceForm;
use BlueFission\Automata\Language\Claims\PredicateOperator;
use BlueFission\Net\HTTP;

$normalizer = new ClaimNormalizer(
    fn (string $text, array $context): array => [
        'statement' => [
            'subject' => ['name' => 'incident', 'meta' => ['source_text' => $text]],
            'behavior' => 'requires',
            'relationship' => 'needs',
            'object' => ['name' => 'review'],
            'context' => ['scope' => $context['scope'] ?? 'unknown'],
        ],
        'predicate' => [
            'operator' => PredicateOperator::GREATER_THAN_OR_EQUAL,
            'path' => 'confidence',
            'value' => 0.8,
        ],
        'evidence' => ['grammar' => 'example-v1'],
    ]
);

$result = $normalizer->normalize(new ClaimEnvelope([
    'source_form' => ClaimSourceForm::TEXT,
    'payload' => 'Incident requires review when confidence is at least 0.8.',
    'source_context' => ['document' => 'release-spec', 'scope' => 'release'],
    'policy' => ['allow_normalization' => true],
]));

print HTTP::jsonEncode($result->toArray()) . PHP_EOL;
