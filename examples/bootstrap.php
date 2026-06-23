<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once dirname(__DIR__) . '/tests/bootstrap.php';

function automata_example_require(string ...$relativePaths): void
{
    $srcRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

    foreach ($relativePaths as $relativePath) {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        require_once $srcRoot . $normalized;
    }
}
