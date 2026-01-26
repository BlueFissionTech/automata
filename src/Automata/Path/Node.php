<?php
namespace BlueFission\Automata\Path;

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
        if (is_array($dataOrEdges) && $edges === [] && $meta === []) {
            $edges = $dataOrEdges;
        } else {
            $data = $dataOrEdges;
        }

        parent::__construct($id, $data, $edges, $meta);
    }
}
