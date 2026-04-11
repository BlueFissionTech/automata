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

use BlueFission\Automata\MonteCarlo\TreeSearch;

$search = new TreeSearch(60, 1.1, 3, 8);

$result = $search->search(
    ['depth' => 0, 'path' => ''],
    function (array $state): array {
        if (($state['depth'] ?? 0) >= 2) {
            return [];
        }

        if (($state['path'] ?? '') === '') {
            return ['slow_safe', 'fast_risky'];
        }

        return ['finish'];
    },
    function (array $state, string $action): array {
        return [
            'depth' => ($state['depth'] ?? 0) + 1,
            'path' => trim(($state['path'] ?? '') . '/' . $action, '/'),
        ];
    },
    function (array $state): bool {
        return ($state['depth'] ?? 0) >= 2;
    },
    function (array $state): float {
        return match ($state['path'] ?? '') {
            'slow_safe/finish' => 9.0,
            'fast_risky/finish' => 3.0,
            default => 0.0,
        };
    }
);

echo json_encode($result->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
