<?php

namespace BlueFission\Automata;

use BlueFission\Obj;
use BlueFission\DevElation as Dev;
use BlueFission\DevElation;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Sensory\Input;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Service\BenchmarkService;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;

/**
 * Class Intelligence
 *
 * Manages and orchestrates different AI strategies to analyze input data,
 * make predictions, and learn from feedback.
 */
class Intelligence extends Obj
{
    use Dispatches;

    protected OrganizedCollection $_strategies; // Collection of strategies with weights
    protected float $_minThreshold; // Minimum accuracy threshold for a strategy
    protected ?IStrategy $_lastStrategyUsed = null; // Last strategy used for prediction
    private ?string $_lastStrategyName = null; // Key/name of last strategy used
    private array $_strategyGroups; // Groups of strategies based on data type
    private array $_strategyProfiles; // Strategy metadata (types, tags, weights)
    private BenchmarkService $_benchmarkService; // Service for benchmarking strategies
    private array $_intentAnalyzers;
    private array $_structureClassifiers;
    private array $_contextProviders;
    private array $_intents;
    private ?Context $_context = null;

    const PREDICTION_EVENT = 'prediction_event'; // Event name for predictions

    /**
     * Constructor
     *
     * @param float $minThreshold Minimum accuracy threshold for strategies
     */
    public function __construct($minThreshold = 0.8)
    {
        $this->_strategies = new OrganizedCollection();
        $this->_minThreshold = $minThreshold;
        $this->_strategyGroups = [];
        $this->_strategyProfiles = [];
        $this->_benchmarkService = new BenchmarkService(); // Initialize benchmark service
        $this->_intentAnalyzers = [];
        $this->_structureClassifiers = [];
        $this->_contextProviders = [];
        $this->_intents = [];
        parent::__construct();
    }

    /**
     * Register a strategy with a given name
     *
     * @param IStrategy $strategy The strategy to register
     * @param string $name The name of the strategy
     */
    public function registerStrategy(IStrategy $strategy, string $name)
    {
        $this->_strategies->add($strategy, $name);
    }

    /**
     * Register a strategy with metadata for insight analysis.
     *
     * @param IStrategy $strategy The strategy to register
     * @param string $name The name of the strategy
     * @param array $profile Metadata such as types, tags, and weight
     */
    public function registerStrategyProfile(IStrategy $strategy, string $name, array $profile = []): void
    {
        $defaults = [
            'types' => [],
            'tags' => [],
            'weight' => null,
        ];

        $profile = array_merge($defaults, $profile);

        $this->registerStrategy($strategy, $name);
        $this->_strategyProfiles[$name] = $profile;

        if ($profile['weight'] !== null) {
            $this->_strategies->weight($name, (float)$profile['weight']);
            $this->_strategies->sort();
        }
    }

    /**
     * Register an intent analyzer (callable or IAnalyzer implementation).
     *
     * @param callable|IAnalyzer $analyzer
     */
    public function registerIntentAnalyzer($analyzer): void
    {
        $this->_intentAnalyzers[] = $analyzer;
    }

    /**
     * Register a structure classifier callable.
     *
     * @param callable $classifier
     */
    public function registerStructureClassifier(callable $classifier): void
    {
        $this->_structureClassifiers[] = $classifier;
    }

    /**
     * Register a context provider callable.
     *
     * @param callable $provider
     */
    public function registerContextProvider(callable $provider): void
    {
        $this->_contextProviders[] = $provider;
    }

    /**
     * Provide a catalog of intents for analyzers.
     *
     * @param array $intents
     */
    public function setIntentCatalog(array $intents): void
    {
        $this->_intents = $intents;
    }

    /**
     * Set a shared context for analyzers.
     *
     * @param Context $context
     */
    public function setContext(Context $context): void
    {
        $this->_context = $context;
    }

    /**
     * Register a group of strategies
     *
     * @param DataGroup $group The strategy group to register
     */
    public function registerStrategyGroup(DataGroup $group)
    {
        $this->_strategyGroups[$group->getName()] = $group;
    }

    /**
     * Register an input and set up an event listener for processing it
     *
     * @param Input $input The input to register
     */
    public function registerInput(Input $input)
    {
        $input->on(Event::COMPLETE, function (Behavior $event) {
            $data = $event->context;
            foreach ($this->_strategies as $strategy) {
                return $strategy->predict($data);
            }
        });
    }

    /**
     * Scan the input, determine its type, and use appropriate strategies to make predictions
     *
     * @param mixed $input The input data to scan
     */
    public function scan($input)
    {
        $dataType = $this->getType($input);
        if ($dataType && isset($this->_strategyGroups[$dataType])) {
            $group = $this->_strategyGroups[$dataType];
            $strategies = $group->getStrategies();

            // Iterate through strategies and use them
            foreach ($strategies as $strategy) {
                $result = $this->_benchmarkService->benchmarkPrediction($strategy, $input);
                $this->dispatch(self::PREDICTION_EVENT, [
                    'strategy' => get_class($strategy),
                    'output' => $result['output'],
                    'executionTime' => $result['executionTime'],
                    'type' => $dataType,
                ]);
            }
        }
    }

    /**
     * Train all registered strategies on the provided dataset
     *
     * @param array $dataset The training data
     * @param array $labels The labels for the training data
     */
    public function train(array $dataset, array $labels)
    {
        // Allow filters to adjust training data or inject instrumentation.
        $dataset = Dev::apply('automata.intelligence.train.1', $dataset);
        $labels  = Dev::apply('automata.intelligence.train.2', $labels);

        foreach ($this->_strategies->toArray() as $name => $meta) {
            /** @var IStrategy $strategy */
            $strategy = $meta['value'];

            $executionTime = $this->_benchmarkService->benchmarkTraining($strategy, $dataset, $labels);
            $accuracy = $strategy->accuracy();
            $score = $this->calculateScore($accuracy, $executionTime);

            $this->_strategies->weight($name, $score);

            // Hook per-strategy training metrics.
            Dev::do('automata.intelligence.train.action1', [
                'strategy'      => $name,
                'accuracy'      => $accuracy,
                'executionTime' => $executionTime,
            ]);
        }

        // Reorder strategies so that the highest scoring strategy is preferred.
        $this->_strategies->sort();
    }

    /**
     * Make a prediction using the best-rated strategy
     *
     * @param mixed $input The input data for prediction
     * @return mixed The prediction result
     */
    public function predict($input)
    {
        // Pre-prediction input filter.
        $input = Dev::apply('automata.intelligence.predict.1', $input);

        $strategies = $this->_strategies->toArray();
        if (empty($strategies)) {
            return null;
        }

        // Select the strategy with the highest weight.
        $bestName = null;
        $bestMeta = null;

        foreach ($strategies as $name => $meta) {
            if (!isset($bestMeta) || $meta['weight'] > $bestMeta['weight']) {
                $bestMeta = $meta;
                $bestName = $name;
            }
        }

        /** @var IStrategy $bestStrategy */
        $bestStrategy = $bestMeta['value'];

        $this->_lastStrategyUsed = $bestStrategy;
        $this->_lastStrategyName = $bestName;

        $output = $bestStrategy->predict($input);

        // Post-prediction filter and action hook.
        $output = Dev::apply('automata.intelligence.predict.2', $output);
        Dev::do('automata.intelligence.predict.action1', [
            'strategy' => $bestName,
            'input'    => $input,
            'output'   => $output,
        ]);

        return $output;
    }

    /**
     * Approve the last prediction, increasing the weight of the strategy used
     */
    public function approvePrediction()
    {
        if ($this->_lastStrategyName !== null && $this->_strategies->has($this->_lastStrategyName)) {
            $score = $this->_strategies->weight($this->_lastStrategyName);
            $newScore = $score * 1.1;
            $this->_strategies->weight($this->_lastStrategyName, $newScore);
            $this->_strategies->sort();
        }
    }

    /**
     * Reject the last prediction, decreasing the weight of the strategy used
     */
    public function rejectPrediction()
    {
        if ($this->_lastStrategyName !== null && $this->_strategies->has($this->_lastStrategyName)) {
            $score = $this->_strategies->weight($this->_lastStrategyName);
            $newScore = $score * 0.9;
            $this->_strategies->weight($this->_lastStrategyName, $newScore);
            $this->_strategies->sort();
        }
    }

    /**
     * Register a listener for prediction events
     *
     * @param callable $listener The listener function
     */
    public function onPrediction(callable $listener)
    {
        $this->behavior(self::PREDICTION_EVENT, $listener);
    }

    /**
     * Analyze input using multiple strategies, returning scored insights.
     *
     * @param mixed $input
     * @param array $options
     * @return array
     */
    public function analyze($input, array $options = []): array
    {
        $segments = $this->segmentInput($input, $options);
        $insights = [];

        foreach ($segments as $segment) {
            $segmentInsights = $this->analyzeSegment($segment, $options);
            $insights = array_merge($insights, $segmentInsights);
        }

        $gestalt = $this->buildGestalt($segments, $insights);

        return [
            'segments' => $segments,
            'insights' => $insights,
            'gestalt' => $gestalt,
        ];
    }

    /**
     * Get the data type of the input
     *
     * @param mixed $input The input data
     * @return string|null The detected data type
     */
    private function getType($input): ?string
    {
        return InputTypeDetector::detect($input);
    }

    private function analyzeSegment(array $segment, array $options): array
    {
        $strategies = $this->resolveStrategiesForType($segment['type']);
        $budget = $this->resolveStrategyBudget(count($strategies), $options);

        $selected = array_slice($strategies, 0, $budget);
        $insights = [];

        foreach ($selected as $strategyMeta) {
            $strategy = $strategyMeta['strategy'];
            $name = $strategyMeta['name'];

            $result = $this->_benchmarkService->benchmarkPrediction($strategy, $segment['payload']);
            $accuracy = $strategy instanceof IStrategy ? $strategy->accuracy() : 0.0;
            $score = $this->calculateScore($accuracy, $result['executionTime']);

            $insight = [
                'segment_index' => $segment['index'],
                'segment_type' => $segment['type'],
                'strategy' => $name,
                'output' => $result['output'],
                'accuracy' => $accuracy,
                'execution_time' => $result['executionTime'],
                'score' => $score,
                'tags' => $strategyMeta['tags'],
                'meta' => $segment['meta'],
            ];

            $insights[] = $insight;

            $this->dispatch(self::PREDICTION_EVENT, [
                'strategy' => $name,
                'output' => $result['output'],
                'executionTime' => $result['executionTime'],
                'type' => $segment['type'],
            ]);
        }

        return $insights;
    }

    private function resolveStrategiesForType(string $type): array
    {
        $strategies = [];
        $allStrategies = $this->_strategies->toArray();

        foreach ($allStrategies as $name => $meta) {
            /** @var IStrategy $strategy */
            $strategy = $meta['value'];
            $profile = $this->_strategyProfiles[$name] ?? [];
            $types = $profile['types'] ?? [];

            if (!empty($types) && !in_array($type, $types, true)) {
                continue;
            }

            $strategies[] = [
                'name' => $name,
                'strategy' => $strategy,
                'weight' => $meta['weight'] ?? 1,
                'tags' => $profile['tags'] ?? [],
            ];
        }

        usort($strategies, function (array $a, array $b): int {
            if ($a['weight'] === $b['weight']) {
                return 0;
            }

            return ($a['weight'] < $b['weight']) ? 1 : -1;
        });

        return $strategies;
    }

    private function resolveStrategyBudget(int $strategyCount, array $options): int
    {
        if ($strategyCount <= 0) {
            return 0;
        }

        if (isset($options['strategy_budget'])) {
            $budget = (int)$options['strategy_budget'];
            return max(1, min($strategyCount, $budget));
        }

        if (isset($options['attention_score'])) {
            $score = (float)$options['attention_score'];
            $score = max(0.0, min(1.0, $score));
            $budget = (int)max(1, ceil($score * $strategyCount));

            if (isset($options['max_strategy_budget'])) {
                $budget = min($budget, (int)$options['max_strategy_budget']);
            }
            if (isset($options['min_strategy_budget'])) {
                $budget = max($budget, (int)$options['min_strategy_budget']);
            }

            return $budget;
        }

        return $strategyCount;
    }

    private function segmentInput($input, array $options): array
    {
        if (isset($options['segmenter']) && is_callable($options['segmenter'])) {
            $segments = call_user_func($options['segmenter'], $input, $options);
            return $this->normalizeSegments($segments, $options);
        }

        return $this->normalizeSegments($input, $options);
    }

    private function normalizeSegments($input, array $options): array
    {
        $segments = [];
        $items = [];
        $baseMeta = $options['meta'] ?? [];

        if (is_array($input)) {
            if ($this->isAssociative($input) && isset($input['segments']) && is_array($input['segments'])) {
                $items = $input['segments'];
                $baseMeta = array_merge($baseMeta, $input['meta'] ?? []);
            } elseif ($this->isAssociative($input) && (array_key_exists('payload', $input) || array_key_exists('type', $input))) {
                $items = [$input];
            } else {
                $items = $input;
            }
        } else {
            $items = [$input];
        }

        foreach ($items as $index => $item) {
            $payload = $item;
            $type = null;
            $meta = $baseMeta;

            if (is_array($item) && (array_key_exists('payload', $item) || array_key_exists('type', $item))) {
                $payload = $item['payload'] ?? ($item['content'] ?? $item);
                $type = $item['type'] ?? null;
                $meta = array_merge($meta, $item['meta'] ?? []);
            }

            $type = $type ?: ($this->getType($payload) ?? InputType::TEXT);

            if (isset($options['segment_meta']) && is_callable($options['segment_meta'])) {
                $extraMeta = (array)call_user_func($options['segment_meta'], $payload, $type, $index, $meta);
                $meta = array_merge($meta, $extraMeta);
            }

            $meta = $this->applyClassifiers($payload, $type, $meta, $options);

            $segments[] = [
                'index' => $index,
                'type' => $type,
                'payload' => $payload,
                'meta' => $meta,
            ];
        }

        return $segments;
    }

    private function applyClassifiers($payload, string $type, array $meta, array $options): array
    {
        $context = $this->resolveContext($options);
        $intents = $options['intents'] ?? $this->_intents;

        $intentSignals = [];
        if (isset($options['intent_classifier']) && is_callable($options['intent_classifier'])) {
            $intentSignals[] = call_user_func($options['intent_classifier'], $payload, $type, $meta, $context, $intents);
        }

        foreach ($this->_intentAnalyzers as $analyzer) {
            $intentSignals[] = $this->runIntentAnalyzer($analyzer, $payload, $context, $intents);
        }

        if (!empty($intentSignals)) {
            $meta['intent'] = $intentSignals;
        }

        $structureSignals = [];
        if (isset($options['structure_classifier']) && is_callable($options['structure_classifier'])) {
            $structureSignals[] = call_user_func($options['structure_classifier'], $payload, $type, $meta);
        }
        foreach ($this->_structureClassifiers as $classifier) {
            $structureSignals[] = call_user_func($classifier, $payload, $type, $meta);
        }
        if (!empty($structureSignals)) {
            $meta['structure'] = $structureSignals;
        }

        $contextSignals = [];
        if (isset($options['context_provider']) && is_callable($options['context_provider'])) {
            $contextSignals[] = call_user_func($options['context_provider'], $payload, $type, $meta);
        }
        foreach ($this->_contextProviders as $provider) {
            $contextSignals[] = call_user_func($provider, $payload, $type, $meta);
        }
        if (!empty($contextSignals)) {
            $meta['context'] = $contextSignals;
        }

        return $meta;
    }

    private function runIntentAnalyzer($analyzer, $payload, Context $context, array $intents)
    {
        if ($analyzer instanceof IAnalyzer) {
            return $analyzer->analyze((string)$payload, $context, $intents);
        }

        if (is_callable($analyzer)) {
            return call_user_func($analyzer, $payload, $context, $intents);
        }

        return null;
    }

    private function resolveContext(array $options): Context
    {
        $context = $options['context'] ?? $this->_context;

        if ($context instanceof Context) {
            return $context;
        }

        $contextObj = new Context();

        if (is_array($context)) {
            foreach ($context as $key => $value) {
                $contextObj->set($key, $value);
            }
        }

        return $contextObj;
    }

    private function buildGestalt(array $segments, array $insights): array
    {
        $segmentTypes = [];
        foreach ($segments as $segment) {
            $type = $segment['type'];
            $segmentTypes[$type] = ($segmentTypes[$type] ?? 0) + 1;
        }

        $strategyScores = [];
        foreach ($insights as $insight) {
            $name = $insight['strategy'];
            $strategyScores[$name] = ($strategyScores[$name] ?? 0) + (float)$insight['score'];
        }

        arsort($strategyScores);
        $topStrategies = array_slice(array_keys($strategyScores), 0, 3);

        $intentScores = $this->aggregateSignals($segments, 'intent');
        $structureScores = $this->aggregateSignals($segments, 'structure');
        $contextScores = $this->aggregateSignals($segments, 'context');

        return [
            'segment_count' => count($segments),
            'insight_count' => count($insights),
            'segment_types' => $segmentTypes,
            'strategy_scores' => $strategyScores,
            'top_strategies' => $topStrategies,
            'intent_scores' => $intentScores,
            'structure_scores' => $structureScores,
            'context_scores' => $contextScores,
            'top_intents' => array_slice(array_keys($intentScores), 0, 3),
            'top_structures' => array_slice(array_keys($structureScores), 0, 3),
            'top_context' => array_slice(array_keys($contextScores), 0, 3),
        ];
    }

    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function aggregateSignals(array $segments, string $metaKey): array
    {
        $scores = [];

        foreach ($segments as $segment) {
            if (!isset($segment['meta'][$metaKey])) {
                continue;
            }

            $signals = $segment['meta'][$metaKey];
            if (!is_array($signals)) {
                $signals = [$signals];
            }

            foreach ($signals as $signal) {
                $entries = $this->normalizeSignal($signal);
                foreach ($entries as $label => $score) {
                    $label = trim((string)$label);
                    if ($label === '') {
                        continue;
                    }
                    $scores[$label] = ($scores[$label] ?? 0) + (float)$score;
                }
            }
        }

        arsort($scores);

        return $scores;
    }

    private function normalizeSignal($signal): array
    {
        if ($signal instanceof \BlueFission\Arr) {
            $signal = $signal->toArray();
        }

        if (is_array($signal)) {
            if ($this->isAssociative($signal)) {
                if (isset($signal['label'])) {
                    $label = (string)$signal['label'];
                    $score = $signal['score'] ?? ($signal['weight'] ?? 1);
                    return [$label => $this->normalizeScore($score, $label)];
                }

                $entries = [];
                foreach ($signal as $key => $value) {
                    if (is_numeric($value)) {
                        $entries[$key] = (float)$value;
                    } elseif (is_scalar($value)) {
                        $label = $this->formatScalarSignal($key, $value);
                        $entries[$label] = 1.0;
                    }
                }

                return $entries;
            }

            $entries = [];
            foreach ($signal as $value) {
                if (is_scalar($value)) {
                    $entries[(string)$value] = 1.0;
                } elseif (is_array($value) && $this->isAssociative($value)) {
                    $entries = array_merge($entries, $this->normalizeSignal($value));
                }
            }

            return $entries;
        }

        if (is_scalar($signal)) {
            return [(string)$signal => 1.0];
        }

        return [];
    }

    private function normalizeScore($score, string $label): float
    {
        if (is_numeric($score)) {
            return (float)$score;
        }

        if (is_scalar($score) && trim((string)$score) !== '') {
            return 1.0;
        }

        return 0.0;
    }

    private function formatScalarSignal($key, $value): string
    {
        $valueText = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        return (string)$key . ':' . $valueText;
    }

    /**
     * Calculate a score for a strategy based on its accuracy and execution time
     *
     * @param float $accuracy The accuracy of the strategy
     * @param float $executionTime The execution time of the strategy
     * @return float The calculated score
     */
    protected function calculateScore(float $accuracy, float $executionTime): float
    {
        return $accuracy / (1 + $executionTime);
    }
}
