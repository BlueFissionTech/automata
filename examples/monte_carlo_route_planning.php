<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

automata_example_require(
    'Automata/MonteCarlo/Search.php',
    'Automata/MonteCarlo/RandomSource.php',
    'Automata/MonteCarlo/ActionStatistics.php',
    'Automata/MonteCarlo/SearchResult.php'
);

use BlueFission\Automata\MonteCarlo\Search;
use BlueFission\Automata\MonteCarlo\RandomSource;
use BlueFission\Func;

$search = new Search(30, 21);

$result = $search->evaluate(['hold', 'reroute', 'airlift'], new Func(function (string $action, RandomSource $random): float {
    $routes = [
        'hold' => ['reward' => 2.0, 'variance' => 1],
        'reroute' => ['reward' => 6.0, 'variance' => 2],
        'airlift' => ['reward' => 5.0, 'variance' => 3],
    ];

    $profile = $routes[$action];
    $noise = $random->nextInt(-$profile['variance'], $profile['variance']);

    return $profile['reward'] + $noise;
}));

echo json_encode([
    'best_action' => $result->getBestAction(),
    'statistics' => $result->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
