<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use BlueFission\Automata\Engine;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Context;
use BlueFission\Automata\Intent\Intent;
use BlueFission\Automata\Intent\KeywordIntentAnalyzer;
use BlueFission\Automata\Analysis\KeywordTopicAnalyzer;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;
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

$modelDir = dirname(__DIR__, 2) . '/artifacts/models/';
$context = new Context();
$context->set('channel', 'example');

$intents = [
    'support' => new Intent('support', 'Support', [
        'keywords' => [
            ['word' => 'help', 'priority' => 1],
            ['word' => 'error', 'priority' => 0.9],
            ['word' => 'issue', 'priority' => 0.8],
        ],
    ]),
    'billing' => new Intent('billing', 'Billing', [
        'keywords' => [
            ['word' => 'invoice', 'priority' => 1],
            ['word' => 'payment', 'priority' => 0.9],
            ['word' => 'charge', 'priority' => 0.8],
        ],
    ]),
];

$topics = [
    'technical' => [
        ['text' => 'error', 'weight' => 1],
        ['text' => 'crash', 'weight' => 0.8],
        ['text' => 'bug', 'weight' => 0.7],
    ],
    'billing' => [
        ['text' => 'invoice', 'weight' => 1],
        ['text' => 'payment', 'weight' => 0.9],
        ['text' => 'refund', 'weight' => 0.6],
    ],
];

$engine->setContext($context);
$engine->setIntentCatalog($intents);
$engine->registerIntentAnalyzer(
    new KeywordIntentAnalyzer(new NaiveBayesTextClassification(), $modelDir)
);
$topicAnalyzer = new KeywordTopicAnalyzer(new NaiveBayesTextClassification(), $modelDir);
$engine->registerContextProvider(function ($payload) use ($context, $topics, $topicAnalyzer) {
    return $topicAnalyzer->analyze((string)$payload, $context, $topics);
});

$engine->registerStructureClassifier(function ($payload) {
    return ['structure' => 'plain_text', 'length' => strlen((string)$payload)];
});

$engine->registerContextProvider(function () {
    return ['source' => 'example'];
});

$report = $engine->analyzeWithAttention('My invoice failed and I need help fixing this error.', [
    'strategy_budget' => 1,
]);

print_r($report);
