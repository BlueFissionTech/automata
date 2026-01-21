<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;

class MetadataClassifier implements IClassifier
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $mime = $input['mime'] ?? '';
        $width = (int)($input['width'] ?? 0);
        $height = (int)($input['height'] ?? 0);
        $sizeBytes = (int)($input['size_bytes'] ?? 0);

        if (strpos($mime, 'image/') === 0) {
            $result->addTag('image', 0.6);
        }

        $pixels = $width * $height;
        if ($pixels >= 3000000) {
            $result->addTag('overview', 0.7);
        }

        if ($sizeBytes >= 5000000) {
            $result->addTag('large_file', 0.5);
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

class TextClassifier implements IClassifier
{
    public function train(array $samples, array $labels, float $testSize = 0.2)
    {
        return null;
    }

    public function classify($input, Context $context, array $options = []): Result
    {
        $result = new Result();
        $text = strtolower((string)($input['text'] ?? ''));

        $tags = [
            'damage' => ['collapsed', 'damage', 'debris'],
            'people' => ['injured', 'people', 'shelter'],
            'infrastructure' => ['bridge', 'road', 'power'],
            'flooding' => ['flood', 'flooding', 'river'],
        ];

        foreach ($tags as $label => $keywords) {
            $hits = 0;
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $score = min(1.0, 0.3 + ($hits * 0.2));
                $result->addTag($label, $score);
            }
        }

        return $result;
    }

    public function accuracy(): float
    {
        return 0.5;
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

$dataset = require __DIR__ . '/mock_dataset.php';

$gateway = new Gateway();
$gateway->setContext(new Context());
$gateway->registerClassifier(new MetadataClassifier(), 'metadata');
$gateway->registerClassifier(new TextClassifier(), 'text');

foreach ($dataset as $item) {
    $result = $gateway->classify($item, [
        'context' => ['region' => $item['region'] ?? 'unknown'],
    ]);

    $result->graph()->relate('damage', 'infrastructure', 0.6);

    echo "Item: {$item['id']}\n";
    print_r($result->top(5));
    echo "\n";
}
