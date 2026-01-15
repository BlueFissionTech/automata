<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use BlueFission\Automata\Engine;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Strategy\IStrategy;

class EchoInsightStrategy implements IStrategy
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function predict($input)
    {
        return ['echo' => $input];
    }

    public function accuracy(): float
    {
        return 0.6;
    }

    public function saveModel(string $path): bool
    {
        return true;
    }

    public function loadModel(string $path): bool
    {
        return true;
    }
}

$engine = new Engine();
$engine->registerStrategyProfile(new EchoInsightStrategy(), 'echo', [
    'types' => [InputType::TEXT],
    'weight' => 2,
]);

$engine->registerStructureClassifier(function ($payload) {
    return ['structure' => 'plain_text', 'length' => strlen((string)$payload)];
});

$engine->registerContextProvider(function () {
    return ['source' => 'example'];
});

$report = $engine->analyzeWithAttention('Summarize this text', [
    'strategy_budget' => 1,
]);

print_r($report);
