<?php

namespace BlueFission\Tests\Automata\Language;

use BlueFission\Automata\Language\Claims\ClaimEnvelope;
use BlueFission\Automata\Language\Claims\ClaimNormalizationStatus;
use BlueFission\Automata\Language\Claims\ClaimNormalizer;
use BlueFission\Automata\Language\Claims\ClaimPredicate;
use BlueFission\Automata\Language\Claims\ClaimSourceForm;
use BlueFission\Automata\Language\Claims\PredicateOperator;
use PHPUnit\Framework\TestCase;

class ClaimNormalizationTest extends TestCase
{
    private array $semantic = [
        'subject' => ['name' => 'incident'],
        'behavior' => 'requires',
        'relationship' => 'needs',
        'object' => ['name' => 'review'],
        'context' => ['scope' => 'release'],
    ];

    private array $predicate = [
        'operator' => PredicateOperator::GREATER_THAN_OR_EQUAL,
        'path' => 'confidence',
        'value' => 0.8,
    ];

    public function testEnvelopeCreatesStableCorrelationAndTraceIdentifiers(): void
    {
        $envelope = new ClaimEnvelope(['payload' => $this->semantic]);

        $this->assertNotSame('', $envelope->id());
        $this->assertSame($envelope->id(), $envelope->field('correlation_id'));
        $this->assertSame($envelope->id(), $envelope->field('trace_id'));
    }

    public function testRawWrappedAndTextClaimsProduceEquivalentSemantics(): void
    {
        $normalizer = new ClaimNormalizer(fn (): array => [
            'statement' => $this->semantic,
            'predicate' => $this->predicate,
            'evidence' => ['parser' => 'fixture'],
        ]);
        $raw = $normalizer->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::RAW,
            'payload' => array_merge($this->semantic, ['predicate' => $this->predicate]),
        ]));
        $wrapped = $normalizer->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::WRAPPED,
            'payload' => ['statement' => $this->semantic, 'predicate' => $this->predicate],
        ]));
        $text = $normalizer->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::TEXT,
            'payload' => 'Incident requires review when confidence is at least 0.8.',
        ]));

        $this->assertTrue($raw->normalized());
        $this->assertTrue($wrapped->normalized());
        $this->assertTrue($text->normalized());

        $rawSnapshot = $this->semanticSnapshot($raw->statement()->snapshot());
        $this->assertSame($rawSnapshot, $this->semanticSnapshot($wrapped->statement()->snapshot()));
        $this->assertSame($rawSnapshot, $this->semanticSnapshot($text->statement()->snapshot()));
        $this->assertSame($raw->predicate()->toArray(), $wrapped->predicate()->toArray());
        $this->assertSame($raw->predicate()->toArray(), $text->predicate()->toArray());
    }

    public function testPredicateRemainsSeparateFromEvaluativeStatementConditions(): void
    {
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'payload' => array_merge($this->semantic, ['predicate' => $this->predicate]),
        ]));

        $this->assertInstanceOf(ClaimPredicate::class, $result->predicate());
        $this->assertSame([], $result->statement()->conditions());
        $this->assertSame('', $result->statement()->field('condition'));
    }

    public function testLogicalPredicateTreeRemainsTypedData(): void
    {
        $predicate = [
            'operator' => PredicateOperator::AND,
            'children' => [
                $this->predicate,
                ['operator' => PredicateOperator::EQUALS, 'path' => 'status', 'value' => 'approved'],
            ],
        ];
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'payload' => array_merge($this->semantic, ['predicate' => $predicate]),
        ]));

        $this->assertTrue($result->normalized());
        $this->assertSame(PredicateOperator::AND, $result->predicate()->operator());
        $this->assertCount(2, $result->predicate()->children());
        $this->assertSame('status', $result->predicate()->children()[1]->field('path'));
    }

    public function testUnsupportedPredicateProducesDiagnosticWithoutExecution(): void
    {
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'payload' => array_merge($this->semantic, [
                'predicate' => ['operator' => 'execute_host_expression', 'value' => 'dangerous()'],
            ]),
        ]));

        $this->assertSame(ClaimNormalizationStatus::MALFORMED, $result->status());
        $this->assertSame('unsupported_predicate_operator', $result->diagnostics()[0]->code());
        $this->assertSame('execute_host_expression', $result->predicate()->operator());
        $this->assertSame([], $result->statement()->conditions());
    }

    public function testMalformedPredicatePayloadFailsClosed(): void
    {
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'payload' => array_merge($this->semantic, ['predicate' => 'confidence >= 0.8']),
        ]));

        $this->assertSame(ClaimNormalizationStatus::MALFORMED, $result->status());
        $this->assertSame('malformed_predicate', $result->diagnostics()[0]->code());
        $this->assertNull($result->predicate());
    }

    public function testEmptyLogicalPredicateFailsClosed(): void
    {
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'payload' => array_merge($this->semantic, [
                'predicate' => ['operator' => PredicateOperator::AND, 'children' => []],
            ]),
        ]));

        $this->assertSame(ClaimNormalizationStatus::MALFORMED, $result->status());
        $this->assertSame('malformed_predicate', $result->diagnostics()[0]->code());
    }

    public function testTextClaimsRequireAnInjectedParser(): void
    {
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::TEXT,
            'payload' => 'Incident requires review.',
        ]));

        $this->assertSame(ClaimNormalizationStatus::UNSUPPORTED, $result->status());
        $this->assertSame('text_parser_unavailable', $result->diagnostics()[0]->code());
    }

    public function testPolicyCanRejectNormalizationBeforeParserInvocation(): void
    {
        $calls = 0;
        $normalizer = new ClaimNormalizer(function () use (&$calls): array {
            $calls++;
            return $this->semantic;
        });
        $result = $normalizer->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::TEXT,
            'payload' => 'Incident requires review.',
            'policy' => ['allow_normalization' => false],
        ]));

        $this->assertSame(ClaimNormalizationStatus::POLICY_REJECTED, $result->status());
        $this->assertSame(0, $calls);
    }

    public function testMultipleParserCandidatesAreExplicitlyAmbiguous(): void
    {
        $normalizer = new ClaimNormalizer(fn (): array => [
            'candidates' => [$this->semantic, array_merge($this->semantic, ['behavior' => 'suggests'])],
        ]);
        $result = $normalizer->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::TEXT,
            'payload' => 'Incident may require review.',
        ]));

        $this->assertSame(ClaimNormalizationStatus::AMBIGUOUS, $result->status());
        $this->assertSame(2, $result->diagnostics()[0]->field('details')['candidate_count']);
    }

    public function testSourceContextAndParserEvidenceRemainInspectable(): void
    {
        $normalizer = new ClaimNormalizer(fn (): array => [
            'statement' => $this->semantic,
            'evidence' => ['grammar' => 'fixture-v1'],
        ]);
        $result = $normalizer->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::TEXT,
            'payload' => 'Incident requires review.',
            'source_context' => ['document' => 'spec-7', 'line' => 14],
        ]));
        $evidence = $result->toArray()['evidence'];

        $this->assertSame(ClaimSourceForm::TEXT, $evidence['source_form']);
        $this->assertSame('spec-7', $evidence['source_context']['document']);
        $this->assertSame('fixture-v1', $evidence['normalization']['grammar']);
    }

    public function testMalformedWrappedClaimHasStableDiagnostic(): void
    {
        $result = (new ClaimNormalizer())->normalize(new ClaimEnvelope([
            'source_form' => ClaimSourceForm::WRAPPED,
            'payload' => ['version' => '1.0'],
        ]));

        $this->assertSame(ClaimNormalizationStatus::MALFORMED, $result->status());
        $this->assertSame('missing_wrapped_claim', $result->diagnostics()[0]->code());
    }

    private function semanticSnapshot(array $snapshot): array
    {
        return [
            'subject' => $snapshot['subject'],
            'behavior' => $snapshot['behavior'],
            'relationship' => $snapshot['relationship'],
            'object' => $snapshot['object'],
            'context' => $snapshot['context'],
        ];
    }
}
