<?php

declare(strict_types=1);

namespace BlueFission\Examples\Cortex;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Automata\Context;
use BlueFission\Automata\Language\Statement;
use BlueFission\Automata\Learning\Experience;
use BlueFission\Automata\Sensory\{Input, Sense};
use BlueFission\Behavioral\Behaviors\Event;
use InvalidArgumentException;
use RuntimeException;

/**
 * Example-owned text ingestion assembly, not a new library sensory interface.
 *
 * Input normalizes admitted text; Sense describes chunks; this adapter explicitly
 * projects both into an Experience. It neither infers intent nor creates labels.
 * The caller supplies source/trace identity and owns trust, consent and retention.
 * Trusted in-process hooks are assumed, just as for the underlying classes.
 */
final class SensoryCapture
{
    public const MAX_BYTES = 256;
    public const MAX_WORDS = 32;

    /**
     * Capture one observation without training or mutating any previous capture.
     *
     * A fresh Input/Sense pair isolates recursive state and processor registration.
     * The byte/word limits bound this fixture's input; they are not a wall-clock
     * deadline or a substitute for host scheduling. No queue/service is involved.
     */
    public function capture(string $id, mixed $raw, string $source, string $trace): Experience
    {
        $observed = null;
        $input = new Input(self::normalize(...));
        $input->name($source);

        // Completion is synchronous. Register before scan() and consume context,
        // rather than expecting scan() to return its transformed payload.
        $input->behavior(new Event(Event::COMPLETE), static function ($event) use (&$observed): void {
            $text = $event->context;
            $sense = new Sense();

            // The default depth-zero preparer currently loses its tokens. This
            // documented extension point also retains negation and literal "0".
            // Reuse the same word boundaries on every sweep, avoiding substring
            // enhancement changing the observation's token meaning mid-analysis.
            $sense->setPreparation(static fn (string $text): array => Str::make($text)->split(' ')->val());
            $firstSweep = null;
            $sweeps = $completions = 0;
            $sense->behavior(new Event(Event::SUCCESS), static function ($event) use (&$firstSweep, &$sweeps): void {
                ++$sweeps;
                // SUCCESS precedes optimize(), which may discard rare chunks.
                // First-sweep evidence must survive later recursive completions.
                $firstSweep ??= $event->context;
            });
            $sense->behavior(new Event(Event::COMPLETE), static function () use (&$completions): void {
                ++$completions;
            });
            $sense->invoke($text);
            if (!is_array($firstSweep) || $completions === 0) {
                throw new RuntimeException('Sense did not produce an observable sweep and completion.');
            }

            // Store text and weights, not CRC32 keys as semantic identities. The
            // preserved utterance remains authoritative when grouping is lossy.
            $chunks = Arr::make($firstSweep['values'])
                ->map(static fn (array $entry): array => [
                    'text' => $entry['value'], 'weight' => (float)$entry['weight'],
                ])->values()->val();
            $observed = ['utterance' => $text, 'sensory' => [
                'chunks' => $chunks,
                'distinct_chunks' => $firstSweep['count'],
                'weight_variance' => (float)$firstSweep['variance1'],
                'sweeps' => $sweeps,
                'completion_events' => $completions,
                'depth' => $sense->attentionState()['depth'],
                'attention_score' => $sense->attentionScore(),
                'confidence' => null,
                // An explicit fixture policy: sparse/repetitive text merits
                // inspection. This hint neither blocks labels nor grants authority.
                'inspection_hint' => $firstSweep['count'] < 2 ? 'inspect' : 'standard',
            ]];
        });
        $input->scan($raw);
        if (!is_array($observed)) {
            throw new RuntimeException('Input did not deliver its normalized observation.');
        }

        // "reported" is an adapter-declared relation, not a parsed intent or fact
        // asserted by Sense. Raw content stays available alongside normalization.
        $statement = new Statement();
        $statement->assign(['subject' => $source, 'behavior' => 'reported', 'object' => $observed['utterance']]);
        return Experience::fromStatements($id, [$statement], new Context([
            'raw_text' => $raw, ...$observed,
        ]), [
            'trace_id' => $trace,
            'provenance' => ['source' => $source, 'raw_sha256' => hash('sha256', $raw),
                'adapter' => 'cortex.sensory-text', 'adapter_version' => '1'],
        ]);
    }

    /**
     * Fixture policy: strict bounded ASCII text, lowercase and collapsed whitespace.
     *
     * Reject instead of coercing, truncating or stripping unsupported bytes. This
     * is intentionally narrower than general Unicode/media ingestion. Lowercase
     * and whitespace normalization are lossy, so capture() also retains raw text.
     */
    private static function normalize(mixed $raw): string
    {
        if (!is_string($raw) || Str::make($raw)->len() > self::MAX_BYTES
            || !preg_match('/\A[\x20-\x7E\t\r\n]+\z/', $raw)) {
            throw new InvalidArgumentException('Expected at most 256 bytes of ASCII text.');
        }
        $text = Str::make($raw)->trim()->lower()->replacePattern('/\s+/', ' ')->val();
        if ($text === '' || Str::make($text)->split(' ')->count() > self::MAX_WORDS) {
            throw new InvalidArgumentException('Expected between 1 and 32 words.');
        }
        return $text;
    }
}
