<?php

namespace BlueFission\Automata\LLM\MCP;

use BlueFission\DevElation as Dev;

class ServerRegistry
{
    /** @var array<string, array> */
    protected array $_servers = [];

    public function register(string $name, string $url, array $options = []): void
    {
        $name = Dev::apply('automata.llm.mcp.registry.register.name', $name);
        $url = Dev::apply('automata.llm.mcp.registry.register.url', $url);
        $options = Dev::apply('automata.llm.mcp.registry.register.options', $options);

        $this->_servers[$name] = array_merge([
            'name' => $name,
            'url' => $url,
            'headers' => [],
        ], $options);

        Dev::do('automata.llm.mcp.registry.registered', [
            'name' => $name,
            'url' => $url,
            'options' => $options,
        ]);
    }

    public function remove(string $name): void
    {
        $name = Dev::apply('automata.llm.mcp.registry.remove.name', $name);
        unset($this->_servers[$name]);
        Dev::do('automata.llm.mcp.registry.removed', ['name' => $name]);
    }

    public function has(string $name): bool
    {
        $name = Dev::apply('automata.llm.mcp.registry.has.name', $name);
        return isset($this->_servers[$name]);
    }

    public function get(string $name): ?array
    {
        $name = Dev::apply('automata.llm.mcp.registry.get.name', $name);
        $server = $this->_servers[$name] ?? null;
        return Dev::apply('automata.llm.mcp.registry.get.server', $server);
    }

    /**
     * @return array<string, array>
     */
    public function all(): array
    {
        return Dev::apply('automata.llm.mcp.registry.all', $this->_servers);
    }
}
