<?php

namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\Input;
use BlueFission\Behavioral\Behaviors\Event;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Input preserves ordered synchronous delivery while validating fluent mutation. */
final class InputContractTest extends TestCase
{
    /** Names include literal zero; setters/scans chain while output stays event-based. */
    public function testFluentPipelinePreservesValuesAndProcessorOrder(): void
    {
        $input = new Input(static fn ($value) => $value + 1);
        $received = [];
        $input->behavior(new Event(Event::COMPLETE), static function ($event) use (&$received): void {
            $received[] = $event->context;
        });
        $this->assertSame($input, $input->name('0'));
        $this->assertSame('0', $input->name());
        $this->assertSame($input, $input->setProcessor(static fn ($value) => $value * 2));
        $this->assertSame($input, $input->scan(0, static fn ($value) => $value - 2));
        $input->scan(1);
        $this->assertSame([0, 2], $received);
    }

    /** Invalid optional callbacks must not become persistent stages or emit success. */
    public function testRejectedProcessorDoesNotPolluteLaterScans(): void
    {
        $input = new Input();
        $received = [];
        $input->behavior(new Event(Event::COMPLETE), static function ($event) use (&$received): void {
            $received[] = $event->context;
        });
        try { $input->scan('first', false); $this->fail('Invalid callback accepted.'); }
        catch (InvalidArgumentException) { $this->assertSame([], $received); }
        $input->scan(false)->scan(null)->scan('0');
        $this->assertSame([false, null, '0'], $received);
    }

    /** Invalid constructors no longer silently substitute identity for falsey values. */
    #[DataProvider('invalidProcessors')]
    public function testConstructorRejectsInvalidProcessors(mixed $processor): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Input($processor);
    }

    /** Only null selects the identity stage; all other inputs must be callable. */
    public static function invalidProcessors(): array { return [[false], [0], [''], [[]], ['missing-processor']]; }
}
