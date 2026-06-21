<?php

namespace BlueFission\Automata\LLM\Tools;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\Lanes\LanePressureManager;
use BlueFission\Net\HTTP;
use BlueFission\Str;

class LanePressure extends BaseTool
{
    protected LanePressureManager $manager;

    public function __construct(?LanePressureManager $manager = null)
    {
        $this->name = 'lane_pressure';
        $this->description = 'Assesses semantic, operational, and execution pressure for an agent task.';
        $this->manager = $manager ?: LanePressureManager::standard();
    }

    public function execute($input): string
    {
        $payload = $this->normalizeInput($input);
        if (!Arr::is($payload)) {
            return HTTP::jsonEncode([
                'status' => 'error',
                'error' => [
                    'code' => 'invalid_input',
                    'message' => 'Lane pressure input must be an array or JSON object.',
                ],
            ]);
        }

        $metrics = Arr::make($payload['metrics'] ?? $payload)->toArray();
        $context = Arr::make($payload['context'] ?? [])->toArray();

        return HTTP::jsonEncode($this->manager->assess($metrics, $context));
    }

    protected function normalizeInput(mixed $input): mixed
    {
        if (Str::is($input)) {
            $decoded = json_decode((string)$input, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $input;
    }
}
