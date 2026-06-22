<?php

namespace BlueFission\Tests\Automata\Security;

use BlueFission\Automata\Security\HerdSignalContract;
use PHPUnit\Framework\TestCase;

class HerdSignalContractTest extends TestCase
{
    public function testSignalNormalizesScoresSensitivityAndRetention(): void
    {
        $signal = HerdSignalContract::signal([
            'id' => 'signal.device',
            'type' => 'device_reputation',
            'score' => 1.7,
            'weight' => -2,
            'confidence' => 0.8,
            'sensitivity' => 'sensitive',
            'retention_days' => -4,
            'evidence' => ['device_ref' => 'device:hash'],
        ]);

        $this->assertSame('signal.device', $signal['id']);
        $this->assertSame('device_reputation', $signal['type']);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame(0.0, $signal['weight']);
        $this->assertSame(0.8, $signal['confidence']);
        $this->assertSame(HerdSignalContract::SENSITIVITY_SENSITIVE, $signal['sensitivity']);
        $this->assertSame(0, $signal['retention_days']);
        $this->assertSame('device:hash', $signal['evidence']['device_ref']);
    }

    public function testResultMapsScoreToChallengeAndRestrictDecisions(): void
    {
        $signals = [
            HerdSignalContract::signal([
                'id' => 'signal.geo',
                'type' => 'geo_velocity',
                'score' => 0.72,
                'confidence' => 0.9,
            ]),
        ];

        $result = HerdSignalContract::result(0.72, $signals, [
            'subject_ref' => 'user:123',
            'session_ref' => 'session:abc',
            'action' => 'change_payout_account',
        ]);

        $this->assertSame(HerdSignalContract::DECISION_RESTRICT, $result['decision']);
        $this->assertFalse($result['challenge']);
        $this->assertTrue($result['restrict']);
        $this->assertSame('user:123', $result['context']['subject_ref']);
        $this->assertSame('signal.geo', $result['reasons'][0]['signal_id']);
    }

    public function testThresholdOverridesAndGuidanceAreAvailableForFixtures(): void
    {
        $this->assertSame(
            HerdSignalContract::DECISION_CHALLENGE,
            HerdSignalContract::decisionFor(0.2, [HerdSignalContract::DECISION_CHALLENGE => 0.1])
        );

        $this->assertArrayHasKey(HerdSignalContract::SENSITIVITY_SENSITIVE, HerdSignalContract::retentionRules());
        $this->assertArrayHasKey(HerdSignalContract::DECISION_DENY, HerdSignalContract::challengeMap());
        $this->assertArrayHasKey('minimize_raw_identifiers', HerdSignalContract::privacyGuidance());
    }
}
