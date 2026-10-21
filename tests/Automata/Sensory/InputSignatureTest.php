<?php

namespace BlueFission\Tests\Automata\Sensory;

use BlueFission\Automata\Sensory\InputSignature;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Signature projection is host policy; input instances and traces are not keys. */
final class InputSignatureTest extends TestCase
{
    public function testEquivalentProjectedFeaturesShareLearningDespiteMapOrdering(): void
    {
        $first = new InputSignature('features', '1', ['mime' => ['text/plain', 'video/example'], 'urgent' => false]);
        $reordered = new InputSignature('features', '1', ['urgent' => false, 'mime' => ['text/plain', 'video/example']]);
        $this->assertSame($first->key(), $reordered->key());
        $this->assertSame($first->toArray(), InputSignature::fromArray($first->toArray())->toArray());
    }

    public function testNewPassReplacesFeaturesWithoutMutatingItsPredecessor(): void
    {
        $initial = new InputSignature('features', '1', ['mime' => ['text/plain'], 'signal' => null]);
        $observed = $initial->withFeatures(['mime' => ['text/plain'], 'signal' => false]);
        $this->assertNotSame($initial->key(), $observed->key());
        $this->assertNull($initial->features()['signal']);
        $this->assertFalse($observed->features()['signal']);
        $this->assertNotSame($observed->key(), $initial->withFeatures(['signal' => 0])->key());
        $this->assertNotSame($initial->key(), (new InputSignature('features', '2', $initial->features()))->key());
        $this->assertNotSame($initial->key(), (new InputSignature('another-domain', '1', $initial->features()))->key());
    }

    public function testFeatureVocabularyAndModalitiesAreNotHardCoded(): void
    {
        $signature = new InputSignature('host.telemetry', '8', ['lidar' => ['density' => 0.25],
            'audio' => ['silence' => true], 'third_pass' => ['new_evidence' => 'unknown']]);
        $changed = $signature->features();
        $changed['lidar']['density'] = 0.8;
        $this->assertSame(0.25, $signature->features()['lidar']['density']);
        $this->assertSame([], $signature->withFeatures([])->features());
        $this->assertNotSame($signature->key(), $signature->withFeatures($changed)->key());
    }

    public function testMalformedOrLiveProjectionDataCannotBecomeAKey(): void
    {
        foreach ([['schema_version' => 99], ['schema_version' => 1, 'projection' => [], 'features' => []]] as $record) {
            try { InputSignature::fromArray($record); $this->fail('Malformed signature must be rejected.'); }
            catch (InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
        foreach ([INF, new \stdClass(), static fn () => 'live'] as $value) {
            try { new InputSignature('features', '1', ['value' => $value]); $this->fail('Features must be finite data.'); }
            catch (InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }
}
