<?php

declare(strict_types=1);

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        require $candidate;
        break;
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'BlueFission\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $candidate = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($candidate)) {
        require_once $candidate;
    }
});

use BlueFission\Automata\MonteCarlo\Search;
use BlueFission\Automata\MonteCarlo\RandomSource;

$search = new Search(30, 21);

$result = $search->evaluate(['hold', 'reroute', 'airlift'], function (string $action, RandomSource $random): float {
    $routes = [
        'hold' => ['reward' => 2.0, 'variance' => 1],
        'reroute' => ['reward' => 6.0, 'variance' => 2],
        'airlift' => ['reward' => 5.0, 'variance' => 3],
    ];

    $profile = $routes[$action];
    $noise = $random->nextInt(-$profile['variance'], $profile['variance']);

    return $profile['reward'] + $noise;
});

echo json_encode([
    'best_action' => $result->getBestAction(),
    'statistics' => $result->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
