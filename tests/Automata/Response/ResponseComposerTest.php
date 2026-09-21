<?php

namespace BlueFission\Tests\Automata\Response;

use BlueFission\Arr;
use BlueFission\Automata\Response\ResponseComposer;
use BlueFission\Automata\Response\ResponseEnvelope;
use BlueFission\Automata\Response\ResponseFragment;
use BlueFission\Automata\Response\ResponsePolicy;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ResponseComposerTest extends TestCase
{
    private function composer(array $fragments, float $threshold = 1.0): ResponseComposer
    {
        return new ResponseComposer(new ResponseEnvelope('response-1', Arr::make($fragments)->map(static fn (array $record): ResponseFragment => new ResponseFragment($record))->val(), ['trace_id' => 'trace-1', 'experience_id' => 'experience-1']),
            new ResponsePolicy(['threshold' => $threshold, 'minimum_threshold' => $threshold]));
    }

    private function receipts(array $release, bool $successful = true): array
    {
        $receipts = [];
        foreach ($release['fragments'] as $fragment) {
            $receipts[$fragment['id']] = ['successful' => $successful, 'evidence' => ['receiver' => 'fixture']];
        }
        return $receipts;
    }

    public function testWeightedProgressCannotBypassBlockingRequirements(): void
    {
        $composer = $this->composer([
            ['id' => 'approval', 'channel' => 'state', 'weight' => 0, 'blocking' => true],
            ['id' => 'speech', 'channel' => 'text', 'weight' => 3],
            ['id' => 'lookup', 'channel' => 'data', 'weight' => 7],
        ], 0.3);
        $composer->resolve('speech', 'I can help.')->progress('lookup', 0.5);
        $this->assertEqualsWithDelta(0.65, $composer->state()['completion'], 0.00001);
        $this->assertNull($composer->prepare(0));
        $release = $composer->resolve('approval', ['approved' => true])->prepare(1);
        $this->assertSame(['approval', 'speech'], Arr::make($release['fragments'])->map(static fn (array $row) => $row['id'])->values()->val());
        $this->assertSame('trace-1', $release['trace']['trace_id']);
    }

    public function testDependenciesWaitForSuccessfulReceiverReceipts(): void
    {
        $composer = $this->composer([
            ['id' => 'act', 'channel' => 'tool'],
            ['id' => 'confirm', 'channel' => 'text', 'dependencies' => ['act']],
        ]);
        $composer->resolve('confirm', 'The fixture action completed.')->resolve('act', ['operation' => 'fixture']);
        $first = $composer->prepare(0);
        $this->assertSame(['act'], Arr::make($first['fragments'])->map(static fn (array $row) => $row['id'])->values()->val());
        $this->assertSame($first, $composer->prepare(1));
        $this->assertTrue($composer->acknowledge($first['id'], $this->receipts($first)));
        $second = $composer->prepare(2);
        $this->assertSame(['confirm'], Arr::make($second['fragments'])->map(static fn (array $row) => $row['id'])->values()->val());
        $this->assertNotSame($first['id'], $second['id']);
        $composer->acknowledge($second['id'], $this->receipts($second));
        $this->assertNull($composer->prepare(3));
    }

    public function testFailedReceiverReceiptDoesNotUnblockConfirmation(): void
    {
        $composer = $this->composer([
            ['id' => 'act', 'channel' => 'tool'],
            ['id' => 'confirm', 'channel' => 'text', 'dependencies' => ['act']],
        ])->resolve('act', 'action')->resolve('confirm', 'confirmation');
        $release = $composer->prepare(0);
        $composer->acknowledge($release['id'], $this->receipts($release, false));
        $this->assertNull($composer->prepare(1));
        $this->assertFalse($composer->state()['fragments']['act']['receipt']['successful']);
    }

    public function testRestoreRetainsPendingIdentityAndAcknowledgedFragments(): void
    {
        $composer = $this->composer([['id' => 'speech', 'channel' => 'text']])->resolve('speech', false);
        $release = $composer->prepare(3);
        $snapshot = json_decode(json_encode($composer->snapshot(),
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR);
        $restored = ResponseComposer::restore($snapshot);
        $this->assertSame($release, $restored->prepare(4));
        $receipts = $this->receipts($release);
        $this->assertTrue($restored->acknowledge($release['id'], $receipts));
        $this->assertFalse($restored->acknowledge($release['id'], $receipts));
        $again = ResponseComposer::restore($restored->snapshot());
        $this->assertNull($again->prepare(5));
        $this->assertFalse($again->state()['fragments']['speech']['payload']);
    }

    public function testAcknowledgementRequiresCompleteStrictReceiptsAndRejectsConflicts(): void
    {
        $composer = $this->composer([['id' => 'speech', 'channel' => 'text']])->resolve('speech', 'hello');
        $release = $composer->prepare(0);
        foreach ([[], ['speech' => ['successful' => 'true', 'evidence' => []]],
            ['speech' => ['successful' => true]], ['invented' => ['successful' => true, 'evidence' => []]]] as $bad) {
            try { $composer->acknowledge($release['id'], $bad); $this->fail('Expected receipt rejection.'); }
            catch (InvalidArgumentException $error) { $this->assertSame($release, $composer->prepare(0)); }
        }
        $composer->acknowledge($release['id'], $this->receipts($release));
        $this->expectException(LogicException::class);
        $composer->acknowledge($release['id'], $this->receipts($release, false));
    }

    public function testCancellationStopsNewOutputAndPreservesInflightReconciliation(): void
    {
        $composer = $this->composer([
            ['id' => 'fast', 'channel' => 'text'], ['id' => 'slow', 'channel' => 'audio'],
        ], 0.5)->resolve('fast', 'hello');
        $release = $composer->prepare(0);
        $composer->cancel('caller_cancelled');
        $this->assertNull($composer->prepare(1));
        $this->assertSame('cancelled', $composer->state()['fragments']['slow']['status']);
        $restored = ResponseComposer::restore($composer->snapshot());
        $this->assertTrue($restored->acknowledge($release['id'], $this->receipts($release)));
        $this->assertNull($restored->prepare(2));
        $this->expectException(LogicException::class);
        $restored->resolve('slow', 'late');
    }

    public function testDeadlineFallbackCannotProveOriginalActionSucceeded(): void
    {
        $composer = $this->composer([
            ['id' => 'lookup', 'channel' => 'text', 'deadline_ms' => 10,
                'fallback' => ['payload' => 'Lookup unavailable.', 'confidence' => 1.0]],
            ['id' => 'confirm', 'channel' => 'text', 'dependencies' => ['lookup']],
        ], 0.5)->resolve('confirm', 'Lookup succeeded.');
        $this->assertNull($composer->prepare(9));
        $release = $composer->prepare(10);
        $this->assertSame('fallback', $release['fragments'][0]['resolution']);
        $this->assertSame('Lookup unavailable.', $release['fragments'][0]['payload']);
        $composer->acknowledge($release['id'], $this->receipts($release));
        $this->assertNull($composer->prepare(11));
    }

    public function testFailureOfBlockingFragmentStopsRelease(): void
    {
        $composer = $this->composer([
            ['id' => 'approval', 'channel' => 'state', 'blocking' => true, 'weight' => 0],
            ['id' => 'speech', 'channel' => 'text'],
        ])->resolve('speech', 'Ready.')->fail('approval', 'denied');
        $this->assertNull($composer->prepare(0));
        $this->assertSame('failed', $composer->state()['fragments']['approval']['status']);
    }

    public function testPolicyFloorCannotBeLowered(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResponsePolicy(['threshold' => 0.3, 'minimum_threshold' => 0.8]);
    }

    public function testInvalidGraphIsRejectedBeforeWork(): void
    {
        foreach ([
            [['id' => 'a', 'channel' => 'text', 'dependencies' => ['missing']]],
            [['id' => 'a', 'channel' => 'text', 'dependencies' => ['a']]],
            [['id' => 'a', 'channel' => 'text', 'dependencies' => ['b']], ['id' => 'b', 'channel' => 'text', 'dependencies' => ['a']]],
            [['id' => 'a', 'channel' => 'text'], ['id' => 'a', 'channel' => 'text']],
            [['id' => 'a', 'channel' => 'text', 'weight' => 0]],
        ] as $records) {
            try { $this->composer($records); $this->fail('Expected graph rejection.'); }
            catch (InvalidArgumentException $error) { $this->assertNotSame('', $error->getMessage()); }
        }
    }

    public function testMalformedFragmentsCannotCoercePolicyOrMeasurements(): void
    {
        foreach ([['weight' => '1'], ['weight' => INF], ['weight' => -1], ['blocking' => 'false'],
            ['deadline_ms' => -1], ['priority' => 1.5], ['dependencies' => ['a', 'a']],
            ['blocking' => true, 'fallback' => ['payload' => 'approved']], ['unknown' => true]] as $bad) {
            try { new ResponseFragment(['id' => 'a', 'channel' => 'text', ...$bad]); $this->fail('Expected invalid fragment.'); }
            catch (InvalidArgumentException $error) { $this->assertNotSame('', $error->getMessage()); }
        }
    }

    public function testPriorityIsStableAndPayloadSnapshotIsDetached(): void
    {
        $payload = ['value' => 0.0];
        $reference = &$payload['value'];
        $composer = $this->composer([
            ['id' => 'a', 'channel' => 'text', 'priority' => 2],
            ['id' => 'b', 'channel' => 'text', 'priority' => 3],
            ['id' => 'c', 'channel' => 'text', 'priority' => 2],
        ])->resolve('a', $payload)->resolve('b', false)->resolve('c', null);
        $reference = 7;
        $release = $composer->prepare(0);
        $this->assertSame(['b', 'a', 'c'], Arr::make($release['fragments'])->map(static fn (array $row) => $row['id'])->values()->val());
        $this->assertSame(['value' => 0.0], $release['fragments'][1]['payload']);
    }

    public function testCompletedFragmentCannotBeRewritten(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']])->resolve('a', 'original');
        $this->expectException(LogicException::class);
        $composer->resolve('a', 'replacement');
    }

    public function testClockCannotMoveBackwards(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']]);
        $composer->prepare(10);
        $this->expectException(InvalidArgumentException::class);
        $composer->prepare(9);
    }

    public function testJsonObjectKeyOrderDoesNotChangePendingDeliveryIdentity(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']])
            ->resolve('a', ['z' => 0.0, 'a' => ['second' => false, 'first' => 'value']]);
        $release = $composer->prepare(1);
        $reverse = function (mixed $value) use (&$reverse): mixed {
            if (!is_array($value)) { return $value; }
            $result = Arr::make($value)->map($reverse)->val();
            if (!array_is_list($result)) { krsort($result); }
            return $result;
        };
        $restored = ResponseComposer::restore($reverse($composer->snapshot()));
        $this->assertSame($release, $restored->prepare(2));
        $receipts = $this->receipts($release);
        $this->assertTrue($restored->acknowledge($release['id'], $receipts));
        $this->assertFalse($restored->acknowledge($release['id'], $reverse($receipts)));
    }

    public function testExplicitNullAndCoerciblePolicyValuesAreRejected(): void
    {
        foreach ([null, false, '0.5', -0.1, 1.1, INF] as $bad) {
            try { new ResponsePolicy(['threshold' => $bad]); $this->fail('Expected strict policy rejection.'); }
            catch (InvalidArgumentException $error) { $this->assertNotSame('', $error->getMessage()); }
        }
    }

    public function testDeliveryIdentityDoesNotDependOnPhpSerializationPrecision(): void
    {
        $previous = ini_get('serialize_precision');
        try {
            ini_set('serialize_precision', '17');
            $composer = $this->composer([['id' => 'a', 'channel' => 'text', 'weight' => 0.3]])
                ->resolve('a', ['value' => 0.1]);
            $release = $composer->prepare(1);
            $checkpoint = $composer->snapshot();
            ini_set('serialize_precision', '-1');
            $restored = ResponseComposer::restore($checkpoint);
            $this->assertSame($release, $restored->prepare(2));
        } finally {
            ini_set('serialize_precision', $previous);
        }
    }

    public function testHistoryLimitRetainsCapacityForTerminalReconciliation(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']]);
        for ($index = 0; $index < 9998; ++$index) { $composer->progress('a', 0.0); }
        $release = $composer->resolve('a', 'content')->prepare(1);
        $this->assertTrue($composer->acknowledge($release['id'], $this->receipts($release)));
        $composer->cancel('event_capacity');
        $restored = ResponseComposer::restore($composer->snapshot());
        $this->assertTrue($restored->state()['cancelled']);
        $this->assertSame('delivered', $restored->state()['fragments']['a']['status']);
    }

    public function testOverflowingWeightSumIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->composer([
            ['id' => 'a', 'channel' => 'text', 'weight' => PHP_FLOAT_MAX],
            ['id' => 'b', 'channel' => 'text', 'weight' => PHP_FLOAT_MAX],
        ]);
    }

    public function testUnknownConfidenceStaysUnknownAndInvalidConfidenceDoesNotMutateState(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']]);
        foreach (['1', false, INF, -0.1, 1.1] as $bad) {
            try { $composer->resolve('a', 'content', $bad); $this->fail('Expected confidence rejection.'); }
            catch (InvalidArgumentException $error) { $this->assertSame([], $composer->snapshot()['events']); }
        }
        $composer->resolve('a', 'content');
        $this->assertNull($composer->state()['confidence']);
        $this->assertNull($composer->prepare(1)['fragments'][0]['confidence']);
    }

    public function testMalformedEventsAreRejectedWithoutUndefinedFieldReads(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']]);
        foreach ([['type' => 'resolve', 'id' => 'a'], ['type' => 'prepare', 'now_ms' => '1'],
            ['type' => 'invented'], ['type' => 'prepare', 'now_ms' => 1, 'allowed' => true]] as $event) {
            $record = $composer->snapshot();
            $record['events'][] = $event;
            try { ResponseComposer::restore($record); $this->fail('Expected malformed checkpoint rejection.'); }
            catch (InvalidArgumentException $error) { $this->assertNotSame('', $error->getMessage()); }
        }
    }

    public function testRestoreRejectsMalformedOrImpossibleHistory(): void
    {
        $composer = $this->composer([['id' => 'a', 'channel' => 'text']]);
        $snapshot = $composer->snapshot();
        $snapshot['events'][] = ['type' => 'acknowledge', 'id' => 'not-prepared', 'receipts' => []];
        $this->expectException(InvalidArgumentException::class);
        ResponseComposer::restore($snapshot);
    }
}
