<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;

class ToolExecutionResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_VALIDATION_ERROR = 'validation_error';
    public const STATUS_PERMISSION_DENIED = 'permission_denied';
    public const STATUS_CIRCUIT_OPEN = 'circuit_open';
    public const STATUS_UNAVAILABLE = 'unavailable';

    protected string $status;
    protected mixed $payload;
    protected ?array $error;
    protected array $meta;

    public function __construct(string $status, mixed $payload = null, ?array $error = null, array $meta = [])
    {
        $this->status = $status;
        $this->payload = $payload;
        $this->error = $error;
        $this->meta = $meta;
    }

    public static function success(mixed $payload = null, array $meta = []): self
    {
        return new self(self::STATUS_SUCCESS, $payload, null, $meta);
    }

    public static function error(string $code, string $message, array $details = [], array $meta = [], string $status = self::STATUS_ERROR): self
    {
        return new self($status, null, [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $meta);
    }

    public static function validationError(string $message, array $details = [], array $meta = []): self
    {
        return self::error('invalid_input', $message, $details, $meta, self::STATUS_VALIDATION_ERROR);
    }

    public function ok(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function errorDetails(): ?array
    {
        return $this->error;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        $result = [
            'status' => $this->status,
            'ok' => $this->ok(),
            'payload' => $this->payload,
            'error' => $this->error,
            'meta' => $this->meta,
        ];

        return Dev::apply('automata.llm.agent.tool_execution_result.to_array', $result);
    }

    public function toJson(int $flags = 0): string
    {
        return (string)json_encode($this->toArray(), $flags);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public static function fromToolOutput(mixed $output, array $meta = []): self
    {
        if ($output instanceof self) {
            return $output;
        }

        if (Arr::is($output)) {
            return self::success($output, $meta);
        }

        return self::success(['output' => (string)$output], $meta);
    }
}
