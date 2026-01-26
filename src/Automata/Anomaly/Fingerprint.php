<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\Automata\Context;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\DevElation as Dev;

class Fingerprint extends Obj
{
    protected $_data = [
        'features' => [],
        'tags' => [],
        'context' => null,
        'meta' => [],
    ];

    protected $_types = [
        'features' => DataTypes::ARRAY,
        'tags' => DataTypes::ARRAY,
        'context' => DataTypes::GENERIC,
        'meta' => DataTypes::ARRAY,
    ];

    public function __construct(array $data = [])
    {
        parent::__construct();

        $this->assign([
            'features' => $data['features'] ?? [],
            'tags' => $data['tags'] ?? [],
            'context' => $data['context'] ?? new Context(),
            'meta' => $data['meta'] ?? [],
        ]);

        if (!($this->context instanceof Context)) {
            $this->context = new Context();
        }

        Dev::do('anomaly.fingerprint.created', ['fingerprint' => $this]);
    }

    public function features(): array
    {
        $features = $this->field('features');
        return is_array($features) ? $features : [];
    }

    public function tags(): array
    {
        $tags = $this->field('tags');
        return is_array($tags) ? $tags : [];
    }

    public function context(): Context
    {
        $context = $this->field('context');
        return $context instanceof Context ? $context : new Context();
    }

    public function meta(): array
    {
        $meta = $this->field('meta');
        return is_array($meta) ? $meta : [];
    }

    public function hash(): string
    {
        $payload = [
            'features' => $this->features(),
            'tags' => $this->tags(),
            'context' => $this->context()->all(),
        ];

        $json = json_encode($payload);
        return sha1((string)$json);
    }

    public function similarity(Fingerprint $other): float
    {
        $featureScore = $this->vectorSimilarity($this->features(), $other->features());
        $tagScore = $this->tagOverlap($this->tags(), $other->tags());

        if ($featureScore === 0.0 && $tagScore === 0.0) {
            return 0.0;
        }

        if ($featureScore === 0.0) {
            return $tagScore;
        }

        if ($tagScore === 0.0) {
            return $featureScore;
        }

        return ($featureScore + $tagScore) / 2;
    }

    protected function vectorSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        if (!$this->isNumericVector($a) || !$this->isNumericVector($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));

        foreach ($keys as $key) {
            $va = (float)($a[$key] ?? 0.0);
            $vb = (float)($b[$key] ?? 0.0);
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    protected function tagOverlap(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $tagsA = $this->normalizeTags($a);
        $tagsB = $this->normalizeTags($b);
        $intersection = array_intersect($tagsA, $tagsB);
        $union = array_unique(array_merge($tagsA, $tagsB));

        return count($union) > 0 ? count($intersection) / count($union) : 0.0;
    }

    protected function normalizeTags(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            $label = is_string($tag) ? $tag : ($tag['label'] ?? null);
            if ($label !== null) {
                $normalized[] = Str::trim((string)$label);
            }
        }

        return array_values(array_filter($normalized, static fn($val) => $val !== ''));
    }

    protected function isNumericVector(array $vector): bool
    {
        foreach ($vector as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }
}
