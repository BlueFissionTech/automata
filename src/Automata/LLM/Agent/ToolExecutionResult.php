<?php

namespace BlueFission\Automata\LLM\Agent;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Net\HTTP;
use BlueFission\Obj;

class ToolExecutionResult extends Obj
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_VALIDATION_ERROR = 'validation_error';
    public const STATUS_PERMISSION_DENIED = 'permission_denied';
    public const STATUS_CIRCUIT_OPEN = 'circuit_open';
    public const STATUS_UNAVAILABLE = 'unavailable';

    /**
     * Store normalized tool execution state, payload, error, and metadata.
     */
    public function __construct(string $status, mixed $payload = null, ?array $error = null, array $meta = [])
    {
        parent::__construct();
        $this->replaceFields([
            'status' => $status,
            'payload' => $payload,
            'error' => $error,
            'meta' => $meta,
        ]);
    }

    /**
     * Create a successful tool execution result.
     */
    public static function success(mixed $payload = null, array $meta = []): self
    {
        return new self(self::STATUS_SUCCESS, $payload, null, $meta);
    }

    /**
     * Create a structured error result that the model can reason from.
     */
    public static function error(string $code, string $message, array $details = [], array $meta = [], string $status = self::STATUS_ERROR): self
    {
        return new self($status, null, [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ], $meta);
    }

    /**
     * Create a schema validation error result.
     */
    public static function validationError(string $message, array $details = [], array $meta = []): self
    {
        return self::error('invalid_input', $message, $details, $meta, self::STATUS_VALIDATION_ERROR);
    }

    /**
     * Determine whether execution succeeded.
     */
    public function ok(): bool
    {
        return $this->status() === self::STATUS_SUCCESS;
    }

    /**
     * Return the normalized status string.
     */
    public function status(): string
    {
        return (string)$this->field('status');
    }

    /**
     * Return the execution payload.
     */
    public function payload(): mixed
    {
        return $this->field('payload');
    }

    /**
     * Return structured error details when execution failed.
     */
    public function errorDetails(): ?array
    {
        $error = $this->field('error');

        return Arr::is($error) ? $error : null;
    }

    /**
     * Return execution metadata.
     */
    public function meta(): array
    {
        return Arr::make($this->field('meta') ?? [])->toArray();
    }

    /**
     * Return the result as an array after DevElation filters run.
     */
    public function toArray(): array
    {
        $result = [
            'status' => $this->status(),
            'ok' => $this->ok(),
            'payload' => $this->payload(),
            'error' => $this->errorDetails(),
            'meta' => $this->meta(),
        ];

        return Dev::apply('automata.llm.agent.tool_execution_result.to_array', $result);
    }

    /**
     * Encode the result for storage or transport.
     */
    public function toJson(int $flags = 0): string
    {
        return (string)HTTP::jsonEncode($this->toArray());
    }

    /**
     * Render the result as JSON for logging.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Normalize raw tool output into a success result.
     */
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

    /**
     * Return a copy with a rewritten payload and merged metadata.
     */
    public function withPayload(mixed $payload, array $meta = []): self
    {
        return new self(
            $this->status(),
            $payload,
            $this->errorDetails(),
            ToolDefinition::mergeConfig($this->meta(), $meta)
        );
    }

    /**
     * Replace Obj-backed result fields without dropping null or empty payloads.
     */
    protected function replaceFields(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->_data[$key] = $value;
        }
    }
}
