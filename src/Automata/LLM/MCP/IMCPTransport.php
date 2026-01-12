<?php

namespace BlueFission\Automata\LLM\MCP;

interface IMCPTransport
{
    /**
     * Send a payload to the MCP server and return the decoded response.
     *
     * @param array $server Server configuration data.
     * @param array $payload JSON-RPC payload.
     * @return array
     */
    public function send(array $server, array $payload): array;
}
