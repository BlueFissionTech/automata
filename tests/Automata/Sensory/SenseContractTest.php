<?php

namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Sense;
use BlueFission\Behavioral\Behaviors\Event;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Regression contracts for observations, independent calls and extension safety. */
final class SenseContractTest extends TestCase
{
    /** The outer result/completion retain the observed tokens before enhancement. */
    public function testDefaultPreparationAndReturnPreserveObservedText(): void
    {
        $sense = new Sense();
        $first = $completed = null;
        $sense->behavior(new Event(Event::SUCCESS), static function ($event) use (&$first): void {
            $first ??= $event->context;
        });
        $sense->behavior(new Event(Event::COMPLETE), static function ($event) use (&$completed): void {
            $completed = $event->context;
        });
        $result = $sense->invoke('blue blue green');
        $this->assertSame(['blue', 'green'], array_column(array_values($first['values']), 'value'));
        $this->assertSame($first, $result);
        $this->assertSame($result, $completed);
        $this->assertSame(2, $result['count']);
        $this->assertSame(3.0, $result['total']);
    }

    /** Falsey text is real content; an empty observation cannot retain prior chunks. */
    public function testRepeatedCallsAreIndependentIncludingZeroAndEmptyText(): void
    {
        $sense = new Sense();
        $zero = $sense->invoke('0');
        $this->assertSame('0', array_values($zero['values'])[0]['value']);
        $this->assertGreaterThanOrEqual(1, $sense->attentionState()['settings']['chunksize']);
        $first = $sense->invoke('blue green');
        $sense->invoke('longer observation with unrelated words');
        $again = $sense->invoke('blue green');
        // Collection timestamps legitimately differ; content and weights must not.
        $this->assertSame(array_column(array_values($first['values']), 'value'),
            array_column(array_values($again['values']), 'value'));
        $this->assertSame($first['count'], $again['count']);
        $this->assertSame($first['total'], $again['total']);
        $this->assertSame([], $sense->invoke('')['values']);
    }

    /** Reset removes retained observation data as well as sweep settings. */
    public function testResetClearsCapturedDataAndSettersAreFluent(): void
    {
        $sense = new Sense();
        $this->assertSame($sense, $sense->setPreparation(static fn ($input): array => [$input]));
        $this->assertSame($sense, $sense->setParent(null));
        $sense->invoke('private text');
        $this->assertSame($sense, $sense->reset());
        foreach (['_matrix', '_buffer', '_input'] as $field) {
            $value = (new \ReflectionProperty(Sense::class, $field))->getValue($sense);
            $this->assertSame($field === '_input' ? null : [], $value);
        }
        $this->assertSame(-1, $sense->attentionState()['depth']);
    }

    /** Malformed callbacks cannot emit success, and failure does not lock the instance. */
    #[DataProvider('invalidPreparation')]
    public function testPreparationRejectsMalformedChunks(mixed $prepared): void
    {
        $sense = new Sense();
        $events = 0;
        $sense->behavior(new Event(Event::SUCCESS), static function () use (&$events): void { ++$events; });
        $sense->setPreparation(static fn () => $prepared);
        try { $sense->invoke('test'); $this->fail('Malformed preparation was accepted.'); }
        catch (InvalidArgumentException) { $this->assertSame(0, $events); }
        $sense->setPreparation(static fn (): array => ['recovered']);
        $this->assertSame('recovered', array_values($sense->invoke('test')['values'])[0]['value']);
    }

    /** Values must be an array of string chunks, not implicit string coercions. */
    public static function invalidPreparation(): array
    {
        return [[null], ['text'], [[false]], [[12]], [[new \stdClass()]], [[[]]]];
    }

    /** Unsafe settings must fail before division/indexing or successful observations. */
    #[DataProvider('invalidSettings')]
    public function testInvalidConfigurationFailsClosed(string $key, mixed $value): void
    {
        $sense = new Sense();
        $sense->config($key, $value);
        $this->expectException(InvalidArgumentException::class);
        $sense->invoke('blue green');
    }

    /** Cover invalid sampling, dimensions, iteration bounds and false numeric strings. */
    public static function invalidSettings(): array
    {
        return [['quality', 0], ['quality', 2], ['quality', NAN], ['quality', '1'],
            ['dimensions', [0, 24]], ['dimensions', []], ['chunksize', 0],
            ['attention', -1], ['sensitivity', -1], ['flags', []]];
    }

    /** External callbacks cannot replace/reset the active sweep or recursively invoke it. */
    public function testCallbackReentryIsRejectedWithoutCorruptingObservation(): void
    {
        $sense = new Sense();
        $denials = 0;
        $attempted = false;
        $sense->behavior(new Event(Event::SUCCESS), static function () use ($sense, &$denials, &$attempted): void {
            // Keep the negative control finite even on the old unguarded code.
            if ($attempted) { return; }
            $attempted = true;
            foreach ([static fn () => $sense->invoke('nested'), static fn () => $sense->reset(),
                static fn () => $sense->setPreparation(static fn () => [])] as $action) {
                try { $action(); }
                catch (LogicException) { ++$denials; }
            }
        });
        $result = $sense->invoke('blue green');
        $this->assertSame(['blue', 'green'], array_column(array_values($result['values']), 'value'));
        $this->assertGreaterThanOrEqual(3, $denials);
    }

    /** Invalid callable registration is rejected at the setter boundary. */
    public function testRejectsNonCallablePreparation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Sense())->setPreparation('missing-sensory-function');
    }

    /** Novelty must look up the same translated key used to store observations. */
    public function testRepeatedChunksDoNotEarnRepeatedNoveltyAttention(): void
    {
        $observe = static function (string $text): int {
            $sense = new Sense();
            $remaining = null;
            $sense->setPreparation(static fn ($text): array => explode(' ', $text));
            $sense->behavior(new Event(Event::SUCCESS), static function () use ($sense, &$remaining): void {
                $remaining ??= $sense->attentionState()['settings']['attention'];
            });
            $sense->invoke($text);
            return $remaining;
        };
        $this->assertGreaterThan($observe('blue blue blue'), $observe('blue green yellow'));
    }

    /** Fractional sampling crosses row boundaries without skipping to nonexistent rows. */
    public function testFractionalSamplingUsesValidMatrixCoordinates(): void
    {
        $sense = new Sense();
        $sense->config('quality', 0.1);
        $sense->setPreparation(static fn (): array => array_map(static fn ($i): string => 'word-' . $i, range(0, 80)));
        $result = $sense->invoke('ignored by fixture preparation');
        $this->assertSame(array_map(static fn ($i): string => 'word-' . $i, range(0, 80, 10)),
            array_column(array_values($result['values']), 'value'));
    }

    /** Adaptive novelty/boredom adjustments must remain valid for the next sweep. */
    public function testAdaptiveQualityStaysWithinTheValidatedDomain(): void
    {
        foreach ([[0.9995, ['blue', 'green', 'yellow']], [0.1, array_fill(0, 1100, 'same')]] as [$quality, $chunks]) {
            $sense = new Sense();
            $sense->config('quality', $quality);
            $sense->setPreparation(static fn (): array => $chunks);
            $sense->invoke('fixture');
            $actual = $sense->attentionState()['settings']['quality'];
            $this->assertGreaterThan(0, $actual);
            $this->assertLessThanOrEqual(1, $actual);
        }
    }
}
