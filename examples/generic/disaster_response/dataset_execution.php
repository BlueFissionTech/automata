<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;

class KeywordClassifier implements IClassifier
{
    private array $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $text = normalizeText((string)($input['text'] ?? ''));

        foreach ($this->rules as $label => $keywords) {
            $hits = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $score = min(1.0, 0.3 + ($hits * 0.15));
                $result->addTag($label, $score, ['hits' => $hits]);
            }
        }

        return $result;
    }

    public function accuracy(): float
    {
        return 0.0;
    }

    public function saveModel(string $path): bool
    {
        return false;
    }

    public function loadModel(string $path): bool
    {
        return false;
    }
}

function normalizeText(string $text): string
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function extractFeatures(string $text): array
{
    $normalized = normalizeText($text);
    $tokens = $normalized === '' ? [] : explode(' ', $normalized);

    $urlCount = preg_match_all('~https?://~', $text, $matches);

    return [
        'word_count' => count($tokens),
        'char_count' => strlen($text),
        'url_count' => (int)$urlCount,
        'has_numbers' => (bool)preg_match('/\d/', $text),
    ];
}

function parseArgs(array $args): array
{
    $options = [
        'limit' => 25,
        'use_mock' => false,
        'dataset' => 'crisismmd',
    ];

    foreach ($args as $arg) {
        if (strpos($arg, '--limit=') === 0) {
            $options['limit'] = max(1, (int)substr($arg, 8));
        }
        if ($arg === '--use-mock') {
            $options['use_mock'] = true;
        }
        if (strpos($arg, '--dataset=') === 0) {
            $options['dataset'] = strtolower(substr($arg, 10));
        }
    }

    return $options;
}

function loadCrisisMmd(string $path, int $limit): array
{
    if (!is_file($path)) {
        return [];
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        return [];
    }

    $header = fgetcsv($handle, 0, "\t");
    if (!is_array($header)) {
        fclose($handle);
        return [];
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        $data = array_combine($header, $row);
        if (!is_array($data)) {
            continue;
        }
        $rows[] = [
            'id' => $data['tweet_id'] ?? '',
            'text' => $data['tweet_text'] ?? '',
            'label' => $data['label'] ?? 'unknown',
            'event' => $data['event_name'] ?? 'unknown',
        ];
        if (count($rows) >= $limit) {
            break;
        }
    }

    fclose($handle);
    return $rows;
}

$options = parseArgs($argv ?? []);
$repoRoot = dirname(__DIR__, 3);
$datasetsRoot = dirname($repoRoot) . DIRECTORY_SEPARATOR . 'datasets';

$datasetPath = $datasetsRoot . DIRECTORY_SEPARATOR . 'crisismmd_datasplit_all' . DIRECTORY_SEPARATOR . 'crisismmd_datasplit_all' . DIRECTORY_SEPARATOR . 'task_damage_text_img_dev.tsv';

$items = [];
$status = 'missing';
$datasetName = 'crisismmd';

if (!$options['use_mock'] && $options['dataset'] === 'crisismmd') {
    $items = loadCrisisMmd($datasetPath, $options['limit']);
    if (!empty($items)) {
        $status = 'ok';
    }
}

if ($options['use_mock'] || empty($items)) {
    $mock = require __DIR__ . '/mock_dataset.php';
    $items = array_map(function ($item) {
        return [
            'id' => $item['id'] ?? '',
            'text' => $item['text'] ?? '',
            'label' => $item['label'] ?? 'unknown',
            'event' => $item['region'] ?? 'mock',
        ];
    }, $mock);
    $items = array_slice($items, 0, $options['limit']);
    $status = 'mock';
    $datasetName = 'mock';
}

$rules = [
    'damage' => ['damage', 'collapsed', 'debris', 'destroyed', 'rubble'],
    'rescue' => ['rescue', 'help', 'trapped', 'evacuate', 'urgent'],
    'medical' => ['injured', 'hospital', 'medical', 'ambulance'],
    'infrastructure' => ['bridge', 'road', 'power', 'grid', 'pipeline'],
    'supplies' => ['food', 'water', 'supply', 'shelter'],
];

$gateway = new Gateway();
$gateway->registerClassifier(new KeywordClassifier($rules), 'keyword');

$tagCounts = [];
$labelCounts = [];
$featureStats = [];

foreach ($items as $item) {
    $features = extractFeatures($item['text']);
    foreach ($features as $key => $value) {
        if (!isset($featureStats[$key])) {
            $featureStats[$key] = ['min' => $value, 'max' => $value, 'sum' => 0, 'count' => 0];
        }
        $featureStats[$key]['min'] = min($featureStats[$key]['min'], $value);
        $featureStats[$key]['max'] = max($featureStats[$key]['max'], $value);
        $featureStats[$key]['sum'] += is_bool($value) ? (int)$value : (int)$value;
        $featureStats[$key]['count']++;
    }

    $label = $item['label'] ?? 'unknown';
    $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;

    $result = $gateway->classify(['text' => $item['text']], ['context' => new Context()]);
    foreach ($result->tags() as $tag => $info) {
        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
    }
}

$normalization = [];
foreach ($featureStats as $key => $stats) {
    $avg = $stats['count'] > 0 ? $stats['sum'] / $stats['count'] : 0;
    $normalization[$key] = [
        'min' => $stats['min'],
        'max' => $stats['max'],
        'avg' => round($avg, 2),
    ];
}

$output = [
    'dataset' => $datasetName,
    'status' => $status,
    'source_path' => $datasetPath,
    'count' => count($items),
    'label_distribution' => $labelCounts,
    'tag_distribution' => $tagCounts,
    'feature_normalization' => $normalization,
];

echo json_encode($output, JSON_PRETTY_PRINT);
