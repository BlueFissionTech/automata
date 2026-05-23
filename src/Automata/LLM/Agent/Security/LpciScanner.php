<?php

namespace BlueFission\Automata\LLM\Agent\Security;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Str;

class LpciScanner
{
    protected array $dangerTerms = [
        'ignore previous',
        'reveal secret',
        'system prompt',
        'exfiltrate',
        'delete logs',
        'hide this',
        'store this instruction',
        'future sessions',
    ];

    /**
     * Scan one content surface for LPCI indicators.
     */
    public function scan(string $content, array $context = []): array
    {
        $findings = [];
        $lower = Str::lower($content);

        foreach ($this->dangerTerms as $term) {
            if (Str::pos($lower, $term) !== false) {
                $findings[] = new LpciFinding([
                    'status' => $this->statusForTerm($term),
                    'stage' => $this->stageForTerm($term),
                    'category' => $this->categoryForTerm($term),
                    'message' => 'Potential LPCI control phrase detected.',
                    'evidence' => ['term' => $term, 'context' => $context],
                ]);
            }
        }

        $findings = $this->appendFindings($findings, $this->scanEncoded($content, $context));

        if (preg_match('/\b(when|after|if)\b.{0,80}\b(tool|turn|keyword|next)\b/i', $content)) {
            $findings[] = new LpciFinding([
                'status' => LpciFinding::WARNING,
                'stage' => LpciTaxonomy::S3_TRIGGER_EXECUTION,
                'category' => LpciTaxonomy::CATEGORY_TRIGGER,
                'message' => 'Conditional activation language detected.',
                'evidence' => ['context' => $context],
            ]);
        }

        if (Arr::count($findings) === 0) {
            $findings[] = new LpciFinding([
                'status' => LpciFinding::ALLOWED,
                'message' => 'No LPCI indicators detected.',
                'evidence' => ['context' => $context],
            ]);
        }

        return $findings;
    }

    /**
     * Sanitize content and return scan findings for audit trails.
     */
    public function sanitize(string $content, array $context = []): array
    {
        $findings = $this->scan($content, $context);
        $sanitized = $content;

        foreach ($findings as $finding) {
            if (!$finding->blocked() && !$finding->warning()) {
                continue;
            }

            $term = $finding->toArray()['evidence']['term'] ?? null;
            if ($term) {
                $sanitized = preg_replace('/' . preg_quote($term, '/') . '/i', '[filtered lpci]', $sanitized) ?? $sanitized;
            }
        }

        if ($this->hasStatus($findings, LpciFinding::BLOCKED)) {
            $sanitized = '[filtered lpci content]';
        }

        return [
            'content' => $sanitized,
            'findings' => $this->findingsToArray($findings),
            'status' => $this->highestStatus($findings),
        ];
    }

    protected function scanEncoded(string $content, array $context): array
    {
        $findings = [];
        preg_match_all('/[A-Za-z0-9+\/=]{16,}/', $content, $matches);
        foreach ($matches[0] ?? [] as $candidate) {
            $decoded = base64_decode($candidate, true);
            if ($decoded && $decoded !== $candidate) {
                foreach ($this->scanDecoded($decoded, 'base64', $context) as $finding) {
                    $findings[] = $finding;
                }

                $rot = str_rot13($decoded);
                foreach ($this->scanDecoded($rot, 'base64+rot13', $context) as $finding) {
                    $findings[] = $finding;
                }
            }
        }

        $rot = str_rot13($content);
        foreach ($this->scanDecoded($rot, 'rot13', $context) as $finding) {
            $findings[] = $finding;
        }

        return $findings;
    }

    protected function scanDecoded(string $decoded, string $encoding, array $context): array
    {
        $findings = [];
        $lower = Str::lower($decoded);
        foreach ($this->dangerTerms as $term) {
            if (Str::pos($lower, $term) !== false) {
                $findings[] = new LpciFinding([
                    'status' => LpciFinding::BLOCKED,
                    'stage' => LpciTaxonomy::S5_EVASION_OBFUSCATION,
                    'category' => LpciTaxonomy::CATEGORY_ENCODING,
                    'message' => 'Encoded LPCI indicator detected.',
                    'evidence' => [
                        'encoding' => $encoding,
                        'term' => $term,
                        'context' => $context,
                    ],
                ]);
            }
        }

        return $findings;
    }

    protected function highestStatus(array $findings): string
    {
        foreach ([LpciFinding::BLOCKED, LpciFinding::WARNING, LpciFinding::UNKNOWN, LpciFinding::ALLOWED] as $status) {
            if ($this->hasStatus($findings, $status)) {
                return $status;
            }
        }

        return LpciFinding::UNKNOWN;
    }

    protected function hasStatus(array $findings, string $status): bool
    {
        foreach ($findings as $finding) {
            if ($finding->status() === $status) {
                return true;
            }
        }

        return false;
    }

    protected function statusForTerm(string $term): string
    {
        return Arr::has([
            'ignore previous',
            'reveal secret',
            'system prompt',
            'exfiltrate',
            'delete logs',
            'hide this',
            'store this instruction',
            'future sessions',
        ], $term, true)
            ? LpciFinding::BLOCKED
            : LpciFinding::WARNING;
    }

    protected function stageForTerm(string $term): string
    {
        return match ($term) {
            'delete logs', 'hide this' => LpciTaxonomy::S6_TRACE_TAMPERING,
            'store this instruction', 'future sessions' => LpciTaxonomy::S4_PERSISTENCE_REUSE,
            default => LpciTaxonomy::S2_LOGIC_LAYER_INJECTION,
        };
    }

    protected function categoryForTerm(string $term): string
    {
        return match ($term) {
            'delete logs', 'hide this' => LpciTaxonomy::CATEGORY_TRACE,
            'exfiltrate', 'reveal secret', 'system prompt' => LpciTaxonomy::CATEGORY_EXFILTRATION,
            default => LpciTaxonomy::CATEGORY_SEMANTIC_REFRAME,
        };
    }

    /**
     * Append finding lists without raw array merge helpers.
     */
    protected function appendFindings(array $left, array $right): array
    {
        foreach ($right as $finding) {
            $left[] = $finding;
        }

        return $left;
    }

    /**
     * Export finding objects as arrays.
     */
    protected function findingsToArray(array $findings): array
    {
        return (new Collection($findings))
            ->map(fn (LpciFinding $finding): array => $finding->toArray())
            ->toArray();
    }
}
