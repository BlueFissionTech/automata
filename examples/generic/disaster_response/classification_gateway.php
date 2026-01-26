<?php

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use BlueFission\Automata\Classification\Gateway;
use BlueFission\Automata\Classification\IClassifier;
use BlueFission\Automata\Classification\Result;
use BlueFission\Automata\Context;
use BlueFission\Cli\Args;
use BlueFission\Cli\Args\OptionDefinition;
use BlueFission\Cli\Util\Ansi;
use BlueFission\Cli\Util\Tty;

function parseOptions(array $args): array
{
    $parser = new Args();
    $parser->addOptions([
        new OptionDefinition('verbose', [
            'short' => ['v'],
            'type' => 'bool',
            'default' => false,
            'description' => 'Print detailed tag output.',
        ]),
        new OptionDefinition('color', [
            'type' => 'bool',
            'default' => true,
            'description' => 'Enable ANSI colors.',
        ]),
        new OptionDefinition('limit', [
            'type' => 'int',
            'description' => 'Limit the number of mock samples.',
        ]),
    ]);
    $parser->parse($args);
    $options = $parser->options();
    if (!empty($options['help'])) {
        echo $parser->usage() . PHP_EOL;
        exit(0);
    }

    $limit = array_key_exists('limit', $options) ? max(1, (int)$options['limit']) : null;
    $color = (bool)($options['color'] ?? true) && Tty::isTty() && Ansi::supportsColors();

    return [
        'verbose' => (bool)($options['verbose'] ?? false),
        'color' => $color,
        'limit' => $limit,
    ];
}

function colorize(string $text, string $color = null, array $styles = [], bool $enabled = true): string
{
    if (!$enabled) {
        return $text;
    }

    return Ansi::colorize($text, $color, $styles, true);
}

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

$options = parseOptions($argv ?? []);
$color = $options['color'];
$dataset = require __DIR__ . '/mock_dataset.php';
$limit = $options['limit'];

$gateway = new Gateway();
$gateway->setContext(new Context());
$gateway->registerClassifier(new MetadataClassifier(), 'metadata');
$gateway->registerClassifier(new TextClassifier(), 'text');

if ($limit !== null) {
    $dataset = array_slice($dataset, 0, $limit);
}

foreach ($dataset as $item) {
    $result = $gateway->classify($item, [
        'context' => ['region' => $item['region'] ?? 'unknown'],
    ]);

    $result->graph()->relate('damage', 'infrastructure', 0.6);

    if ($options['verbose']) {
        $title = colorize('Item ' . ($item['id'] ?? 'unknown'), 'cyan', ['bold'], $color);
        echo $title . ' | region=' . ($item['region'] ?? 'unknown') . "\n";
        foreach ($result->top(5) as $tag) {
            $label = $tag['label'] ?? '';
            $score = isset($tag['score']) ? round((float)$tag['score'], 2) : 0.0;
            echo '  - ' . colorize($label, 'yellow', ['bold'], $color) . ' score=' . $score . "\n";
        }
        echo "\n";
    } else {
        echo "Item: {$item['id']}\n";
        print_r($result->top(5));
        echo "\n";
    }
}
