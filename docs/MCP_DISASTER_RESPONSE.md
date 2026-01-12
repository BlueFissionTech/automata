# MCP Disaster Response Integration

This document describes how MCP tooling fits into the Disaster Response Logistics scenario. The Automata MCP layer lets an agent query shared resources, tools, and metadata from external MCP servers without hardcoding connectors.

## Scenario Use

In the Coastal County Flood Event scenario, MCP can expose:
- Live resource snapshots (roads, hospitals, shelters, comms)
- Suggested dispatch actions from external services
- Domain vocabularies or templates for structured reports

These requests are routed through the MCP client so the agent can discover or invoke tools at runtime.

## Example Payloads

### Read a disaster resource snapshot

```
{
  "server": "logistics",
  "method": "resources/read",
  "params": {
    "uri": "disaster://coastal-county/dispatch",
    "seed": "SEED_A",
    "time_step": 3
  }
}
```

### List MCP tools

```
{
  "server": "logistics",
  "method": "tools/list"
}
```

## Using the MCP Tools

1) Register a server with `MCPRegisterServer`:

```
{
  "name": "logistics",
  "url": "http://localhost:3333",
  "options": { "path": "mcp" }
}
```

2) Use `MCPDiscovery` to list resources or tools.
3) Use `MCPResource` to read a resource by URI.
4) Use `MCPToolCall` or `MCPRequest` to invoke a tool or send custom requests.

## Test Coverage

`tests/Automata/LLM/MCPDisasterResponseTest.php` exercises a disaster-response style MCP request using a stub transport.

Run:

```
vendor/bin/phpunit --do-not-cache-result
```
