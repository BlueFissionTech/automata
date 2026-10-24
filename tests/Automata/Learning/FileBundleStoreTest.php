<?php

namespace BlueFission\Tests\Automata\Learning;

use BlueFission\Automata\Learning\FileBundleStore;
use BlueFission\Automata\Support\RecordSnapshot;
use LogicException;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/** Private local directories exercise real immutable-file publication and CAS. */
final class FileBundleStoreTest extends TestCase
{
    private string $directory;
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/automata-bundles-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }
    protected function tearDown(): void
    {
        // Only this test's flat, freshly created private directory is removed.
        foreach (glob($this->directory . '/*') as $path) { if (is_file($path)) { unlink($path); } }
        rmdir($this->directory);
    }
    public function testFreshInstanceReadsWholeStateAndRejectsStaleWriter(): void
    {
        $store = new FileBundleStore($this->directory);
        self::assertNull($store->load());
        $first = ['revision' => 0, 'float' => 1.0, 'bindings' => ['workflow' => '1', 'model' => '1']];
        $store->compareAndSwap(null, $first);
        self::assertSame($first, (new FileBundleStore($this->directory))->load());
        $next = ['revision' => 1, 'float' => 1.0, 'bindings' => ['workflow' => '2', 'model' => '2']];
        $store->compareAndSwap(RecordSnapshot::fingerprint($first), $next);
        try { (new FileBundleStore($this->directory))->compareAndSwap(RecordSnapshot::fingerprint($first), $first); self::fail('Stale writer published.'); }
        catch (LogicException) { self::assertSame($next, $store->load()); }
    }
    public function testUnpublishedPartialRecordDoesNotReplaceCommittedState(): void
    {
        $store = new FileBundleStore($this->directory);
        $store->compareAndSwap(null, ['revision' => 0]);
        file_put_contents($this->directory . '/prepare-interrupted', '{"partial":');
        self::assertSame(['revision' => 0], (new FileBundleStore($this->directory))->load());
    }
    public function testCorruptCommittedRecordFailsClosed(): void
    {
        $store = new FileBundleStore($this->directory);
        $store->compareAndSwap(null, ['revision' => 0]);
        file_put_contents(glob($this->directory . '/bundle-*.json')[0], '{"partial":');
        $this->expectException(RuntimeException::class);
        $store->load();
    }
    public function testBrokenGenerationChainFailsClosed(): void
    {
        $store = new FileBundleStore($this->directory);
        $one = ['revision' => 0]; $two = ['revision' => 1];
        $store->compareAndSwap(null, $one);
        $store->compareAndSwap(RecordSnapshot::fingerprint($one), $two);
        unlink(glob($this->directory . '/bundle-*.json')[0]);
        $this->expectException(RuntimeException::class);
        $store->load();
    }

    /** A lossy runtime JSON setting must never publish an unreadable generation. */
    public function testLossyFloatEncodingIsRejectedBeforePublication(): void
    {
        $store = new FileBundleStore($this->directory);
        $first = ['revision' => 0];
        $store->compareAndSwap(null, $first);
        $precision = ini_get('serialize_precision');
        try {
            ini_set('serialize_precision', '3');
            try { $store->compareAndSwap(RecordSnapshot::fingerprint($first), ['score' => 0.123456789012345]); self::fail('Lossy state published.'); }
            catch (RuntimeException) { self::assertSame($first, $store->load()); }
        } finally { ini_set('serialize_precision', $precision); }
        self::assertSame($first, (new FileBundleStore($this->directory))->load());
    }
}
