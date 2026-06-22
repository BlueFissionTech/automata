<?php
namespace BlueFission\Automata\Path;

use BlueFission\Arr;
use BlueFission\Data\Graph\Node as BaseNode;

/**
 * Node
 *
 * Wrapper for the shared BlueFission Data Graph node implementation.
 */
class Node extends BaseNode
{
    public function __construct(string $id, $dataOrEdges = null, array $edges = [], array $meta = [])
    {
        $data = null;
        if (Arr::is($dataOrEdges) && Arr::isEmpty($edges) && Arr::isEmpty($meta)) {
            $edges = $dataOrEdges;
        } else {
            $data = $dataOrEdges;
        }

        parent::__construct($id, $data, $edges, $meta);
    }
}
