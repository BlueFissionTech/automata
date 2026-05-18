<?php

namespace BlueFission\Automata\LLM\Agent\Governance;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\DevElation as Dev;

class GovernanceDecision
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_PENDING = 'pending';
    public const STATUS_STEERED = 'steered';

    protected array $data = [];

    /**
     * Store a normalized decision from policy or human review.
     */
    public function __construct(array $data = [])
    {
        $this->data = ToolDefinition::mergeConfig($this->defaults(), $data);
    }

    /**
     * Normalize booleans, strings, arrays, or decision objects into a decision.
     */
    public static function from(mixed $decision): self
    {
        if ($decision instanceof self) {
            return $decision;
        }

        if ($decision === true) {
            return self::approved();
        }

        if ($decision === false) {
            return self::denied('Rejected by reviewer.');
        }

        if (Arr::is($decision)) {
            return new self($decision);
        }

        if ($decision) {
            return new self(['status' => (string)$decision]);
        }

        return self::pending('No decision was provided.');
    }

    /**
     * Create an approval decision.
     */
    public static function approved(string $message = '', array $payload = []): self
    {
        return new self([
            'status' => self::STATUS_APPROVED,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * Create a denial decision.
     */
    public static function denied(string $message = '', array $payload = []): self
    {
        return new self([
            'status' => self::STATUS_DENIED,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * Create a pending-review decision.
     */
    public static function pending(string $message = '', array $payload = []): self
    {
        return new self([
            'status' => self::STATUS_PENDING,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * Create an approval that also carries deterministic steering changes.
     */
    public static function steered(array $payload, string $message = ''): self
    {
        return new self([
            'status' => self::STATUS_STEERED,
            'message' => $message,
            'payload' => $payload,
        ]);
    }

    /**
     * Return a copy with additional decision metadata.
     */
    public function with(array $data): self
    {
        return new self(ToolDefinition::mergeConfig($this->data, $data));
    }

    /**
     * Return the normalized decision status.
     */
    public function status(): string
    {
        return (string)$this->data['status'];
    }

    /**
     * Determine whether the observed call may execute.
     */
    public function allowsExecution(): bool
    {
        return $this->isApproved() || $this->isSteered();
    }

    /**
     * Determine whether the decision approved the call without changes.
     */
    public function isApproved(): bool
    {
        return $this->status() === self::STATUS_APPROVED;
    }

    /**
     * Determine whether the decision denied the call.
     */
    public function isDenied(): bool
    {
        return $this->status() === self::STATUS_DENIED;
    }

    /**
     * Determine whether the call still requires human review.
     */
    public function isPending(): bool
    {
        return $this->status() === self::STATUS_PENDING;
    }

    /**
     * Determine whether review changed the request before approval.
     */
    public function isSteered(): bool
    {
        return $this->status() === self::STATUS_STEERED;
    }

    /**
     * Return reviewer or policy notes.
     */
    public function message(): string
    {
        return (string)$this->data['message'];
    }

    /**
     * Return steering payload or decision details.
     */
    public function payload(): array
    {
        return Arr::make($this->data['payload'] ?? [])->toArray();
    }

    /**
     * Return normalized decision storage data.
     */
    public function toArray(): array
    {
        return Dev::apply('automata.llm.agent.governance.decision.to_array', $this->data);
    }

    /**
     * Return default decision fields.
     */
    protected function defaults(): array
    {
        return [
            'status' => self::STATUS_PENDING,
            'message' => '',
            'payload' => [],
            'review_id' => null,
            'reviewer' => null,
        ];
    }
}
