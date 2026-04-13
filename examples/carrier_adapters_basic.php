<?php

declare(strict_types=1);

require_once __DIR__ . '/../tests/bootstrap.php';

use BlueFission\Obj;
use BlueFission\Automata\Adapters\CarrierAdapter;
use BlueFission\Automata\Adapters\StateAdapter;

$carrier = new class extends Obj {
};
$carrier->assign([
    'status' => 'draft',
    'metrics' => [
        'load' => 2,
    ],
]);

$carrierAdapter = new CarrierAdapter($carrier);
$stateAdapter = StateAdapter::wrap($carrier);
$stateAdapter
    ->set('metrics.load', 5)
    ->set('metrics.health', 'watch')
    ->set('status', 'ready')
    ->sync();

echo json_encode([
    'carrier_snapshot' => $carrierAdapter->snapshot(),
    'state_snapshot' => $stateAdapter->snapshot(),
    'carrier_status' => $carrierAdapter->field('status'),
], JSON_PRETTY_PRINT) . PHP_EOL;
