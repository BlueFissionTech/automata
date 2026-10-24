<?php

namespace BlueFission\Automata\Support;

use Fiber;
use Throwable;

/** Evaluate pure final checks without lending them the caller's suspension point. */
final class SynchronousCallback
{
    public static function evaluate(callable $callback): array
    {
        // A nested Fiber lets us detect a forbidden yield before the caller can
        // dispatch. Never resume it. This is not a sandbox for untrusted code:
        // host-supplied clocks and guards must still be side-effect free.
        $fiber = new Fiber($callback);
        try {
            $fiber->start();
            return $fiber->isTerminated()
                ? ['status' => 'returned', 'value' => $fiber->getReturn()]
                : ['status' => 'suspended'];
        } catch (Throwable $error) {
            return ['status' => 'exception', 'error_type' => $error::class];
        }
    }
}
