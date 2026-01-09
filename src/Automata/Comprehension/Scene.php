<?php
namespace BlueFission\Automata\Comprehension;

use BlueFission\Automata\Context;
use BlueFission\Automata\Memory\IWorkingMemory;
use BlueFission\Automata\Memory\IRecallScoringStrategy;
use BlueFission\Automata\Memory\TemporalEdge;

class Scene
{
	private array $_frames = [];
	private array $_stack = [];
	private array $_groups = [];

	private float $_group_tolerance = 100;
	private float $_variance_tolerance = 100;
	private float $_new_tolerance = 50;

	private int $_buffer_size = 10;

	private array $_temporalEdges = [];

	private IWorkingMemory $_memory;

	public function __construct(IWorkingMemory $memory)
	{
		$this->_memory = $memory;
	}

	public function addFrame(Frame $frame): void
	{
		if (count($this->_frames) >= $this->_buffer_size) {
			array_shift($this->_frames); // Remove oldest frame
		}
		$this->_frames[] = $frame;

		$entities = $frame->extract();
		$this->processEntities($entities);
	}

	protected function processEntities(array $entities): void
	{
	    $contextMap = [];

	    foreach ($entities as $label => $data) {
	        if (!isset($data['value'])) {
	            continue;
	        }

	        $value = $data['value'];

	        // Create a context object with optional meta
	        $context = new Context();
	        $context->set('label', $label);
	        $context->set('value', $value);
	        $context->set('weight', $data['weight'] ?? 1);

	        $contextMap[$label] = $context;
	    }

	    // Clustering based on similarity and temporal edges
	    $clusters = $this->clusterEntities($contextMap);

	    foreach ($clusters as $clusterLabel => $contextGroup) {
	        if (count($contextGroup) === 1) {
	            // Original logic: treat as a single memory
	            $context = current($contextGroup);
	            $this->_memory->addMemory($clusterLabel, $context);
	        } else {
	            // Grouped concept (e.g., mob)
	            $combined = new Context();
	            foreach ($contextGroup as $context) {
	                foreach ($context->all() as $k => $v) {
	                    $combined->set($k, $v);
	                }
	            }

	            $grpLabel = $this->generateGroupLabel($contextGroup);
	            $this->_memory->addMemory($grpLabel, $combined);
	            $this->_groups[$grpLabel] = $contextGroup;

	            // New logic: enhance with temporal similarity
	            foreach ($contextGroup as $i => $contextA) {
	                foreach ($contextGroup as $j => $contextB) {
	                    if ($i !== $j) {
	                        $vectorA = $this->hashContextVector($contextA);
	                        $vectorB = $this->hashContextVector($contextB);
	                        $similarity = $this->cosineSimilarity($vectorA, $vectorB);

	                        $temporalKey = "$i-$j";
	                        $edge = $this->_temporalEdges[$temporalKey] ?? null;

	                        if ($edge) {
	                            $similarity = $this->temporalWeight($similarity, $edge->timestamp);
	                            $edge->reinforce();
	                        } else {
	                            $this->_temporalEdges[$temporalKey] = new TemporalEdge($i, $j);
	                        }
	                    }
	                }
	            }
	        }
	    }
	}

	private function temporalWeight(float $similarity, int $timestamp, float $decayRate = 0.001): float
	{
	    $age = time() - $timestamp;
	    return $similarity * exp(-$decayRate * $age);
	}

	/**
	 * Cluster entities by label; this is a placeholder for a richer
	 * similarity-based clustering that can use hashed context vectors.
	 *
	 * @param array<string,Context> $contextMap
	 * @param float $variance_tolerance Currently unused, reserved for future similarity tuning.
	 * @return array<string,array<Context>>
	 */
	protected function clusterEntities(array $contextMap, float $variance_tolerance = 0.7): array {
	    $clusters = [];
	    foreach ($contextMap as $label => $context) {
	        $clusters[$label] = [$context];
	    }
	    return $clusters;
	}

	private function cosineSimilarity(array $vec1, array $vec2): float {
	    $dot = 0.0;
	    $normA = 0.0;
	    $normB = 0.0;

	    $length = min(count($vec1), count($vec2));
	    for ($i = 0; $i < $length; $i++) {
	        $dot += $vec1[$i] * $vec2[$i];
	        $normA += $vec1[$i] ** 2;
	        $normB += $vec2[$i] ** 2;
	    }

	    if ($normA == 0 || $normB == 0) return 0.0;
	    return $dot / (sqrt($normA) * sqrt($normB));
	}


	protected function generateGroupLabel(array $contexts): string
	{
		$labels = array_map(fn($ctx) => $ctx->get('label'), $contexts);
		sort($labels);
		return 'group_' . md5(implode('_', $labels));
	}

	public function recallFromGroup(string $groupLabel, float $tolerance = 0.7, ?IRecallScoringStrategy $strategy = null): array
	{
	    if (!isset($this->_groups[$groupLabel])) {
	        return [];
	    }

	    $groupMembers = $this->_groups[$groupLabel];
	    $results = [];
	    $vectors = [];

	    foreach ($groupMembers as $context) {
	        $label = $context->get('label');
	        $vectors[$label] = $this->hashContextVector($context);
	    }

	    $labels = array_keys($vectors);
	    $count = count($labels);

	    for ($i = 0; $i < $count; $i++) {
	        $labelA = $labels[$i];
	        $vecA = $vectors[$labelA];

	        for ($j = $i + 1; $j < $count; $j++) {
	            $labelB = $labels[$j];
	            $vecB = $vectors[$labelB];

	            $score = $strategy 
	                ? $strategy->score($vecA, $vecB, $groupMembers[$i], $groupMembers[$j])
	                : $this->cosineSimilarity($vecA, $vecB);

	            if ($score >= $tolerance) {
	                $results[$labelA] = $groupMembers[$i];
	                $results[$labelB] = $groupMembers[$j];
	            }
	        }
	    }

	    return $results;
	}

    /**
     * Hash a context into a numeric vector using crc32 on each key/value pair.
     *
     * @param Context $context
     * @return float[]
     */
	private function hashContextVector(Context $context): array
	{
	    $data = $context->all();
	    $hashes = [];

	    foreach ($data as $key => $value) {
	        $scalar = is_scalar($value) ? (string)$value : json_encode($value);
	        $hashes[] = crc32((string)$key . ':' . $scalar) * 0.0000000001;
	    }

	    return $hashes;
	}


	public function frames(): array
	{
		return $this->_frames;
	}

	public function groups(): array
	{
		return $this->_groups;
	}

	public function memory(): IWorkingMemory
	{
		return $this->_memory;
	}

	public function stats() 
	{
		return [];
	}

	public function data() 
	{
		return [];
	}
}
