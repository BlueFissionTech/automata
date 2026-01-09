<?php

declare(strict_types=1);

use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\Abs2Memory;
use BlueFission\DevElation;

require __DIR__ . '/../../../vendor/autoload.php';

// Seeded randomness for reproducibility.
$seed = 123;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--seed=')) {
        $seed = (int)substr($arg, strlen('--seed='));
    }
}

mt_srand($seed);

// Activate DevElation's behavioral hooks for the session (no-op if unused).
DevElation::up();

$memory = new Abs2Memory();

// Minimal disaster-response "episodes" stored as working-memory nodes.
$episodes = [
    'delivery_hospital_a_clear_roads' => [
        'location'     => 'Hospital-A',
        'asset_type'   => 'truck',
        'risk_level'   => 1,
        'success'      => true,
        'delay_min'    => 10,
    ],
    'delivery_hospital_a_flooded' => [
        'location'     => 'Hospital-A',
        'asset_type'   => 'boat',
        'risk_level'   => 3,
        'success'      => true,
        'delay_min'    => 40,
    ],
    'delivery_shelter_b_backlog' => [
        'location'     => 'Shelter-B',
        'asset_type'   => 'truck',
        'risk_level'   => 2,
        'success'      => false,
        'delay_min'    => 90,
    ],
];

foreach ($episodes as $label => $data) {
    $ctx = new Context();
    foreach ($data as $k => $v) {
        $ctx->set($k, $v);
    }
    $ctx->set('timestamp', time());

    $memory->addMemory($label, $ctx);
}

// Associate similar experiences by location.
$memory->associate('delivery_hospital_a_clear_roads', 'delivery_hospital_a_flooded', 1.0);
$memory->associate('delivery_hospital_a_flooded', 'delivery_shelter_b_backlog', 2.0);

// New request: hospital A, roads partially flooded, truck considered but risky.
$query = (new Context())
    ->set('location', 'Hospital-A')
    ->set('asset_type', 'truck')
    ->set('risk_level', 2)
    ->set('timestamp', time());

$similar = $memory->recallSimilar($query, 0.1);

$log = [
    'seed'        => $seed,
    'query'       => $query->all(),
    'similar'     => array_map(
        fn(array $entry) => [
            'context'    => $entry['context']->all(),
            'similarity' => $entry['similarity'],
        ],
        $similar
    ),
    'path_example' => $memory->shortestAssociation(
        'delivery_hospital_a_clear_roads',
        'delivery_shelter_b_backlog'
    ),
];

echo json_encode($log, JSON_PRETTY_PRINT) . PHP_EOL;
