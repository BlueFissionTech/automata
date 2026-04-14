<?php

use BlueFission\Automata\Engine;
use BlueFission\Automata\InputType;
use BlueFission\Automata\Media\Ingestion\Gateway as IngestionGateway;
use BlueFission\Automata\Media\Ingestion\TextIngestor;
use BlueFission\Automata\Media\Processing\Gateway as ProcessingGateway;
use BlueFission\Automata\Media\Processing\HandlerRegistry;
use BlueFission\Automata\Media\Processing\Image\SimpleClientsOcrHandler;
use BlueFission\Automata\Media\Processing\Text\TextPipeline;
use BlueFission\Automata\Strategy\NaiveBayesTextClassification;

require_once __DIR__ . '/../vendor/autoload.php';

$input = $argv[1] ?? 'Flooded roadway near bridge; volunteers needed.';

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
    'Flooded roadway near bridge',
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

$engine = new Engine();
$engine->registerStrategyProfile($strategy, 'naive-bayes-text', [
    'types' => [InputType::TEXT],
    'tags' => ['media', 'text'],
]);

$normalized = $result->meta()['normalized_text'] ?? $input;

$report = $engine->analyzeWithAttention($normalized, [
    'segmenter' => function () use ($result) {
        return $result->segments();
    },
]);

echo "Attention score: " . ($report['attention']['score'] ?? 0) . PHP_EOL;
echo "Top strategies: " . implode(', ', $report['gestalt']['top_strategies'] ?? []) . PHP_EOL;
echo "Segments: " . count($report['segments'] ?? []) . PHP_EOL;
