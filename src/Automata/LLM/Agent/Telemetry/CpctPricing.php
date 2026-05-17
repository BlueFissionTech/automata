<?php

namespace BlueFission\Automata\LLM\Agent\Telemetry;

class CpctPricing
{
    protected array $models = [];

    public function __construct(array $config = [])
    {
        $this->models = $config['models'] ?? $config;
    }

    public function costForSpan(array $span): float
    {
        $model = (string)($span['model'] ?? 'default');
        $rates = $this->models[$model] ?? $this->models['default'] ?? [];
        if (!$rates) {
            return 0.0;
        }

        $input = (int)($span['input_tokens'] ?? 0);
        $output = (int)($span['output_tokens'] ?? 0);
        $cacheHit = (int)($span['cache_hit_tokens'] ?? 0);
        $cacheWrite = (int)($span['cache_write_tokens'] ?? 0);
        $batch = (int)($span['batch_tokens'] ?? 0);
        $uncached = (int)($span['uncached_input_tokens'] ?? max(0, $input - $cacheHit - $cacheWrite));

        $inputRate = (float)($rates['input'] ?? 0);
        $outputRate = (float)($rates['output'] ?? 0);
        $cacheHitRate = (float)($rates['cache_hit_input'] ?? ($inputRate * 0.1));
        $cacheWriteRate = (float)($rates['cache_write_input'] ?? $inputRate);
        $batchMultiplier = (float)($rates['batch_multiplier'] ?? 0.5);

        $cost = ($uncached / 1000) * $inputRate;
        $cost += ($cacheHit / 1000) * $cacheHitRate;
        $cost += ($cacheWrite / 1000) * $cacheWriteRate;
        $cost += ($output / 1000) * $outputRate;

        if ($batch > 0) {
            $cost -= ($batch / 1000) * $inputRate * (1 - $batchMultiplier);
        }

        return max(0.0, round($cost, 8));
    }

    public function savingsForCacheHits(array $span): float
    {
        $model = (string)($span['model'] ?? 'default');
        $rates = $this->models[$model] ?? $this->models['default'] ?? [];
        $inputRate = (float)($rates['input'] ?? 0);
        $cacheHitRate = (float)($rates['cache_hit_input'] ?? ($inputRate * 0.1));
        $cacheHit = (int)($span['cache_hit_tokens'] ?? 0);

        return max(0.0, round(($cacheHit / 1000) * ($inputRate - $cacheHitRate), 8));
    }
}
