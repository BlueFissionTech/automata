<?php

namespace BlueFission\Automata\Learning;

use BlueFission\{Arr, Num, Str};
use BlueFission\Automata\Support\RecordSnapshot;
use LogicException;
use OverflowException;
use RuntimeException;
use Throwable;

/**
 * Local-filesystem reference: immutable complete generations behind a stable lock.
 *
 * Ordinary DevElation Disk flushes the destination before writing and has no CAS
 * publication boundary. Native filesystem operations are isolated here to write,
 * flush and atomically rename a new generation without replacing existing files.
 * A private host-owned directory and cooperative local filesystem are required.
 * This qualifies process interruption, not directory-fsync/power-loss durability,
 * hostile rollback of the directory, network filesystems or distributed leases.
 * Other IBundleStore implementations may provide stronger transaction guarantees.
 */
class FileBundleStore implements IBundleStore
{
    private readonly string $directory;

    /** Storage limits belong to this reference adapter, not the general bundle API. */
    public function __construct(string $directory, private readonly int $maximumBytes = 8388608,
        private readonly int $maximumGenerations = 10000, private readonly int $lockTimeoutMs = 5000)
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved) || $maximumBytes < 1 || $maximumGenerations < 1 || $lockTimeoutMs < 1) {
            throw new RuntimeException('Bundle storage requires an existing directory and positive limits.');
        }
        $this->directory = $resolved;
    }

    /** A shared lock prevents observing a writer's partially published generation chain. */
    public function load(): ?array
    {
        return $this->locked(LOCK_SH, function (): ?array {
            $head = $this->head();
            return $head === null ? null : $head['state'];
        });
    }

    /** Compare the authoritative state, prepare complete bytes, then publish once. */
    public function compareAndSwap(?string $expectedDigest, array $replacement): void
    {
        $replacement = RecordSnapshot::copy($replacement);
        $this->locked(LOCK_EX, function () use ($expectedDigest, $replacement): void {
            $head = $this->head();
            $actual = $head === null ? null : RecordSnapshot::fingerprint($head['state']);
            if ($actual !== $expectedDigest) { throw new LogicException('Bundle publication lost its expected store revision.'); }
            $sequence = ($head['sequence'] ?? 0) + 1;
            if ($sequence > $this->maximumGenerations) { throw new OverflowException('Bundle generation retention exhausted.'); }
            $payload = ['schema_version' => 1, 'sequence' => $sequence, 'previous' => $head['fingerprint'] ?? null, 'state' => $replacement];
            $record = [...$payload, 'fingerprint' => RecordSnapshot::fingerprint($payload)];
            $bytes = json_encode($record, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            if (strlen($bytes) > $this->maximumBytes) { throw new OverflowException('Bundle state exceeds the configured file limit.'); }
            // serialize_precision is process configuration. Never publish bytes
            // that round a score and invalidate their own typed fingerprint.
            // Reject locally instead of changing the host's global PHP settings.
            $decoded = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
            if (RecordSnapshot::fingerprint($decoded) !== RecordSnapshot::fingerprint($record)) {
                throw new RuntimeException('Bundle JSON encoding loses scalar precision; use round-trip-safe serialize_precision.');
            }
            $temporary = tempnam($this->directory, 'prepare-');
            if ($temporary === false || dirname($temporary) !== $this->directory) { throw new RuntimeException('Cannot prepare a bundle in its own directory.'); }
            $handle = null;
            try {
                $handle = fopen($temporary, 'wb');
                if ($handle === false) { throw new RuntimeException('Cannot open prepared bundle.'); }
                $offset = 0; $length = strlen($bytes);
                while ($offset < $length) {
                    $written = fwrite($handle, substr($bytes, $offset));
                    if ($written === false || $written === 0) { throw new RuntimeException('Incomplete bundle preparation.'); }
                    $offset += $written;
                }
                if (!fflush($handle) || !fsync($handle)) { throw new RuntimeException('Cannot flush prepared bundle bytes.'); }
                fclose($handle); $handle = null;
                $target = $this->path(sprintf('bundle-%020d.json', $sequence));
                if (file_exists($target) || is_link($target)) { throw new LogicException('Bundle generation already exists.'); }
                $this->publish($temporary, $target);
            } finally {
                if (is_resource($handle)) { fclose($handle); }
                // No recursive deletion: only the temporary file created above.
                // Process termination may leave it; readers ignore prepared files.
                if (is_file($temporary)) { unlink($temporary); }
            }
        });
    }

    /** Atomic boundary, isolated so host filesystem adapters and fault probes can specialize it. */
    protected function publish(string $prepared, string $committed): void
    {
        if (!rename($prepared, $committed)) { throw new RuntimeException('Bundle publication could not be acknowledged.'); }
    }

    /**
     * Verify the complete retained generation chain, not only the newest filename.
     * Prepared files are never candidates. A missing middle record, partial commit
     * or checksum mismatch fails closed rather than silently selecting old state.
     */
    private function head(): ?array
    {
        $paths = glob($this->path('bundle-*.json'));
        if ($paths === false) { throw new RuntimeException('Cannot enumerate committed bundles.'); }
        if (Arr::size($paths) > $this->maximumGenerations) { throw new OverflowException('Bundle store exceeds configured retention.'); }
        $paths = Arr::make($paths)->sort()->val();
        $previous = null; $sequence = 0; $head = null;
        foreach ($paths as $path) {
            ++$sequence;
            if (basename($path) !== sprintf('bundle-%020d.json', $sequence) || is_link($path)
                || !is_file($path) || filesize($path) > $this->maximumBytes) { throw new RuntimeException('Invalid committed bundle generation.'); }
            $bytes = file_get_contents($path);
            if ($bytes === false || strlen($bytes) > $this->maximumBytes) { throw new RuntimeException('Cannot read complete committed bundle.'); }
            try { $record = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR); }
            catch (Throwable $error) { throw new RuntimeException('Committed bundle is not complete JSON.', 0, $error); }
            if (!Arr::is($record)) { throw new RuntimeException('Malformed committed bundle.'); }
            $fingerprint = $record['fingerprint'] ?? null;
            unset($record['fingerprint']);
            if (($record['schema_version'] ?? null) !== 1 || ($record['sequence'] ?? null) !== $sequence
                || !Arr::hasKey($record, 'previous') || $record['previous'] !== $previous || !Arr::is($record['state'] ?? null)
                || Arr::make($record)->keys()->diff(['schema_version', 'sequence', 'previous', 'state'])->val() !== []
                || !Str::is($fingerprint) || $fingerprint !== RecordSnapshot::fingerprint($record)) {
                throw new RuntimeException('Committed bundle integrity chain is invalid.');
            }
            $previous = $fingerprint;
            $head = [...$record, 'fingerprint' => $fingerprint];
        }
        return $head;
    }

    /** Bound lock waiting and release it on every ordinary exception path. */
    private function locked(int $mode, callable $operation): mixed
    {
        $path = $this->path('bundle.lock');
        if (is_link($path)) { throw new RuntimeException('Bundle lock must not be a symbolic link.'); }
        $handle = fopen($path, 'c+b');
        if ($handle === false) { throw new RuntimeException('Cannot open bundle lock.'); }
        $locked = false;
        $started = hrtime(true);
        try {
            while (!($locked = flock($handle, $mode | LOCK_NB))) {
                $elapsed = Num::make(hrtime(true))->subtract($started)->divide(1000000)->val();
                if ($elapsed >= $this->lockTimeoutMs) { throw new RuntimeException('Bundle store lock timed out.'); }
                usleep(10000);
            }
            return $operation();
        } finally {
            if ($locked) { flock($handle, LOCK_UN); }
            fclose($handle);
        }
    }

    /** Filenames are generated internally; artifact keys never become store paths. */
    private function path(string $name): string { return $this->directory . DIRECTORY_SEPARATOR . $name; }
}
