<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent;
use BlueFission\Automata\LLM\Agent\AgentHook;
use BlueFission\Automata\LLM\Agent\ToolCatalog;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Tools\BaseTool;
use BlueFission\DevElation as Dev;
use BlueFission\Net\HTTP;

final class ContractExampleClient implements IClient
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

final class ContractExampleSearchTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'local_search';
        $this->description = 'Searches local indexed facts.';
    }

    public function execute($input): string
    {
        return HTTP::jsonEncode([
            'query' => Arr::make($input)->toArray()['query'] ?? '',
            'matches' => ['agent capability documentation'],
        ]);
    }
}

final class ContractExampleCriticalTool extends BaseTool
{
    public function __construct()
    {
        $this->name = 'critical_write';
        $this->description = 'Demonstrates approval-gated execution.';
    }

    public function execute($input): string
    {
        return 'approved:' . (string)$input;
    }
}

Dev::up();

Dev::action(AgentHook::SESSION_START, function (string $agent): void {
    print "session: {$agent}\n";
});

Dev::action(AgentHook::USER_PROMPT_SUBMIT, function (mixed $input, mixed ...$payload): void {
    print "prompt submitted: {$input}\n";
});

Dev::action(AgentHook::PERMISSION_REQUEST, function (string $tool, string $permission, mixed ...$payload): void {
    print "permission requested: {$tool} ({$permission})\n";
});

Dev::action(AgentHook::PRE_TOOL_USE, function (string $tool, int $attempt, mixed ...$payload): void {
    print "before tool: {$tool} attempt {$attempt}\n";
});

Dev::action(AgentHook::POST_TOOL_USE, function (string $status, mixed ...$payload): void {
    print "after tool status: {$status}\n";
});

Dev::action(AgentHook::TURN_STOP, function (mixed ...$payload): void {
    print "turn stopped\n";
});

$agent = new Agent(new ContractExampleClient());
$agent->registerTool('local_search', new ContractExampleSearchTool(), [
    'purpose' => 'Search local project facts before asking a remote service.',
    'category' => 'retrieval',
    'tags' => ['local', 'read', 'agent'],
    'groups' => ['agent.lifecycle'],
    'taxonomy' => [
        'domain' => ['agent'],
        'lifecycle' => ['user_prompt_submit', 'pre_tool_use', 'post_tool_use'],
        'risk' => ['read_only'],
    ],
    'input_schema' => [
        'type' => 'object',
        'required' => ['query'],
        'properties' => [
            'query' => ['type' => 'string'],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'matches' => ['type' => 'array'],
        ],
    ],
    'parallel_safe' => true,
]);

$agent->registerTool('critical_write', new ContractExampleCriticalTool(), [
    'purpose' => 'Demonstrate a permission-gated write tool contract.',
    'permission' => ToolDefinition::PERMISSION_CRITICAL,
    'requires_approval' => true,
    'tags' => ['write'],
    'groups' => ['agent.lifecycle'],
]);

$catalogSlice = $agent->toolDefinitions([
    ToolCatalog::FILTER_TAGS => ['agent'],
    ToolCatalog::FILTER_TAXONOMY => [
        'risk' => ['read_only'],
    ],
]);

print HTTP::jsonEncode($catalogSlice) . "\n";

$result = $agent->callTool('local_search', 'agent hooks');

print $result->toJson() . "\n";

$denied = $agent->callTool('critical_write', 'change-request');

print $denied->toJson() . "\n";

$agent->execute('List the available agent lifecycle hooks.');
