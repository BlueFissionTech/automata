<?php

require_once dirname(__DIR__) . '/bootstrap.php';

use BlueFission\Arr;
use BlueFission\Automata\Engine;

final class EngineResultFixture
{
    public int $calls = 0;

    public function __construct(private mixed $result)
    {
    }

    public function predict(mixed $input): mixed
    {
        $this->calls++;

        return $this->result;
    }
}

final class EngineGuessFixture
{
    private bool $processed = false;

    public function process(mixed $input): void
    {
        $this->processed = true;
    }

    public function guess(): mixed
    {
        return $this->processed ? null : 'not processed';
    }
}

$checks = [];

$later = new EngineResultFixture('answer');
$engine = new Engine();
$engine->addProcessor('no-result', new EngineGuessFixture());
$engine->addProcessor('later', $later);
$checks['null_guess_continues'] = $engine->classify('request') === 'answer' && $later->calls === 1;

foreach (['false' => false, 'zero' => 0] as $name => $prediction) {
    $first = new EngineResultFixture($prediction);
    $unused = new EngineResultFixture('later');
    $engine = new Engine();
    $engine->addProcessor('first', $first);
    $engine->addProcessor('unused', $unused);

    $checks[$name . '_is_a_result'] = $engine->classify('request') === $prediction
        && $first->calls === 1
        && $unused->calls === 0;
}

$engine = new Engine();
$engine->addProcessor('no-result', new EngineResultFixture(null));
$checks['input_fallback_only_after_no_result'] = $engine->classify('request') === 'request';
$checks['elapsed_wall_seconds'] = is_float($engine->time()) && $engine->time() >= 0.0;

$passed = !Arr::has($checks, false, true);

echo json_encode([
    'experiment' => 'engine-classification-v1',
    'passed' => $passed,
    'checks' => $checks,
    'elapsed_seconds' => $engine->time(),
    'limits' => [
        'Only null means that a strategy supplied no result.',
        'Elapsed time is monotonic wall time for the most recent strategy attempt, not CPU time.',
        'These fixture predictors are side-effect-free and make no quality claim.',
    ],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

exit($passed ? 0 : 1);
