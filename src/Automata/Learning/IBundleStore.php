<?php

namespace BlueFission\Automata\Learning;

/**
 * Atomic publication boundary for one learning scope; implementations own durability.
 *
 * Reads must return a complete, detached, authoritative state or null. Publication
 * must compare the current state's RecordSnapshot fingerprint (null means empty)
 * and replace the whole state atomically, or reject the operation. A thrown write
 * may have committed; a subsequent authoritative load must resolve that outcome.
 * No asynchronous write may complete after that recovery read. Implementations
 * own access control, corruption detection, capacity and writer coordination.
 */
interface IBundleStore
{
    /** Read a complete committed state; never expose a partially prepared record. */
    public function load(): ?array;

    /** Publish all compatible bindings and receipts together under an exact prior digest. */
    public function compareAndSwap(?string $expectedDigest, array $replacement): void;
}
