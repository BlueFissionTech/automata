<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\Memory\FileMemoryEventStore;
use BlueFission\Automata\LLM\Agent\Memory\InMemoryEventStore;
use BlueFission\Automata\LLM\Agent\Memory\StaticMemoryInjector;
use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use PHPUnit\Framework\TestCase;

class MemoryHookClientStub implements IClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $reply = new Reply();
        $reply->addMessage('generated', true);
        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('completed', true);
        return $reply;
    }

    public function respond($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage('responded', true);
        return $reply;
    }
}

class MemoryHookEchoTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'memory_echo';
        $this->description = 'Echo for memory hook tests.';
    }

    public function execute($input): string
    {
        return (string)$input;
    }
}

class AgentMemoryHooksTest extends TestCase
{
    public function testMemoryStoreCapturesSessionAndToolLifecycleEvents(): void
    {
        $store = new InMemoryEventStore();
        $agent = new Agent(new MemoryHookClientStub());
        $agent->startTask('task-memory-1');
        $agent->enableMemory($store, null, 'session-memory-1');
        $agent->registerTool('memory_echo', new MemoryHookEchoTool());

        $agent->callTool('memory_echo', 'hello');
        $agent->stopMemorySession(['reason' => 'test complete']);

        $events = $store->events('session-memory-1');

        $this->assertSame([
            AgentHook::SESSION_START,
            AgentHook::PRE_TOOL_USE,
            AgentHook::POST_TOOL_USE,
            AgentHook::TURN_STOP,
        ], $this->column($events, 'event'));
        $this->assertSame([1, 2, 3, 4], $this->column($events, 'sequence'));
        $this->assertSame('task-memory-1', $events[0]['task_id']);
        $this->assertSame('memory_echo', $events[1]['payload']['tool']);
        $this->assertSame('success', $events[2]['payload']['result']['status']);
    }

    public function testMemoryInjectorAddsSessionAndPromptContext(): void
    {
        $store = new InMemoryEventStore();
        $agent = new Agent(new MemoryHookClientStub());
        $agent->setTemplate('{input}');
        $agent->enableMemory(
            $store,
            new StaticMemoryInjector('profile: prefer local tools', 'project: automata agent memory'),
            'session-inject'
        );

        $agent->execute('Summarize the task.');

        $this->assertStringContainsString('Memory context:', $agent->output());
        $this->assertStringContainsString('profile: prefer local tools', $agent->output());
        $this->assertStringContainsString('Relevant memory:', $agent->output());
        $this->assertStringContainsString('project: automata agent memory', $agent->output());

        $events = $agent->memoryEvents();
        $this->assertSame(AgentHook::SESSION_START, $events[0]['event']);
        $this->assertSame(AgentHook::USER_PROMPT_SUBMIT, $events[1]['event']);
        $this->assertStringContainsString('profile: prefer local tools', $events[1]['payload']['prompt']);
    }

    public function testMemoryDisabledByDefaultDoesNotRecordEvents(): void
    {
        $agent = new Agent(new MemoryHookClientStub());
        $agent->registerTool('memory_echo', new MemoryHookEchoTool());

        $agent->callTool('memory_echo', 'hello');

        $this->assertSame([], $agent->memoryEvents());
    }

    public function testFileMemoryStorePersistsThroughStorageAdapter(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'automata-memory-hooks-test-' . uniqid('', true) . '.json';

        $store = new FileMemoryEventStore($path);
        $store->clear();
        $store->append(new \BlueFission\Automata\LLM\Agent\Memory\MemoryEvent(AgentHook::SESSION_START, ['ok' => true], [
            'session_id' => 'file-session',
            'task_id' => 'task-file',
            'sequence' => 1,
        ]));

        $events = $store->events('file-session');
        $store->clear();

        $this->assertCount(1, $events);
        $this->assertSame('task-file', $events[0]['task_id']);
        $this->assertSame(AgentHook::SESSION_START, $events[0]['event']);
    }

    public function testAgentSessionScopesPermissionsAndWorkingMemory(): void
    {
        $agent = new Agent(new MemoryHookClientStub());
        $memory = new Abs2Memory();

        $agent->enableMemory(new InMemoryEventStore(), null, 'session-scope', $memory);
        $agent->session()->allow('read');
        $agent->session()->remember('preference', new Context(['value' => 'local tools']));

        $this->assertTrue($agent->session()->can('read'));
        $this->assertSame('session-scope', $agent->session()->id());
        $this->assertNotNull($agent->session()->recall('preference'));
    }

    protected function column(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = $row[$key] ?? null;
        }

        return $values;
    }
}
