<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;

class CrisisLabelClassifier implements IClassifier
{
    private array $map = [
        'severe_damage' => ['damage', 'infrastructure'],
        'mild_damage' => ['damage'],
        'no_damage' => ['no_damage'],
    ];

    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $label = $input['label'] ?? '';

        foreach ($this->map[$label] ?? [] as $tag) {
            $result->addTag($tag, 0.8);
        }

        return $result;
    }

    public function accuracy(): float
    {
        return 0.4;
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

class CrisisTextClassifier implements IClassifier
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $text = strtolower((string)($input['tweet_text'] ?? ''));

        $keywords = [
            'people' => ['people', 'injured', 'rescue', 'shelter'],
            'infrastructure' => ['bridge', 'road', 'power', 'station'],
            'damage' => ['damage', 'collapsed', 'debris', 'destroyed'],
        ];

        foreach ($keywords as $label => $list) {
            $hits = 0;
            foreach ($list as $word) {
                if (strpos($text, $word) !== false) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $result->addTag($label, min(1.0, 0.3 + ($hits * 0.2)));
            }
        }

        return $result;
    }

    public function accuracy(): float
    {
        return 0.4;
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

$datasetPath = 'D:\\projects\\chat\\datasets\\crisismmd_datasplit_all\\crisismmd_datasplit_all\\task_damage_text_img_train.tsv';
if (!file_exists($datasetPath)) {
    echo "Dataset not found at {$datasetPath}\n";
    exit(0);
}

$gateway = new Gateway();
$gateway->setContext(new Context());
$gateway->registerClassifier(new CrisisLabelClassifier(), 'labels');
$gateway->registerClassifier(new CrisisTextClassifier(), 'text');

$handle = fopen($datasetPath, 'r');
if (!$handle) {
    echo "Unable to open dataset.\n";
    exit(1);
}

$header = fgetcsv($handle, 0, "\t");
$limit = 5;
$count = 0;

while (($row = fgetcsv($handle, 0, "\t")) !== false && $count < $limit) {
    $item = array_combine($header, $row);
    if (!$item) {
        continue;
    }

    $result = $gateway->classify($item, [
        'context' => ['event' => $item['event_name'] ?? 'unknown'],
    ]);

    echo "Tweet {$item['tweet_id']} label={$item['label']}\n";
    print_r($result->top(5));
    echo "\n";
    $count++;
}

fclose($handle);
