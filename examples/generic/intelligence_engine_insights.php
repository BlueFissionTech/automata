<?php

require_once dirname(__DIR__) . '/bootstrap.php';
automata_example_require(
    'Automata/Intelligence.php',
    'Automata/Engine.php'
);

use BlueFission\Automata\Engine;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Context;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Func;
use BlueFission\Str;

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

$context = new Context();
$context->set('channel', 'example');

$engine->setContext($context);
$engine->setIntentCatalog([
    'support' => 'Support',
    'billing' => 'Billing',
]);
$engine->registerIntentAnalyzer(new Func(function ($payload) {
    $text = strtolower((string)$payload);

    if (str_contains($text, 'invoice') || str_contains($text, 'payment')) {
        return ['billing' => 0.95];
    }

    if (str_contains($text, 'help') || str_contains($text, 'error')) {
        return ['support' => 0.9];
    }

    return ['general' => 0.4];
}));

$engine->registerStructureClassifier(new Func(function ($payload) {
    return ['structure' => 'plain_text', 'length' => Str::len((string)$payload)];
}));

$engine->registerContextProvider(new Func(function ($payload) {
    $text = strtolower((string)$payload);

    return [
        'source' => 'example',
        'topic' => str_contains($text, 'invoice') ? 'billing' : 'support',
    ];
}));

$report = $engine->analyzeWithAttention('My invoice failed and I need help fixing this error.', [
    'strategy_budget' => 1,
]);

echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
