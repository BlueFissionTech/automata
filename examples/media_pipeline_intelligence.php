<?php

use BlueFission\Automata\InputType;
use BlueFission\Automata\Intelligence;
use BlueFission\Automata\Media\Ingestion\Gateway as IngestionGateway;
use BlueFission\Automata\Media\Ingestion\TextIngestor;
use BlueFission\Automata\Media\Processing\Gateway as ProcessingGateway;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\Image\SimpleClientsOcrHandler;
use BlueFission\Automata\Media\Processing\Text\TextPipeline;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

require_once __DIR__ . '/../vendor/autoload.php';

$input = $argv[1] ?? 'Bridge collapse near river. Volunteers needed.';

$ingestion = new IngestionGateway();
$ingestion->registerIngestor(new TextIngestor(), 'text', ['types' => [InputType::TEXT]]);

$processing = new ProcessingGateway();
$processing->registerPipeline(new TextPipeline(), 'text', ['types' => [InputType::TEXT]]);

$registry = new HandlerRegistry();
$registry->register('ocr', new SimpleClientsOcrHandler(), 'simpleclients-ocr', 5);

$item = $ingestion->ingest($input);
$result = $processing->process($item, [
    'handler_registry' => $registry,
]);

$strategy = new NaiveBayesTextClassification();
$samples = [
    'Bridge collapse near river',
    'Volunteers needed for medical aid',
    'Road cleared after storm',
    'Missing persons reported',
];
$labels = [
    'infrastructure',
    'people',
    'infrastructure',
    'people',
];

$strategy->train($samples, $labels, 0.25);

$intelligence = new Intelligence();
$intelligence->registerStrategyProfile($strategy, 'naive-bayes-text', [
    'types' => [InputType::TEXT],
    'tags' => ['media', 'text'],
]);

$analysis = $intelligence->analyze($result->segments());

echo "Normalized text: " . ($result->meta()['normalized_text'] ?? '') . PHP_EOL;
echo "Tokens: " . implode(', ', $result->tokens()) . PHP_EOL;
echo "Bag of words: " . json_encode($result->features()['bag_of_words'] ?? []) . PHP_EOL;
echo "Gestalt summary: " . json_encode($analysis['gestalt']) . PHP_EOL;
