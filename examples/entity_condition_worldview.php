<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
automata_example_require(
    'Automata/Comprehension/Entity.php',
    'Automata/Goal/Condition.php'
);

use BlueFission\Automata\Comprehension\Entity;
use BlueFission\Automata\Goal\Condition;

$entity = new Entity('Warehouse A', 'Regional warehouse', ['category' => 'place']);
$entity
    ->addLabel('place')
    ->addLabel('storage')
    ->defineDimension('x', ['kind' => 'absolute', 'unit' => 'km'])
    ->defineDimension('time', ['kind' => 'relative', 'unit' => 'minutes'])
    ->coordinate('x', 12)
    ->coordinate('time', 5)
    ->relate('supplies', 'Hospital A', ['distance_km' => 12])
    ->record('status', ['condition' => 'operational']);

$condition = new Condition([
    'path' => 'coordinates.x',
    'operator' => 'gte',
    'value' => 10,
    'weight' => 2,
]);

echo json_encode([
    'entity_summary' => $entity->explain(),
    'entity' => $entity->snapshot(),
    'condition_summary' => $condition->explain(),
    'condition' => $condition->snapshot(),
    'matches_entity' => $condition->matches($entity->snapshot()),
], JSON_PRETTY_PRINT) . PHP_EOL;
