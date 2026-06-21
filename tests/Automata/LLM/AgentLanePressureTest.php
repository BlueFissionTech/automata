<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent\Lanes\AgentLane;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureManager;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureProfile;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Tools\LanePressure;
use PHPUnit\Framework\TestCase;

class AgentLanePressureTest extends TestCase
{
    public function testStandardLaneDescriptorsAreProviderNeutral(): void
    {
        $lanes = AgentLane::standard();

        $this->assertArrayHasKey(AgentLane::SEMANTIC, $lanes);
        $this->assertArrayHasKey(AgentLane::OPERATIONAL, $lanes);
        $this->assertArrayHasKey(AgentLane::EXECUTION, $lanes);
        $this->assertStringContainsString('Intent', $lanes[AgentLane::SEMANTIC]->purpose());
        $this->assertNotContains('codex', array_map('strtolower', $lanes[AgentLane::EXECUTION]->responsibilities()));
    }

    public function testPressureManagerFindsDominantLaneAndRecommendations(): void
    {
        $manager = LanePressureManager::standard();

        $assessment = $manager->assess([
            AgentLane::SEMANTIC => [
                'context_load' => 0.82,
                'ambiguity' => 0.3,
            ],
            AgentLane::OPERATIONAL => [
                'policy_conflict' => 0.2,
            ],
            AgentLane::EXECUTION => [
                'tool_failures' => 0.55,
            ],
        ], ['task_id' => 'lane-test']);

        $this->assertSame(AgentLane::SEMANTIC, $assessment['dominant_lane']);
        $this->assertSame(LanePressureManager::LEVEL_HIGH, $assessment['overall_level']);
        $this->assertSame('context_load', $assessment['lanes'][AgentLane::SEMANTIC]['dominant_signal']['name']);
        $this->assertStringContainsString('clarify intent', $assessment['recommendations'][0]);
        $this->assertSame('lane-test', $assessment['context']['task_id']);
    }

    public function testPressureManagerBlocksCriticalOperationalPressure(): void
    {
        $assessment = LanePressureManager::standard()->assess([
            AgentLane::OPERATIONAL => [
                'approval_pending' => true,
            ],
        ]);

        $this->assertSame(AgentLane::OPERATIONAL, $assessment['dominant_lane']);
        $this->assertSame(LanePressureManager::LEVEL_CRITICAL, $assessment['overall_level']);
        $this->assertStringContainsString('human review', $assessment['recommendations'][0]);
    }

    public function testLongHorizonProfileMapsReadinessGapsToLanes(): void
    {
        $metrics = LanePressureProfile::longHorizonTask([
            'spec' => true,
            'source_map' => 0.25,
            'durable_memory' => true,
            'runbook' => false,
            'milestones' => 0.5,
            'audit_log' => true,
            'verification' => 0.8,
            'observability' => false,
            'isolated_workspace' => true,
            'repair_loop' => true,
            'rollback_plan' => false,
            'local_governance' => 0.7,
        ]);

        $assessment = LanePressureManager::standard()->assess($metrics);

        $this->assertSame(0.75, $metrics[AgentLane::SEMANTIC]['source_map_gap']['value']);
        $this->assertSame(0.85, $metrics[AgentLane::OPERATIONAL]['runbook_gap']['value']);
        $this->assertSame(0.75, $metrics[AgentLane::EXECUTION]['observability_gap']['value']);
        $this->assertSame(AgentLane::OPERATIONAL, $assessment['dominant_lane']);
        $this->assertStringContainsString(
            'runbook',
            $assessment['lanes'][AgentLane::OPERATIONAL]['recommendations'][0]
        );
    }

    public function testLanePressureToolReturnsAssessmentJson(): void
    {
        $tool = new LanePressure();
        $result = json_decode($tool->execute(json_encode([
            'metrics' => [
                AgentLane::EXECUTION => [
                    'tool_failures' => 0.91,
                ],
            ],
            'context' => [
                'task_id' => 'tool-test',
            ],
        ])), true);

        $this->assertSame(AgentLane::EXECUTION, $result['dominant_lane']);
        $this->assertSame(LanePressureManager::LEVEL_CRITICAL, $result['overall_level']);
        $this->assertSame('tool-test', $result['context']['task_id']);
    }

    public function testLanePressureToolCanBeRegisteredAsReadOnlyAgentTool(): void
    {
        $definition = ToolDefinition::fromTool('lane_pressure', new LanePressure(), [
            'category' => 'agent',
            'permission' => ToolDefinition::PERMISSION_READ,
            'tags' => ['agent', 'pressure', 'lanes'],
            'parallel_safe' => true,
        ]);

        $this->assertSame('lane_pressure', $definition->name());
        $this->assertSame(ToolDefinition::PERMISSION_READ, $definition->permission());
        $this->assertTrue($definition->parallelSafe());
        $this->assertTrue($definition->hasAnyTag(['lanes']));
    }
}
