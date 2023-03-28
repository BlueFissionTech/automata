<?php
namespace BlueFission\Bot\NaturalLanguage;

class SyntaxTreeWalker {
    protected $tree;

    public function __construct($tree) {
        $this->tree = $tree;
    }

    public function walk() {
        $results = [
            'subject' => null,
            'operator' => null,
            'objects' => []
        ];
        $this->traverse($this->tree, $results);
        return $results;
    }

    protected function traverse($node, &$results) {
        if (isset($node['type']) && $node['type'] === 'T_ALIAS' && $node['value'] === 'me') {
            $results['subject'] = 'app';
        } elseif (isset($node['type']) && $node['type'] === 'T_OPERATOR') {
            $results['operator'] = $node['value'];
        } elseif (isset($node['type']) && $node['type'] === 'T_ENTITY') {
            $results['objects'][] = $node['value'];
        }

        if (isset($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->traverse($child, $results);
            }
        }
    }
}