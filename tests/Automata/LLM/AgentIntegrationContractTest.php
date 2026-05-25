<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentSession;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\Integration\AgentIntegrationContract;
use BlueFission\Automata\LLM\Agent\ToolCatalog;
use BlueFission\Automata\Comprehension\Holoscene;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\Obj;
use PHPUnit\Framework\TestCase;

class IntegrationContractClientStub implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        return $this->reply('generated');
    }

    public function complete($input, $config = []): Reply
    {
        return $this->reply('completed');
    }

    public function respond($input, $config = []): Reply
    {
        return $this->reply('responded');
    }

    protected function reply(string $message): Reply
    {
        $reply = new Reply();
        $reply->addMessage($message, true);

        return $reply;
    }
}

class IntegrationContractSceneStub
{
    public function stats(): array
    {
        return ['frame_count' => 1];
    }

    public function data(): array
    {
        return ['summary' => 'contract scene'];
    }
}

class AgentIntegrationContractTest extends TestCase
{
    public function testContractExportsStableFeatureIds(): void
    {
        $contract = AgentIntegrationContract::standard();

        $this->assertSame(AgentIntegrationContract::VERSION, $contract->version());
        $this->assertTrue($contract->supports(AgentIntegrationContract::FEATURE_TOOLS));
        $this->assertTrue($contract->supports(AgentIntegrationContract::FEATURE_TELEMETRY));
        $this->assertSame(
            'Deterministic tool definitions, catalog retrieval, execution, and structured results.',
            $contract->feature(AgentIntegrationContract::FEATURE_TOOLS)['summary']
        );
    }

    public function testConsumerFilteringKeepsLinqrFocusedOnQueryRelevantSurfaces(): void
    {
        $contract = AgentIntegrationContract::standard();
        $features = $contract->features(AgentIntegrationContract::CONSUMER_LINQR);

        $this->assertArrayHasKey(AgentIntegrationContract::FEATURE_TOOLS, $features);
        $this->assertArrayHasKey(AgentIntegrationContract::FEATURE_HOLOSCENE, $features);
        $this->assertArrayHasKey(AgentIntegrationContract::FEATURE_TELEMETRY, $features);
        $this->assertArrayNotHasKey(AgentIntegrationContract::FEATURE_HOOKS, $features);
    }

    public function testBindingsMapLanguageConstructsToAutomataFeatures(): void
    {
        $contract = AgentIntegrationContract::standard();
        $jenss = $contract->bindings(AgentIntegrationContract::CONSUMER_JENSS);
        $chainlinq = $contract->bindings(AgentIntegrationContract::CONSUMER_CHAINLINQ);

        $this->assertSame(AgentIntegrationContract::FEATURE_TOOLS, $jenss['tool']);
        $this->assertSame(AgentIntegrationContract::FEATURE_HOLOSCENE, $jenss['holoscene']);
        $this->assertSame(AgentIntegrationContract::FEATURE_ORCHESTRATION, $jenss['orchestrate']);
        $this->assertSame(AgentIntegrationContract::FEATURE_HOLOSCENE, $chainlinq['adapter.holoscene']);
        $this->assertSame(AgentIntegrationContract::FEATURE_MCP, $chainlinq['adapter.mcp']);
    }

    public function testLifecycleHooksAndCatalogFiltersAreDiscoverable(): void
    {
        $contract = AgentIntegrationContract::standard();

        $this->assertContains(AgentHook::SESSION_START, $contract->hooks());
        $this->assertContains(AgentHook::HUMAN_REVIEW_DECISION, $contract->hooks());
        $this->assertContains(ToolCatalog::FILTER_TAXONOMY, $contract->toolCatalogFilters());
        $this->assertContains(ToolCatalog::FILTER_PARALLEL_SAFE, $contract->toolCatalogFilters());
    }

    public function testContractJsonCanSeedConformanceFixtures(): void
    {
        $json = AgentIntegrationContract::standard()->toJson();

        $this->assertStringContainsString('"version":"1.0.0"', $json);
        $this->assertStringContainsString('"agent.tool_contracts"', $json);
        $this->assertStringContainsString('"agent.holoscene_comprehension"', $json);
        $this->assertStringContainsString('"query.tool_catalog"', $json);
    }

    public function testHolosceneFeatureAdvertisesComprehensionClasses(): void
    {
        $feature = AgentIntegrationContract::standard()
            ->feature(AgentIntegrationContract::FEATURE_HOLOSCENE);

        $this->assertContains(Holoscene::class, $feature['classes']);
        $this->assertContains('reader.to_holoscene', $feature['constructs']);
        $this->assertContains('holoscene_snapshot', $feature['outputs']);
    }

    public function testAgentUsesDevelationPrototypeCarrier(): void
    {
        $agent = new Agent(new IntegrationContractClientStub());

        $this->assertInstanceOf(Obj::class, $agent);
        $this->assertSame('agent', $agent->kind());
        $this->assertSame('llm-runtime', $agent->role());
        $this->assertSame('automata.llm.agent', $agent->scope());
        $this->assertSame('configurable', $agent->autonomy());
        $this->assertContains('answer-user-visible-tasks', $agent->goals());

        $agent->decide(['feature' => AgentIntegrationContract::FEATURE_TOOLS]);
        $snapshot = $agent->snapshot();

        $this->assertSame($agent->session()->id(), $snapshot['properties']['session_id']);
        $this->assertSame(
            AgentIntegrationContract::FEATURE_TOOLS,
            $snapshot['lastDecision']['feature']
        );
    }

    public function testAgentPrototypeTracksExternalSessionScope(): void
    {
        $agent = new Agent(new IntegrationContractClientStub());
        $session = new AgentSession('contract-session');

        $agent->useSession($session);

        $this->assertSame('contract-session', $agent->snapshot()['properties']['session_id']);
    }

    public function testAgentSessionScopesHolosceneEpisodes(): void
    {
        $agent = new Agent(new IntegrationContractClientStub());
        $holoscene = new Holoscene('contract-holoscene');

        $agent->useHoloscene($holoscene);
        $agent->session()->useWorkingMemory(new Abs2Memory());
        $agent->session()->addHolosceneEpisode('episode_contract', new IntegrationContractSceneStub());

        $snapshot = $agent->session()->holosceneSnapshot();

        $this->assertSame($holoscene, $agent->holoscene());
        $this->assertSame('contract-holoscene', $agent->snapshot()['properties']['holoscene_id']);
        $this->assertSame(1, $snapshot['sceneCount']);
        $this->assertSame('scene', $snapshot['domain_members']['episode_contract']['kind']);
    }
}
