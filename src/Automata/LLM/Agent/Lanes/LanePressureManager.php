<?php

namespace BlueFission\Automata\LLM\Agent\Lanes;

use BlueFission\Arr;
use BlueFission\Automata\LLM\Agent\ToolDefinition;
use BlueFission\DevElation as Dev;
use BlueFission\Num;

class LanePressureManager
{
    public const LEVEL_NONE = 'none';
    public const LEVEL_LOW = 'low';
    public const LEVEL_MEDIUM = 'medium';
    public const LEVEL_HIGH = 'high';
    public const LEVEL_CRITICAL = 'critical';

    protected array $lanes = [];
    protected array $config = [];

    public function __construct(array $lanes = [], array $config = [])
    {
        $this->lanes = $this->normalizeLanes($lanes ?: AgentLane::standard());
        $this->config = ToolDefinition::mergeConfig([
            'thresholds' => [
                self::LEVEL_LOW => 0.25,
                self::LEVEL_MEDIUM => 0.5,
                self::LEVEL_HIGH => 0.75,
                self::LEVEL_CRITICAL => 0.9,
            ],
        ], $config);
    }

    public static function standard(array $config = []): self
    {
        return new self([], $config);
    }

    public function lanes(): array
    {
        return $this->lanes;
    }

    public function lane(string $id): ?AgentLane
    {
        return $this->lanes[$id] ?? null;
    }

    public function assess(array $metrics, array $context = []): array
    {
        $metrics = Dev::apply('automata.llm.agent.lanes.metrics', $metrics);
        $laneReports = [];
        $recommendations = [];
        $dominantLane = null;
        $highestScore = 0.0;

        foreach ($this->lanes as $laneId => $lane) {
            $signals = $this->normalizeSignals(Arr::make($metrics[$laneId] ?? [])->toArray());
            $score = $this->scoreSignals($signals);
            $level = $this->levelFor($score);
            $dominantSignal = $this->dominantSignal($signals);
            $laneRecommendations = $this->recommendationsFor($lane, $level, $dominantSignal);

            if ($score > $highestScore || $dominantLane === null) {
                $highestScore = $score;
                $dominantLane = $laneId;
            }

            foreach ($laneRecommendations as $recommendation) {
                $recommendations[] = $recommendation;
            }

            $laneReports[$laneId] = [
                'lane' => $lane->toArray(),
                'score' => $score,
                'level' => $level,
                'dominant_signal' => $dominantSignal,
                'signals' => $signals,
                'recommendations' => $laneRecommendations,
            ];
        }

        return Dev::apply('automata.llm.agent.lanes.assessment', [
            'dominant_lane' => $dominantLane,
            'overall_score' => $highestScore,
            'overall_level' => $this->levelFor($highestScore),
            'lanes' => $laneReports,
            'recommendations' => $recommendations,
            'unmapped_metrics' => $this->unmappedMetrics($metrics),
            'context' => $context,
        ]);
    }

    protected function normalizeLanes(array $lanes): array
    {
        $normalized = [];
        foreach ($lanes as $key => $lane) {
            $lane = $lane instanceof AgentLane ? $lane : new AgentLane(Arr::make($lane)->toArray());
            $normalized[$lane->id() ?: (string)$key] = $lane;
        }

        return $normalized;
    }

    protected function normalizeSignals(array $signals): array
    {
        $normalized = [];
        foreach ($signals as $name => $value) {
            $weight = 1.0;
            $raw = $value;

            if (Arr::is($value)) {
                $raw = $value['value'] ?? 0;
                $weight = max(0.0, (float)($value['weight'] ?? 1.0));
            }

            $normalized[(string)$name] = [
                'value' => $this->normalizePressureValue($raw),
                'weight' => $weight,
            ];
        }

        return $normalized;
    }

    protected function normalizePressureValue(mixed $value): float
    {
        if ($value === true) {
            return 1.0;
        }

        if ($value === false || $value === null) {
            return 0.0;
        }

        if (!Num::is($value)) {
            return 0.0;
        }

        return Num::max(0.0, Num::min((float)$value, 1.0));
    }

    protected function scoreSignals(array $signals): float
    {
        if (!$signals) {
            return 0.0;
        }

        $weighted = 0.0;
        $weightTotal = 0.0;
        $max = 0.0;

        foreach ($signals as $signal) {
            $value = (float)($signal['value'] ?? 0.0);
            $weight = Num::max(0.0, (float)($signal['weight'] ?? 1.0));
            $weighted += $value * $weight;
            $weightTotal += $weight;
            $max = Num::max($max, $value);
        }

        $average = $weightTotal > 0.0 ? $weighted / $weightTotal : 0.0;

        return Num::round(Num::max($average, $max), 4);
    }

    protected function dominantSignal(array $signals): ?array
    {
        $dominant = null;
        foreach ($signals as $name => $signal) {
            if ($dominant === null || (float)$signal['value'] > (float)$dominant['value']) {
                $dominant = [
                    'name' => (string)$name,
                    'value' => (float)$signal['value'],
                ];
            }
        }

        return $dominant;
    }

    protected function levelFor(float $score): string
    {
        $thresholds = Arr::make($this->config['thresholds'] ?? [])->toArray();

        if ($score >= (float)($thresholds[self::LEVEL_CRITICAL] ?? 0.9)) {
            return self::LEVEL_CRITICAL;
        }

        if ($score >= (float)($thresholds[self::LEVEL_HIGH] ?? 0.75)) {
            return self::LEVEL_HIGH;
        }

        if ($score >= (float)($thresholds[self::LEVEL_MEDIUM] ?? 0.5)) {
            return self::LEVEL_MEDIUM;
        }

        if ($score >= (float)($thresholds[self::LEVEL_LOW] ?? 0.25)) {
            return self::LEVEL_LOW;
        }

        return self::LEVEL_NONE;
    }

    protected function recommendationsFor(AgentLane $lane, string $level, ?array $dominantSignal): array
    {
        if ($level === self::LEVEL_NONE) {
            return [];
        }

        $recommendation = $lane->recommendation($level);
        if (!$recommendation) {
            return [];
        }

        $prefix = $lane->label() . ' lane pressure is ' . $level;
        if ($dominantSignal) {
            $prefix .= ' from ' . $dominantSignal['name'];
        }

        return [$prefix . ': ' . $recommendation];
    }

    protected function unmappedMetrics(array $metrics): array
    {
        $unmapped = [];
        foreach ($metrics as $laneId => $signals) {
            if (!Arr::hasKey($this->lanes, $laneId)) {
                $unmapped[$laneId] = $signals;
            }
        }

        return $unmapped;
    }
}
