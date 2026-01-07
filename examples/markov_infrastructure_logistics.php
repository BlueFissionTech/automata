<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use BlueFission\Automata\Markov\DiscreteMarkov;

/**
 * Infrastructure Markov example for disaster logistics.
 *
 * Models road segment state evolution {open, degraded, closed}
 * under a simple transition matrix and shows how distributions
 * change over a few time steps.
 */

$model = new DiscreteMarkov();

$roadMatrixMild = [
    'open' => [
        'open' => 0.8,
        'degraded' => 0.15,
        'closed' => 0.05,
    ],
    'degraded' => [
        'open' => 0.3,
        'degraded' => 0.5,
        'closed' => 0.2,
    ],
    'closed' => [
        'open' => 0.1,
        'degraded' => 0.3,
        'closed' => 0.6,
    ],
];

$distribution = ['open' => 1.0, 'degraded' => 0.0, 'closed' => 0.0];

echo "=== Infrastructure Markov (Roads) ===\n\n";
echo "Initial: " . json_encode($distribution) . "\n";

for ($t = 1; $t <= 3; $t++) {
    $distribution = $model->step($distribution, $roadMatrixMild);
    echo "After step $t: " . json_encode($distribution) . "\n";
}

echo "\nExample completed.\n";
