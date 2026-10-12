<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Automata\Behavioral\Trainable;
use BlueFission\Automata\Behavioral\TrainableMethod;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;

class LocalForecast extends Obj
{
    use Trainable;

    public int $sourceCalls = 0;
    public string $region = 'north';

    #[TrainableMethod(minimumSamples: 2, allowPrediction: true, minimumMatches: 2, behaviors: ['OnForecastReady'])]
    public function estimate(string $zone): array
    {
        $this->sourceCalls++;
        // A repeatable, read-only stand-in for an expensive calculation.
        return ['region' => $this->region, 'zone' => $zone, 'score' => 0.8];
    }
}

$forecast = new LocalForecast();
$forecast->registerAttributedTrainableMethods();
$forecast->registerTrainableState('region', fn (LocalForecast $host) => $host->region);
$sources = [];
$readySources = [];
$forecast->when('OnTrainableReturned', function ($event, $meta) use (&$sources): void {
    $sources[] = $meta->info;
});
$forecast->when('OnForecastReady', function ($event, $meta) use (&$readySources): void {
    $readySources[] = $meta->info;
});

$hook = 'automata.trainable.v1.' . strtolower(str_replace('\\', '.', LocalForecast::class)) . '.estimate';
Dev::filter($hook . '.output', fn (array $output) => $output);
Dev::up();
try {
    $outputs = [];
    for ($i = 0; $i < 3; $i++) {
        $outputs[] = $forecast->trainableInvoke('estimate', 'ridge');
    }
    $forecast->region = 'south'; // New context cannot reuse the north model.
    $outputs[] = $forecast->trainableInvoke('estimate', 'ridge');
} finally {
    Dev::down();
}

echo json_encode([
    'sources' => $sources,
    'forecast_ready_sources' => $readySources,
    'source_calls' => $forecast->sourceCalls,
    'outputs' => $outputs,
    'observations' => count($forecast->trainingRegistry()->observations(LocalForecast::class, 'estimate')),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
