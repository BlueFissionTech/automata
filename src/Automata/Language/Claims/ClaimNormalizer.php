<?php

namespace BlueFission\Automata\Language\Claims;

use BlueFission\Arr;
use BlueFission\Automata\Language\Statement;
use BlueFission\Str;
use Throwable;

class ClaimNormalizer implements IClaimNormalizer
{
    private const STATEMENT_FIELDS = [
        'type',
        'context',
        'priority',
        'subject',
        'negation',
        'modality',
        'behavior',
        'object',
        'relationship',
        'indirect_object',
        'position',
    ];

    protected $textParser;

    public function __construct(?callable $textParser = null)
    {
        $this->textParser = $textParser;
    }

    public function normalize(ClaimEnvelope $envelope): ClaimNormalizationResult
    {
        if (($envelope->policy()['allow_normalization'] ?? true) !== true) {
            return $this->failure(
                $envelope,
                ClaimNormalizationStatus::POLICY_REJECTED,
                'normalization_not_allowed',
                'Claim normalization is not allowed by policy.'
            );
        }

        if (!ClaimSourceForm::isKnown($envelope->sourceForm())) {
            return $this->failure(
                $envelope,
                ClaimNormalizationStatus::UNSUPPORTED,
                'unsupported_source_form',
                'The claim source form is not supported.',
                ['source_form' => $envelope->sourceForm()]
            );
        }

        $normalized = $this->normalizedPayload($envelope);
        if ($normalized instanceof ClaimNormalizationResult) {
            return $normalized;
        }

        if (isset($normalized['candidates']) && Arr::is($normalized['candidates']) && Arr::size($normalized['candidates']) !== 1) {
            return $this->failure(
                $envelope,
                ClaimNormalizationStatus::AMBIGUOUS,
                'ambiguous_claim',
                'The claim produced multiple semantic candidates.',
                ['candidate_count' => Arr::size($normalized['candidates'])]
            );
        }

        if (isset($normalized['candidates'][0]) && Arr::isAssoc($normalized['candidates'][0])) {
            $normalized = Arr::make($normalized['candidates'][0])->toArray();
        }

        $statementData = $normalized['statement'] ?? $normalized['claim'] ?? $normalized;
        if (!Arr::isAssoc($statementData)) {
            return $this->failure(
                $envelope,
                ClaimNormalizationStatus::MALFORMED,
                'malformed_statement',
                'The normalized claim must contain an associative statement payload.'
            );
        }

        $predicateData = $normalized['predicate'] ?? ($statementData['predicate'] ?? null);
        $statement = new Statement();
        $statement->assign($this->statementPayload(Arr::make($statementData)->toArray()));

        if ($predicateData !== null
            && $predicateData !== []
            && !$predicateData instanceof ClaimPredicate
            && !Arr::isAssoc($predicateData)) {
            return new ClaimNormalizationResult([
                'status' => ClaimNormalizationStatus::MALFORMED,
                'envelope' => $envelope,
                'statement' => $statement,
                'evidence' => $this->evidence($envelope, $normalized),
                'diagnostics' => [new ClaimDiagnostic([
                    'code' => 'malformed_predicate',
                    'message' => 'The claim predicate must be an associative typed value.',
                ])],
            ]);
        }

        $predicate = $this->predicate($predicateData);
        $unsupportedOperator = $predicate ? $this->unsupportedPredicateOperator($predicate) : null;
        if ($predicate && $unsupportedOperator !== null) {
            return new ClaimNormalizationResult([
                'status' => ClaimNormalizationStatus::MALFORMED,
                'envelope' => $envelope,
                'statement' => $statement,
                'predicate' => $predicate,
                'evidence' => $this->evidence($envelope, $normalized),
                'diagnostics' => [new ClaimDiagnostic([
                    'code' => 'unsupported_predicate_operator',
                    'message' => 'The claim contains an unsupported predicate operator.',
                    'details' => ['operator' => $unsupportedOperator],
                ])],
            ]);
        }

        if ($predicate && !$predicate->structureIsValid()) {
            return new ClaimNormalizationResult([
                'status' => ClaimNormalizationStatus::MALFORMED,
                'envelope' => $envelope,
                'statement' => $statement,
                'predicate' => $predicate,
                'evidence' => $this->evidence($envelope, $normalized),
                'diagnostics' => [new ClaimDiagnostic([
                    'code' => 'malformed_predicate',
                    'message' => 'The claim predicate structure is invalid for its operator.',
                ])],
            ]);
        }

        return new ClaimNormalizationResult([
            'status' => ClaimNormalizationStatus::NORMALIZED,
            'envelope' => $envelope,
            'statement' => $statement,
            'predicate' => $predicate,
            'evidence' => $this->evidence($envelope, $normalized),
        ]);
    }

    private function normalizedPayload(ClaimEnvelope $envelope): array|ClaimNormalizationResult
    {
        $payload = $envelope->payload();

        if ($envelope->sourceForm() === ClaimSourceForm::TEXT) {
            $text = Arr::isAssoc($payload) ? ($payload['text'] ?? '') : $payload;
            if (!is_string($text) || Str::trim($text) === '') {
                return $this->failure(
                    $envelope,
                    ClaimNormalizationStatus::MALFORMED,
                    'missing_claim_text',
                    'A textual claim must contain non-empty text.'
                );
            }

            if (!$this->textParser) {
                return $this->failure(
                    $envelope,
                    ClaimNormalizationStatus::UNSUPPORTED,
                    'text_parser_unavailable',
                    'No textual claim parser adapter is configured.'
                );
            }

            try {
                $payload = ($this->textParser)($text, $envelope->sourceContext(), $envelope);
            } catch (Throwable $exception) {
                return $this->failure(
                    $envelope,
                    ClaimNormalizationStatus::MALFORMED,
                    'text_parser_failed',
                    'The textual claim parser failed.',
                    ['exception' => $exception::class]
                );
            }
        }

        if (!Arr::isAssoc($payload)) {
            return $this->failure(
                $envelope,
                ClaimNormalizationStatus::MALFORMED,
                'malformed_claim_payload',
                'The claim payload must normalize to an associative array.'
            );
        }

        $payload = Arr::make($payload)->toArray();

        if ($envelope->sourceForm() === ClaimSourceForm::WRAPPED) {
            if (!isset($payload['statement']) && !isset($payload['claim']) && !isset($payload['candidates'])) {
                return $this->failure(
                    $envelope,
                    ClaimNormalizationStatus::MALFORMED,
                    'missing_wrapped_claim',
                    'A wrapped claim must contain statement, claim, or candidates.'
                );
            }
        }

        return $payload;
    }

    private function statementPayload(array $payload): array
    {
        $statement = [];

        foreach (self::STATEMENT_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $statement[$field] = $payload[$field];
            }
        }

        return $statement;
    }

    private function predicate(mixed $payload): ?ClaimPredicate
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        if ($payload instanceof ClaimPredicate) {
            return $payload;
        }

        return Arr::isAssoc($payload)
            ? new ClaimPredicate(Arr::make($payload)->toArray())
            : null;
    }

    private function unsupportedPredicateOperator(ClaimPredicate $predicate): ?string
    {
        if (!$predicate->isKnown()) {
            return $predicate->operator();
        }

        foreach ($predicate->children() as $child) {
            $unsupported = $this->unsupportedPredicateOperator($child);
            if ($unsupported !== null) {
                return $unsupported;
            }
        }

        return null;
    }

    private function evidence(ClaimEnvelope $envelope, array $normalized): array
    {
        return [
            'source_form' => $envelope->sourceForm(),
            'source_context' => $envelope->sourceContext(),
            'normalization' => Arr::make($normalized['evidence'] ?? [])->toArray(),
        ];
    }

    private function failure(
        ClaimEnvelope $envelope,
        string $status,
        string $code,
        string $message,
        array $details = []
    ): ClaimNormalizationResult {
        return new ClaimNormalizationResult([
            'status' => $status,
            'envelope' => $envelope,
            'diagnostics' => [new ClaimDiagnostic([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ])],
        ]);
    }
}
