<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\GameTheory\PayoffMatrix;

require __DIR__ . '/../../../vendor/autoload.php';

// Optional seed for future stochastic strategies (currently deterministic).
$seed = 123;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int)substr($arg, strlen('--seed='));
    }
}

mt_srand($seed);
DevElation::up();

/**
 * Two-player resource allocation game:
 *
 * Player 0: Logistics command
 * Player 1: Hospital network
 *
 * Each chooses:
 * - "Conservative": prioritize safer, slower routing.
 * - "Aggressive":   prioritize faster, riskier routing.
 *
 * Payoffs encode a balance between delay (negative) and risk (negative),
 * with slightly different preferences for each side.
 */
$matrix = new PayoffMatrix();

// (Logistics, Hospital) => [logistics_payoff, hospital_payoff]
$matrix->setPayoff(['Conservative', 'Conservative'], [8, 7]);  // safe but slower, generally acceptable
$matrix->setPayoff(['Conservative', 'Aggressive'],   [5, 9]);  // hospital pushes hard; logistics absorbs more risk
$matrix->setPayoff(['Aggressive',   'Conservative'], [9, 5]);  // logistics pushes speed; hospital bears more risk
$matrix->setPayoff(['Aggressive',   'Aggressive'],   [4, 4]);  // both push; risk and coordination issues

$profiles = [
    ['Conservative', 'Conservative'],
    ['Conservative', 'Aggressive'],
    ['Aggressive',   'Conservative'],
    ['Aggressive',   'Aggressive'],
];

$results = [];

foreach ($profiles as $profile) {
    $payoff = $matrix->getPayoff($profile);
    $results[] = [
        'actions' => [
            'logistics' => $profile[0],
            'hospital'  => $profile[1],
        ],
        'payoff' => [
            'logistics' => $payoff[0] ?? null,
            'hospital'  => $payoff[1] ?? null,
        ],
    ];
}

$log = [
    'seed'     => $seed,
    'profiles' => $results,
];

echo json_encode($log, JSON_PRETTY_PRINT) . PHP_EOL;

