<?php

namespace BlueFission\Tests\Automata\Adapters;

use BlueFission\Automata\Adapters\StoreAdapter;
use BlueFission\Data\Storage\Local;
use PHPUnit\Framework\TestCase;

class StoreAdapterTest extends TestCase
{
    public function testStoreAdapterWrapsLocalStorageWithoutChangingStoreMechanics(): void
    {
        $store = new Local();
        $store->activate();

        $adapter = new StoreAdapter($store);
        $adapter
            ->contents([
                'domain' => [
                    'name' => 'regional-grid',
                    'state' => 'stable',
                ],
            ])
            ->write()
            ->read();

        $snapshot = $adapter->snapshot();

        $this->assertSame('regional-grid', $snapshot['domain']['name']);
        $this->assertSame('stable', $snapshot['domain']['state']);
    }
}
