<?php

namespace BlueFission\Automata\LLM\Agent\Memory;

use BlueFission\Data\Storage\Disk;

class FileMemoryEventStore extends StorageMemoryEventStore
{
    /**
     * Create a file-backed event store through DevElation Disk storage.
     */
    public function __construct(string $path)
    {
        parent::__construct(new Disk([
            'location' => dirname($path),
            'name' => basename($path),
        ]));
    }
}
