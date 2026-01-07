<?php

declare(strict_types=1);

use BlueFission\DevElation;
use BlueFission\Automata\Normalization\NumericalScaler;
use BlueFission\Automata\Encoding\CategoricalEncoder;

require __DIR__ . '/../../../vendor/autoload.php';

DevElation::up();

// Generic tabular data: sensor readings from multiple stations.
$records = [
    ['station' => 'A', 'temp' => 20.5, 'humidity' => 60, 'type' => 'urban'],
    ['station' => 'B', 'temp' => 18.2, 'humidity' => 72, 'type' => 'rural'],
    ['station' => 'C', 'temp' => 25.1, 'humidity' => 65, 'type' => 'urban'],
];

// Extract numeric columns.
$temps    = array_column($records, 'temp');
$humidity = array_column($records, 'humidity');

$tempScaler    = new NumericalScaler();
$humidScaler   = new NumericalScaler();

$tempsScaled   = $tempScaler->fitTransform($temps);
$humidScaled   = $humidScaler->fitTransform($humidity);

// Categorical encoding for station type.
$types = array_column($records, 'type');

$typeEncoder = new CategoricalEncoder(true, 'UNKNOWN');
$typeEncoder->fit($types);
$typeVectors = $typeEncoder->transform($types);

$output = [];
foreach ($records as $i => $row) {
    $features = [
        $tempsScaled[$i],
        $humidScaled[$i],
    ];

    $features = array_merge($features, $typeVectors[$i]->val());

    $output[] = [
        'station'  => $row['station'],
        'raw'      => $row,
        'features' => $features,
    ];
}

echo json_encode([
    'records' => $output,
], JSON_PRETTY_PRINT) . PHP_EOL;
