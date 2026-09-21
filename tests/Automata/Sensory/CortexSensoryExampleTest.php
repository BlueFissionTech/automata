<?php

namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Arr;
use BlueFission\Examples\Cortex\SensoryCapture;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/examples/generic/cortex/SensoryCapture.php';

/** Exercises the example adapter through real Input events and real Sense sweeps. */
final class CortexSensoryExampleTest extends TestCase
{
    /** Normalization preserves evidence; sensory statistics never invent a label. */
    public function testCapturePreservesTextNegationAndProvenance(): void
    {
        $raw = "  DO\tNOT book 0  ";
        $experience = (new SensoryCapture())->capture('observation', $raw, 'fixture-source', 'trace-1');
        $record = $experience->toArray();
        $data = $record['context']['data'];
        $this->assertSame($raw, $data['raw_text']);
        $this->assertSame('do not book 0', $data['utterance']);
        $this->assertSame(['do', 'not', 'book', '0'], Arr::make($data['sensory']['chunks'])->map(static fn (array $row) => $row['text'])->values()->val());
        $this->assertSame(4, $data['sensory']['distinct_chunks']);
        $this->assertSame(hash('sha256', $raw), $record['provenance']['raw_sha256']);
        $this->assertSame('fixture-source', $record['provenance']['source']);
        $this->assertSame('trace-1', $record['trace_id']);
        $this->assertSame([], $experience->outcomes());
        $this->assertNull($data['sensory']['confidence']);
    }

    /** Repetition changes a descriptive inspection hint, never training authority. */
    public function testFirstSweepSurvivesOptimizationAndZeroIsNotDropped(): void
    {
        $capture = new SensoryCapture();
        $repeated = $capture->capture('repeat', 'help help help', 'fixture', 'trace')->toArray()['context']['data'];
        $zero = $capture->capture('zero', '0', 'fixture', 'trace')->toArray()['context']['data'];
        $this->assertSame([['text' => 'help', 'weight' => 3.0]], $repeated['sensory']['chunks']);
        $this->assertSame('inspect', $repeated['sensory']['inspection_hint']);
        $this->assertSame('0', $zero['utterance']);
        $this->assertSame('0', $zero['sensory']['chunks'][0]['text']);
        $this->assertSame(8, $zero['sensory']['sweeps']);
        $this->assertSame(8, $zero['sensory']['completion_events']);
    }

    /** A fresh Sense per capture prevents recursive state leaking across observations. */
    public function testObservationsAreIsolatedAndSnapshotsStayDetached(): void
    {
        $capture = new SensoryCapture();
        $first = $capture->capture('first', 'book book book cancel', 'fixture', 'trace');
        $before = $first->toArray();
        $capture->capture('middle', 'help', 'fixture', 'trace');
        $again = $capture->capture('again', 'book book book cancel', 'fixture', 'trace');
        $this->assertSame($before['context']['data'], $again->toArray()['context']['data']);
        $changed = $first->toArray();
        $changed['context']['data']['sensory']['chunks'][0]['text'] = 'corrupted';
        $this->assertSame($before, $first->toArray());
        $this->assertSame(1, $before['context']['data']['sensory']['sweeps']);
        $this->assertSame('standard', $before['context']['data']['sensory']['inspection_hint']);
    }

    /** Invalid or oversized observations fail before an Experience can be returned. */
    #[DataProvider('invalidInputs')]
    public function testRejectsUnsupportedInput(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SensoryCapture())->capture('invalid', $input, 'fixture', 'trace');
    }

    /** The example deliberately supports bounded ASCII text, not arbitrary media. */
    public static function invalidInputs(): array
    {
        return [ [null], [false], [0], [[]], [new \stdClass()], [''], [" \t\n"],
            ["hello\0world"], ['café'], [str_repeat('a', 257)], [str_repeat('a ', 33)] ];
    }
}
