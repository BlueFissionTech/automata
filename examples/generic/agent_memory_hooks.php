<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\Context;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\Memory\InMemoryEventStore;
use BlueFission\Automata\LLM\Agent\Memory\StaticMemoryInjector;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\Net\HTTP;

final class MemoryExampleClient implements IClient
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

    private function reply(string $message): Reply
    {
        $reply = new Reply();
        $reply->addMessage($message, true);

        return $reply;
    }
}

$agent = new Agent(new MemoryExampleClient());
$agent->setTemplate('{input}');
$agent->enableMemory(
    new InMemoryEventStore(),
    new StaticMemoryInjector('profile: prefer local tools', 'project: automata memory hooks'),
    'memory-example-session',
    new Abs2Memory()
);

$agent->session()->allow('read');
$agent->session()->remember('profile/preference', new Context(['preference' => 'local tools']));
$agent->execute('Summarize agent memory scope.');
$agent->stopMemorySession(['reason' => 'example complete']);

print HTTP::jsonEncode($agent->memoryEvents()) . "\n";
