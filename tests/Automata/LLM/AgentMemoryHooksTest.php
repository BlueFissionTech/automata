<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Memory\FileMemoryEventStore;
use BlueFission\Automata\LLM\Agent\Memory\InMemoryEventStore;
use BlueFission\Automata\LLM\Agent\Memory\MemoryEvent;
use BlueFission\Automata\LLM\Agent\Memory\StaticMemoryInjector;
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
            MemoryEvent::SESSION_START,
            MemoryEvent::PRE_TOOL_USE,
            MemoryEvent::POST_TOOL_USE,
            MemoryEvent::STOP,
        ], array_column($events, 'event'));
        $this->assertSame([1, 2, 3, 4], array_column($events, 'sequence'));
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
        $this->assertSame(MemoryEvent::SESSION_START, $events[0]['event']);
        $this->assertSame(MemoryEvent::USER_PROMPT_SUBMIT, $events[1]['event']);
        $this->assertStringContainsString('profile: prefer local tools', $events[1]['payload']['prompt']);
    }

    public function testMemoryDisabledByDefaultDoesNotRecordEvents(): void
    {
        $agent = new Agent(new MemoryHookClientStub());
        $agent->registerTool('memory_echo', new MemoryHookEchoTool());

        $agent->callTool('memory_echo', 'hello');

        $this->assertSame([], $agent->memoryEvents());
    }

    public function testFileMemoryStorePersistsJsonLines(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'automata-memory-hooks-test.jsonl';
        if (is_file($path)) {
            unlink($path);
        }

        $store = new FileMemoryEventStore($path);
        $store->append(new MemoryEvent(MemoryEvent::SESSION_START, ['ok' => true], [
            'session_id' => 'file-session',
            'task_id' => 'task-file',
            'sequence' => 1,
        ]));

        $events = $store->events('file-session');
        $store->clear();

        $this->assertCount(1, $events);
        $this->assertSame('task-file', $events[0]['task_id']);
        $this->assertSame(MemoryEvent::SESSION_START, $events[0]['event']);
    }
}
