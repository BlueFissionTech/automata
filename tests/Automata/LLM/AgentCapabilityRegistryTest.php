<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent\Capability\AutonomyDecision;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyGrant;
use BlueFission\Automata\LLM\Agent\Capability\AutonomyPacket;
use BlueFission\Automata\LLM\Agent\Capability\CapabilityDefinition;
use BlueFission\Automata\LLM\Agent\Capability\CapabilityRegistry;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AgentCapabilityRegistryTest extends TestCase
{
    public function testRegistryResolvesExactDescriptiveCapabilityVersions(): void
    {
        $registry = $this->registry();
        $definition = $registry->definition('evidence.read', '1.0');

        $this->assertInstanceOf(CapabilityDefinition::class, $definition);
        $this->assertSame('automata', $definition->owner());
        $this->assertSame(CapabilityDefinition::AVAILABILITY_AVAILABLE, $definition->availability());
        $this->assertTrue($definition->available());
        $this->assertSame(['query'], $definition->toArray()['inputs']);
        $this->assertArrayNotHasKey('handler', $definition->toArray());
        $this->assertNull($registry->definition('evidence.read', '2.0'));
    }

    public function testRegistryRejectsIncompleteCapabilityIdentity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CapabilityRegistry())->register([
            'id' => 'evidence.read',
            'version' => '',
            'owner' => 'automata',
        ]);
    }

    public function testApprovedExactGrantReturnsTypedAuthorizationDecision(): void
    {
        $packet = $this->packet();
        $decision = $packet->authorize(
            'evidence.read',
            '1.0',
            $this->registry(),
            new DateTimeImmutable('2026-08-30T00:00:00Z')
        );

        $this->assertTrue($decision->allowed());
        $this->assertSame(AutonomyDecision::CODE_ALLOWED, $decision->code());
        $this->assertSame(1, $decision->limits()['max_calls']);
        $this->assertSame('trace-capability-1', $decision->toArray()['trace_id']);
        $this->assertSame('registry-fixture', $decision->evidence()[0]['source']);
        $this->assertSame('operator-approval', $decision->evidence()[1]['source']);
    }

    public function testUnknownUnavailableAndUnrequestedCapabilitiesFailClosed(): void
    {
        $registry = $this->registry();
        $packet = $this->packet();

        $unknown = $packet->authorize('evidence.write', '1.0', $registry);
        $unavailable = $packet->authorize('provider.generate', '1.0', $registry);
        $unrequested = $packet->authorize('review.approve', '1.0', $registry);

        $this->assertFalse($unknown->allowed());
        $this->assertFalse($unknown->toArray()['allowed']);
        $this->assertSame(AutonomyDecision::CODE_UNKNOWN_CAPABILITY, $unknown->code());
        $this->assertSame(AutonomyDecision::CODE_CAPABILITY_UNAVAILABLE, $unavailable->code());
        $this->assertSame(AutonomyDecision::CODE_CAPABILITY_NOT_REQUESTED, $unrequested->code());
    }

    public function testApprovalExpiryAndRevocationStatesFailClosed(): void
    {
        $registry = $this->registry();

        $pending = $this->packet(['approval_state' => AutonomyPacket::APPROVAL_PENDING]);
        $expired = $this->packet(['expires_at' => '2026-08-29T23:59:59Z']);
        $revoked = $this->packet(['revoked' => true, 'revocation_reason' => 'operator_stop']);

        $this->assertSame(
            AutonomyDecision::CODE_APPROVAL_REQUIRED,
            $pending->authorize('evidence.read', '1.0', $registry)->code()
        );
        $this->assertSame(
            AutonomyDecision::CODE_PACKET_EXPIRED,
            $expired->authorize('evidence.read', '1.0', $registry, new DateTimeImmutable('2026-08-30T00:00:00Z'))->code()
        );
        $this->assertSame(
            AutonomyDecision::CODE_PACKET_REVOKED,
            $revoked->authorize('evidence.read', '1.0', $registry)->code()
        );
    }

    public function testGrantExpiryRevocationAndSubjectMismatchFailClosed(): void
    {
        $registry = $this->registry();
        $now = new DateTimeImmutable('2026-08-30T00:00:00Z');

        $expired = $this->packet([], ['expires_at' => '2026-08-29T23:59:59Z']);
        $revoked = $this->packet([], ['revoked' => true]);
        $mismatched = $this->packet([], ['subject_id' => 'agent-child']);

        $this->assertSame(
            AutonomyDecision::CODE_GRANT_EXPIRED,
            $expired->authorize('evidence.read', '1.0', $registry, $now)->code()
        );
        $this->assertSame(
            AutonomyDecision::CODE_GRANT_REVOKED,
            $revoked->authorize('evidence.read', '1.0', $registry, $now)->code()
        );
        $this->assertSame(
            AutonomyDecision::CODE_SUBJECT_MISMATCH,
            $mismatched->authorize('evidence.read', '1.0', $registry, $now)->code()
        );
    }

    public function testGrantDoesNotTransferToAnotherSubject(): void
    {
        $grant = new AutonomyGrant([
            'capability_id' => 'evidence.read',
            'capability_version' => '1.0',
            'subject_id' => 'agent-specialist',
            'granted' => true,
        ]);

        $this->assertTrue($grant->permits('evidence.read', '1.0', 'agent-specialist'));
        $this->assertFalse($grant->permits('evidence.read', '1.0', 'agent-child'));
        $this->assertFalse($grant->permits('evidence.read', '2.0', 'agent-specialist'));
        $this->assertFalse($grant->transferable());
        $this->assertFalse($grant->toArray()['transferable']);
    }

    private function registry(): CapabilityRegistry
    {
        return new CapabilityRegistry([
            [
                'id' => 'evidence.read',
                'version' => '1.0',
                'owner' => 'automata',
                'description' => 'Read bounded evidence references.',
                'inputs' => ['query'],
                'outputs' => ['evidence_references'],
                'constraints' => ['locator_only' => true],
                'risk' => ['level' => 'low'],
                'evidence' => [['source' => 'registry-fixture']],
                'availability' => CapabilityDefinition::AVAILABILITY_AVAILABLE,
            ],
            [
                'id' => 'evidence.read',
                'version' => '1.1',
                'owner' => 'automata',
                'availability' => CapabilityDefinition::AVAILABILITY_AVAILABLE,
            ],
            [
                'id' => 'provider.generate',
                'version' => '1.0',
                'owner' => 'host',
                'availability' => CapabilityDefinition::AVAILABILITY_UNAVAILABLE,
            ],
            [
                'id' => 'review.approve',
                'version' => '1.0',
                'owner' => 'host',
                'availability' => CapabilityDefinition::AVAILABILITY_AVAILABLE,
            ],
        ]);
    }

    private function packet(array $packetOverrides = [], array $grantOverrides = []): AutonomyPacket
    {
        return new AutonomyPacket(array_replace_recursive([
            'id' => 'autonomy-1',
            'subject_id' => 'agent-specialist',
            'requested_capabilities' => [
                ['id' => 'evidence.read', 'version' => '1.0'],
                ['id' => 'evidence.read', 'version' => '1.1'],
                ['id' => 'provider.generate', 'version' => '1.0'],
            ],
            'grants' => [array_replace_recursive([
                'capability_id' => 'evidence.read',
                'capability_version' => '1.0',
                'subject_id' => 'agent-specialist',
                'granted' => true,
                'limits' => ['max_calls' => 1],
                'evidence' => [['source' => 'operator-approval']],
            ], $grantOverrides)],
            'limits' => ['max_calls' => 3],
            'approval_state' => AutonomyPacket::APPROVAL_APPROVED,
            'expires_at' => '2026-08-31T00:00:00Z',
            'correlation_id' => 'correlation-capability-1',
            'trace_id' => 'trace-capability-1',
        ], $packetOverrides));
    }
}
