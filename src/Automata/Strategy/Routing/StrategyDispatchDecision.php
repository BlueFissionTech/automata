<?php

namespace BlueFission\Automata\Strategy\Routing;

use BlueFission\Automata\Support\RecordSnapshot;
use BlueFission\Flag;

/** Final immutable value prevents user callbacks between allow and invocation. */
final class StrategyDispatchDecision
{
    public readonly bool $allowed;
    public function __construct(mixed $allowed, public readonly string $code = 'dispatch_guard_denied')
    {
        // A typed bool parameter alone coerces truthy strings in weak-mode PHP.
        if (!Flag::isBool($allowed)) { throw new \InvalidArgumentException('Dispatch decision must be a boolean.'); }
        RecordSnapshot::identifier($code, 'dispatch decision code');
        $this->allowed = $allowed;
    }
}
