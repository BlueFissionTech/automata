<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

automata_example_require(
    'Automata/MonteCarlo/TreeSearch.php',
    'Automata/MonteCarlo/TreeSearchNode.php',
    'Automata/MonteCarlo/TreeSearchResult.php',
    'Automata/MonteCarlo/RandomSource.php'
);

use BlueFission\Automata\MonteCarlo\TreeSearch;
use BlueFission\Func;

$search = new TreeSearch(60, 1.1, 3, 8);

$result = $search->search(
    ['depth' => 0, 'path' => ''],
    new Func(function (array $state): array {
        if (($state['depth'] ?? 0) >= 2) {
            return [];
        }

        if (($state['path'] ?? '') === '') {
            return ['slow_safe', 'fast_risky'];
        }

        return ['finish'];
    }),
    new Func(function (array $state, string $action): array {
        return [
            'depth' => ($state['depth'] ?? 0) + 1,
            'path' => trim(($state['path'] ?? '') . '/' . $action, '/'),
        ];
    }),
    new Func(function (array $state): bool {
        return ($state['depth'] ?? 0) >= 2;
    }),
    new Func(function (array $state): float {
        return match ($state['path'] ?? '') {
            'slow_safe/finish' => 9.0,
            'fast_risky/finish' => 3.0,
            default => 0.0,
        };
    })
);

echo json_encode($result->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
