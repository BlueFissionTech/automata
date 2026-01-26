<?php

namespace BlueFission\Tests\Automata\Anomaly;

use BlueFission\Automata\Anomaly\Fingerprint;
use BlueFission\Automata\Anomaly\Signature;
use PHPUnit\Framework\TestCase;

class FingerprintSignatureTest extends TestCase
{
    public function testFingerprintSimilarityAndSignatureMatch(): void
    {
        $fingerprintA = new Fingerprint([
            'features' => [1, 0],
            'tags' => ['device:alpha'],
        ]);

        $fingerprintB = new Fingerprint([
            'features' => [1, 0],
            'tags' => ['device:alpha'],
        ]);

        $fingerprintC = new Fingerprint([
            'features' => [0, 1],
            'tags' => ['device:beta'],
        ]);

        $this->assertGreaterThan(0.9, $fingerprintA->similarity($fingerprintB));
        $this->assertLessThan(0.5, $fingerprintA->similarity($fingerprintC));

        $signature = new Signature([
            'features' => [1, 0],
            'tags' => ['device:alpha'],
        ], 0.8);

        $this->assertTrue($signature->matches($fingerprintB));
    }
}
