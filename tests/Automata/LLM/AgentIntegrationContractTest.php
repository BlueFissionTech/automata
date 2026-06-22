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
        $this->assertTrue($contract->supports(AgentIntegrationContract::FEATURE_LANE_PRESSURE));
        $this->assertTrue($contract->supports(AgentIntegrationContract::FEATURE_CAPABILITY_VOCABULARY));
        $this->assertSame(
            'Deterministic tool definitions, catalog retrieval, execution, and structured results.',
            $contract->feature(AgentIntegrationContract::FEATURE_TOOLS)['summary']
        );
    }

    public function testFeatureSelectionUsesCallerOwnedFeatureIds(): void
    {
        $contract = AgentIntegrationContract::standard();
        $features = $contract->features([
            AgentIntegrationContract::FEATURE_TOOLS,
            AgentIntegrationContract::FEATURE_HOLOSCENE,
            AgentIntegrationContract::FEATURE_TELEMETRY,
        ]);

        $this->assertArrayHasKey(AgentIntegrationContract::FEATURE_TOOLS, $features);
        $this->assertArrayHasKey(AgentIntegrationContract::FEATURE_HOLOSCENE, $features);
        $this->assertArrayHasKey(AgentIntegrationContract::FEATURE_TELEMETRY, $features);
        $this->assertArrayNotHasKey(AgentIntegrationContract::FEATURE_HOOKS, $features);
    }

    public function testBindingTemplateMapsNeutralConstructsToAutomataFeatures(): void
    {
        $contract = AgentIntegrationContract::standard();
        $template = $contract->bindingTemplate();

        $this->assertSame(AgentIntegrationContract::FEATURE_TOOLS, $template['tool']['feature']);
        $this->assertSame(AgentIntegrationContract::FEATURE_HOLOSCENE, $template['holoscene']['feature']);
        $this->assertSame(AgentIntegrationContract::FEATURE_ORCHESTRATION, $template['orchestration']['feature']);
        $this->assertSame(AgentIntegrationContract::FEATURE_MCP, $template['mcp']['feature']);
        $this->assertSame(AgentIntegrationContract::FEATURE_CAPABILITY_VOCABULARY, $template['capability']['feature']);
        $this->assertSame($template['tool'], $contract->bindings(AgentIntegrationContract::TEMPLATE_TOOL));
    }

    public function testContractTemplateDefinesAdapterOwnedUpstreamShape(): void
    {
        $template = AgentIntegrationContract::standard()->contractTemplate();

        $this->assertSame('family.adapter.contract', $template['name']);
        $this->assertSame('adapter-to-upstream-runtime', $template['direction']);
        $this->assertContains('feature_bindings', $template['required_fields']);
        $this->assertStringContainsString('adapter libraries own syntax', $template['boundary']);
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

        $this->assertStringContainsString('"version":"1.2.0"', $json);
        $this->assertStringContainsString('"agent.tool_contracts"', $json);
        $this->assertStringContainsString('"agent.holoscene_comprehension"', $json);
        $this->assertStringContainsString('"agent.lane_pressure"', $json);
        $this->assertStringContainsString('"agent.capability_vocabulary"', $json);
        $this->assertStringContainsString('"contract_template"', $json);
        $this->assertStringNotContainsString('"jenss"', $json);
        $this->assertStringNotContainsString('"jenerator"', $json);
        $this->assertStringNotContainsString('"linqr"', $json);
        $this->assertStringNotContainsString('"chainlinq"', $json);
    }

    public function testHolosceneFeatureAdvertisesComprehensionClasses(): void
    {
        $feature = AgentIntegrationContract::standard()
            ->feature(AgentIntegrationContract::FEATURE_HOLOSCENE);

        $this->assertContains(Holoscene::class, $feature['classes']);
        $this->assertContains('reader.to_holoscene', $feature['constructs']);
        $this->assertContains('holoscene_snapshot', $feature['outputs']);
    }

    public function testCapabilityVocabularyDocumentsNeutralRuntimeTerms(): void
    {
        $contract = AgentIntegrationContract::standard();
        $vocabulary = $contract->capabilityVocabulary();

        $this->assertArrayHasKey('goal', $vocabulary);
        $this->assertArrayHasKey('statement', $vocabulary);
        $this->assertArrayHasKey('feedback', $vocabulary);
        $this->assertArrayHasKey('domain_evaluation', $vocabulary);
        $this->assertArrayHasKey('lane_pressure', $vocabulary);
        $this->assertSame(
            AgentIntegrationContract::FEATURE_HOLOSCENE,
            $contract->capabilityVocabulary('statement')['feature']
        );
        $this->assertContains('subject', $contract->capabilityVocabulary('statement')['stable_fields']);
        $this->assertContains('corrected_value', $contract->capabilityVocabulary('feedback')['stable_fields']);
        $this->assertContains('unmet_conditions', $contract->capabilityVocabulary('domain_evaluation')['stable_fields']);
        $this->assertStringContainsString(
            'provider internal architecture',
            $contract->capabilityVocabulary('lane_pressure')['constraints'][0]
        );
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
