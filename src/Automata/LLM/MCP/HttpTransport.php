<?php

namespace BlueFission\Automata\LLM\MCP;

use BlueFission\Connections\Curl;
use BlueFission\DevElation as Dev;

class HttpTransport implements IMCPTransport
{
    protected Curl $_curl;

    public function __construct(?Curl $curl = null)
    {
        $this->_curl = $curl ?? new Curl(['method' => 'post']);
    }

    public function send(array $server, array $payload): array
    {
        $server = Dev::apply('automata.llm.mcp.http.send.server', $server);
        $payload = Dev::apply('automata.llm.mcp.http.send.payload', $payload);

        $url = $server['url'] ?? '';
        $path = $server['path'] ?? '';
        $headers = $server['headers'] ?? [];
        $token = $server['token'] ?? '';

        if (!is_array($headers)) {
            $headers = [];
        }

        $target = rtrim((string)$url, '/');
        if ($path !== '') {
            $target .= '/' . ltrim((string)$path, '/');
        }

        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';

        $this->_curl->config('target', $target);
        $this->_curl->config('method', 'post');
        $this->_curl->config('headers', $headers);

        $this->_curl->open();
        $this->_curl->query($payload);
        $response = $this->_curl->result();
        $this->_curl->close();

        $result = $response;
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $result = $decoded;
            } else {
                $result = ['raw' => $response];
            }
        }

        $result = Dev::apply('automata.llm.mcp.http.send.result', $result);
        Dev::do('automata.llm.mcp.http.send.complete', ['result' => $result]);

        return $result;
    }
}
