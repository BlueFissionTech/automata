<?php

namespace BlueFission\Automata;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Func;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\DevElation as Dev;
use BlueFission\DevElation;
use BlueFission\Automata\Support\Evaluates;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Sensory\Input;
use BlueFission\Automata\Strategy\IStrategy;
use BlueFission\Automata\Strategy\Routing\IStrategyRouteAdvisor;
use BlueFission\Automata\Strategy\Routing\StrategyRouteAdvice;
use BlueFission\Automata\Strategy\Routing\StrategyRouteRequest;
use BlueFission\Automata\Strategy\Routing\StrategyRouteResult;
use BlueFission\Automata\Service\BenchmarkService;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Context;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Behavior;
use Throwable;

/**
 * Class Intelligence
 *
 * Manages and orchestrates different AI strategies to analyze input data,
 * make predictions, and learn from feedback.
 */
class Intelligence extends Obj implements IStrategyRouteAdvisor
{
    use Dispatches;
    use Evaluates;

    protected OrganizedCollection $_strategies; // Collection of strategies with weights
    protected float $_minThreshold; // Minimum accuracy threshold for a strategy
    protected ?IStrategy $_lastStrategyUsed = null; // Last strategy used for prediction
    private ?string $_lastStrategyName = null; // Key/name of last strategy used
    private array $_strategyGroups; // Groups of strategies based on data type
    private array $_strategyProfiles; // Strategy metadata (types, tags, weights)
    private array $_strategyPerformance; // Contextual route and prediction observations
    private ?string $_lastStrategyContext = null;
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
        $this->_strategyPerformance = [];
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
        if (!Arr::hasKey($this->_strategyProfiles, $name)) {
            $this->_strategyProfiles[$name] = [
                'types' => [],
                'tags' => [],
                'weight' => null,
                'route_id' => $name,
                'route_version' => '1.0',
            ];
        }
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
            'route_id' => $name,
            'route_version' => '1.0',
        ];

        $profile = $this->mergeMap($defaults, $profile);

        $this->registerStrategy($strategy, $name);
        $this->_strategyProfiles[$name] = $profile;

        if ($profile['weight'] !== null) {
            $this->_strategies->weight($name, (float)$profile['weight']);
            $this->_strategies->sort();
        }
    }

    /**
     * Register an intent analyzer (Func/callable or IAnalyzer implementation).
     *
     * @param Func|callable|IAnalyzer $analyzer
     */
    public function registerIntentAnalyzer($analyzer): void
    {
        $this->_intentAnalyzers[] = $analyzer instanceof Func || $analyzer instanceof IAnalyzer
            ? $analyzer
            : $this->asFunc($analyzer);
    }

    /**
     * Register a structure classifier callable.
     *
     * @param callable $classifier
     */
    public function registerStructureClassifier(Func|callable $classifier): void
    {
        $this->_structureClassifiers[] = $this->asFunc($classifier);
    }

    /**
     * Register a context provider callable.
     *
     * @param callable $provider
     */
    public function registerContextProvider(Func|callable $provider): void
    {
        $this->_contextProviders[] = $this->asFunc($provider);
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
        if ($dataType && Arr::hasKey($this->_strategyGroups, $dataType)) {
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
        if (Arr::isEmpty($strategies)) {
            return null;
        }

        // Combine the existing learned weight with contextual performance evidence.
        $bestName = null;
        $bestMeta = null;
        $bestScore = null;

        foreach ($strategies as $name => $meta) {
            $score = $this->strategyPreferenceScore($name, $input);
            if ($bestScore === null || $score > $bestScore) {
                $bestMeta = $meta;
                $bestName = $name;
                $bestScore = $score;
            }
        }

        /** @var IStrategy $bestStrategy */
        $bestStrategy = $bestMeta['value'];

        $this->_lastStrategyUsed = $bestStrategy;
        $this->_lastStrategyName = $bestName;
        $this->_lastStrategyContext = $this->inputContextKey($input);

        $result = $this->_benchmarkService->benchmarkPrediction($bestStrategy, $input);
        $output = $result['output'];
        $this->recordPerformance(
            $this->profileRouteId($bestName),
            $this->profileRouteVersion($bestName),
            [
                'observation' => true,
                'accuracy' => $this->strategyAccuracy($bestStrategy),
                'latency_ms' => (float)$result['executionTime'] * 1000,
            ],
            $this->_lastStrategyContext
        );

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
            $this->recordStrategyFeedback(
                $this->profileRouteId($this->_lastStrategyName),
                $this->profileRouteVersion($this->_lastStrategyName),
                ['successful' => true, 'prediction_accuracy' => 1.0, 'score' => 1.0],
                $this->_lastStrategyContext ?? 'global'
            );
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
            $this->recordStrategyFeedback(
                $this->profileRouteId($this->_lastStrategyName),
                $this->profileRouteVersion($this->_lastStrategyName),
                ['successful' => false, 'prediction_accuracy' => 0.0, 'score' => 0.0],
                $this->_lastStrategyContext ?? 'global'
            );
        }
    }

    /**
     * Register a listener for prediction events
     *
     * @param callable $listener The listener function
     */
    public function onPrediction(Func|callable $listener)
    {
        $this->behavior(self::PREDICTION_EVENT, $listener instanceof Func ? $listener : new Func($listener));
    }

    /**
     * Provide data-only preferences for the router's already-declared candidates.
     */
    public function advise(StrategyRouteRequest $request, array $definitions): StrategyRouteAdvice
    {
        $contextKey = $this->requestContextKey($request);
        $rankings = [];

        foreach ($definitions as $definition) {
            $definition = Arr::make($definition)->toArray();
            $strategyId = (string)($definition['id'] ?? '');
            $strategyVersion = (string)($definition['version'] ?? '');
            if ($strategyId === '' || $strategyVersion === '') {
                continue;
            }

            $performance = $this->strategyPerformance($strategyId, $strategyVersion, $contextKey);
            $registeredAccuracy = $this->routeStrategyAccuracy($strategyId, $strategyVersion);
            $score = $this->adaptivePreferenceScore(
                $this->routeBaseWeight($strategyId, $strategyVersion),
                $performance,
                $registeredAccuracy
            );
            $key = StrategyRouteAdvice::key($strategyId, $strategyVersion);
            $rankings[$key] = [
                'strategy_id' => $strategyId,
                'strategy_version' => $strategyVersion,
                'score' => $score,
                'confidence' => $performance['confidence'],
                'observations' => $performance['observations'],
                'reasons' => [
                    'existing Intelligence weight is the prior',
                    'quality evidence is balanced against latency, cost, and energy',
                ],
                'evidence' => [[
                    'source' => 'automata.intelligence',
                    'context_key' => $contextKey,
                    'observations' => $performance['observations'],
                    'feedback_samples' => $performance['feedback_samples'],
                ]],
                'metrics' => $performance,
            ];
        }

        return new StrategyRouteAdvice([
            'policy' => StrategyRouteRequest::SELECTION_ADAPTIVE,
            'rankings' => $rankings,
            'evidence' => [[
                'source' => 'automata.intelligence',
                'context_key' => $contextKey,
            ]],
            'metadata' => [
                'advisory_only' => true,
                'may_add_candidates' => false,
                'may_grant_authority' => false,
            ],
        ]);
    }

    /**
     * Learn execution efficiency from a completed route without changing its result.
     */
    public function observe(StrategyRouteRequest $request, StrategyRouteResult $result): void
    {
        $contextKey = $this->requestContextKey($request);
        foreach ($result->attempts as $attempt) {
            if (!($attempt['executed'] ?? false)) {
                continue;
            }

            $usage = Arr::make($attempt['actual_usage'] ?? [])->toArray();
            $this->recordPerformance(
                (string)($attempt['strategy_id'] ?? ''),
                (string)($attempt['strategy_version'] ?? ''),
                [
                    'observation' => true,
                    'successful' => ($attempt['status'] ?? '') === 'completed',
                    'latency_ms' => $usage['latency_ms'] ?? null,
                    'cost' => $usage['cost'] ?? null,
                    'energy' => $usage['energy'] ?? null,
                    'confidence' => $attempt['confidence'] ?? null,
                ],
                $contextKey
            );
        }
    }

    /**
     * Record delayed truth or reviewer feedback for one exact strategy version.
     */
    public function recordStrategyFeedback(
        string $strategyId,
        string $strategyVersion,
        array $feedback,
        string $contextKey = 'global'
    ): void {
        $feedback['feedback'] = true;
        $this->recordPerformance($strategyId, $strategyVersion, $feedback, $contextKey);
    }

    /**
     * Return a data-only performance snapshot suitable for agents and host telemetry.
     */
    public function strategyPerformance(
        string $strategyId,
        string $strategyVersion,
        string $contextKey = 'global'
    ): array {
        $key = StrategyRouteAdvice::key($strategyId, $strategyVersion);
        $bucket = $this->_strategyPerformance[$key][$this->normalizeContextKey($contextKey)]
            ?? $this->performanceDefaults();

        return $this->performanceSnapshot($bucket);
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
            $insights = $this->appendList($insights, $segmentInsights);
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
        $budget = $this->resolveStrategyBudget(Arr::count($strategies), $options);

        $selected = Arr::slice($strategies, 0, $budget);
        $insights = [];

        foreach ($selected as $strategyMeta) {
            $strategy = $strategyMeta['strategy'];
            $name = $strategyMeta['name'];

            $result = $this->_benchmarkService->benchmarkPrediction($strategy, $segment['payload']);
            $accuracy = $strategy instanceof IStrategy ? $strategy->accuracy() : 0.0;
            $score = $this->calculateScore($accuracy, $result['executionTime']);
            $this->recordPerformance(
                $this->profileRouteId($name),
                $this->profileRouteVersion($name),
                [
                    'observation' => true,
                    'accuracy' => $accuracy,
                    'score' => $score,
                    'latency_ms' => (float)$result['executionTime'] * 1000,
                ],
                $this->inputContextKey($segment['payload'])
            );

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

            if (Arr::isNotEmpty($types) && !Arr::contains($types, $type, true)) {
                continue;
            }

            $strategies[] = [
                'name' => $name,
                'strategy' => $strategy,
                'weight' => $meta['weight'] ?? 1,
                'tags' => $profile['tags'] ?? [],
            ];
        }

        $strategies = Arr::sort($strategies, function (array $a, array $b): int {
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

        if (Arr::hasKey($options, 'strategy_budget')) {
            $budget = (int)$options['strategy_budget'];
            return Num::max(1, Num::min($strategyCount, $budget));
        }

        if (Arr::hasKey($options, 'attention_score')) {
            $score = (float)$options['attention_score'];
            $score = Num::max(0.0, Num::min(1.0, $score));
            $budget = (int)Num::max(1, ceil(Num::times($score, $strategyCount)));

            if (Arr::hasKey($options, 'max_strategy_budget')) {
                $budget = Num::min($budget, (int)$options['max_strategy_budget']);
            }
            if (Arr::hasKey($options, 'min_strategy_budget')) {
                $budget = Num::max($budget, (int)$options['min_strategy_budget']);
            }

            return $budget;
        }

        return $strategyCount;
    }

    private function segmentInput($input, array $options): array
    {
        if (Arr::hasKey($options, 'segmenter') && Func::is($options['segmenter'])) {
            $segments = $this->invokeFunc($options['segmenter'], [$input, $options, $this]);
            return $this->normalizeSegments($segments, $options);
        }

        return $this->normalizeSegments($input, $options);
    }

    private function normalizeSegments($input, array $options): array
    {
        $segments = [];
        $items = [];
        $baseMeta = $options['meta'] ?? [];

        if (Arr::is($input)) {
            if ($this->isAssociative($input) && Arr::hasKey($input, 'segments') && Arr::is($input['segments'])) {
                $items = $input['segments'];
                $baseMeta = $this->mergeMap($baseMeta, $input['meta'] ?? []);
            } elseif ($this->isAssociative($input) && (Arr::hasKey($input, 'payload') || Arr::hasKey($input, 'type'))) {
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

            if (Arr::is($item) && (Arr::hasKey($item, 'payload') || Arr::hasKey($item, 'type'))) {
                $payload = $item['payload'] ?? ($item['content'] ?? $item);
                $type = $item['type'] ?? null;
                $meta = $this->mergeMap($meta, $item['meta'] ?? []);
            }

            $type = $type ?: ($this->getType($payload) ?? InputType::TEXT);

            if (Arr::hasKey($options, 'segment_meta') && Func::is($options['segment_meta'])) {
                $extraMeta = (array)$this->invokeFunc($options['segment_meta'], [$payload, $type, $index, $meta, $this]);
                $meta = $this->mergeMap($meta, $extraMeta);
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
        if (Arr::hasKey($options, 'intent_classifier') && Func::is($options['intent_classifier'])) {
            $intentSignals[] = $this->invokeFunc($options['intent_classifier'], [$payload, $type, $meta, $context, $intents, $this]);
        }

        foreach ($this->_intentAnalyzers as $analyzer) {
            $intentSignals[] = $this->runIntentAnalyzer($analyzer, $payload, $context, $intents);
        }

        if (Arr::isNotEmpty($intentSignals)) {
            $meta['intent'] = $intentSignals;
        }

        $structureSignals = [];
        if (Arr::hasKey($options, 'structure_classifier') && Func::is($options['structure_classifier'])) {
            $structureSignals[] = $this->invokeFunc($options['structure_classifier'], [$payload, $type, $meta, $context, $this]);
        }
        foreach ($this->_structureClassifiers as $classifier) {
            $structureSignals[] = $this->invokeFunc($classifier, [$payload, $type, $meta, $context, $this]);
        }
        if (Arr::isNotEmpty($structureSignals)) {
            $meta['structure'] = $structureSignals;
        }

        $contextSignals = [];
        if (Arr::hasKey($options, 'context_provider') && Func::is($options['context_provider'])) {
            $contextSignals[] = $this->invokeFunc($options['context_provider'], [$payload, $type, $meta, $context, $this]);
        }
        foreach ($this->_contextProviders as $provider) {
            $contextSignals[] = $this->invokeFunc($provider, [$payload, $type, $meta, $context, $this]);
        }
        if (Arr::isNotEmpty($contextSignals)) {
            $meta['context'] = $contextSignals;
        }

        return $meta;
    }

    private function runIntentAnalyzer($analyzer, $payload, Context $context, array $intents)
    {
        if ($analyzer instanceof IAnalyzer) {
            return $analyzer->analyze((string)$payload, $context, $intents);
        }

        if (Func::is($analyzer)) {
            return $this->invokeFunc($analyzer, [$payload, $context, $intents, $this]);
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

        if (Arr::is($context)) {
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
        $topStrategies = $this->topKeys($strategyScores, 3);

        $intentScores = $this->aggregateSignals($segments, 'intent');
        $structureScores = $this->aggregateSignals($segments, 'structure');
        $contextScores = $this->aggregateSignals($segments, 'context');

        return [
            'segment_count' => Arr::count($segments),
            'insight_count' => Arr::count($insights),
            'segment_types' => $segmentTypes,
            'strategy_scores' => $strategyScores,
            'top_strategies' => $topStrategies,
            'intent_scores' => $intentScores,
            'structure_scores' => $structureScores,
            'context_scores' => $contextScores,
            'top_intents' => $this->topKeys($intentScores, 3),
            'top_structures' => $this->topKeys($structureScores, 3),
            'top_context' => $this->topKeys($contextScores, 3),
        ];
    }

    private function isAssociative(array $value): bool
    {
        return Arr::isNotEmpty($value) && Arr::isAssoc($value);
    }

    private function aggregateSignals(array $segments, string $metaKey): array
    {
        $scores = [];

        foreach ($segments as $segment) {
            if (!Arr::hasKey($segment['meta'], $metaKey)) {
                continue;
            }

            $signals = $segment['meta'][$metaKey];
            if (!Arr::is($signals)) {
                $signals = [$signals];
            }

            foreach ($signals as $signal) {
                $entries = $this->normalizeSignal($signal);
                foreach ($entries as $label => $score) {
                    $label = Str::trim((string)$label);
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

        if (Arr::is($signal)) {
            if ($this->isAssociative($signal)) {
                if (Arr::hasKey($signal, 'label')) {
                    $label = (string)$signal['label'];
                    $score = $signal['score'] ?? ($signal['weight'] ?? 1);
                    return [$label => $this->normalizeScore($score, $label)];
                }

                $entries = [];
                foreach ($signal as $key => $value) {
                    if (Num::isValid($value)) {
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
                } elseif (Arr::is($value) && $this->isAssociative($value)) {
                    $entries = $this->mergeMap($entries, $this->normalizeSignal($value));
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
        if (Num::isValid($score)) {
            return (float)$score;
        }

        if (is_scalar($score) && Str::trim((string)$score) !== '') {
            return 1.0;
        }

        return 0.0;
    }

    private function formatScalarSignal($key, $value): string
    {
        $valueText = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        return (string)$key . ':' . $valueText;
    }

    private function strategyPreferenceScore(string $name, mixed $input): float
    {
        $strategy = $this->strategyByName($name);
        $performance = $this->strategyPerformance(
            $this->profileRouteId($name),
            $this->profileRouteVersion($name),
            $this->inputContextKey($input)
        );

        return $this->adaptivePreferenceScore(
            (float)$this->_strategies->weight($name),
            $performance,
            $strategy instanceof IStrategy ? $this->strategyAccuracy($strategy) : null
        );
    }

    private function adaptivePreferenceScore(
        float $baseWeight,
        array $performance,
        ?float $registeredAccuracy = null
    ): float {
        $qualitySignals = [];
        foreach (['success_rate', 'accuracy', 'prediction_accuracy', 'average_score'] as $key) {
            if ($performance[$key] !== null) {
                $qualitySignals[] = $this->boundedRatio((float)$performance[$key]);
            }
        }
        if ($registeredAccuracy !== null) {
            $qualitySignals[] = $this->boundedRatio($registeredAccuracy);
        }

        $quality = $this->averageValues($qualitySignals, 0.5);
        $confidence = (float)$performance['confidence'];
        if ($registeredAccuracy !== null) {
            $confidence = Num::max(0.25, $confidence);
        }
        $quality = Num::plus(
            Num::times(0.5, Num::minus(1.0, $confidence)),
            Num::times($quality, $confidence)
        );

        $penalty = Num::plus(
            Num::divide((float)$performance['average_latency_ms'], 1000),
            Num::plus((float)$performance['average_cost'], (float)$performance['average_energy'])
        );

        return Num::times(
            Num::max(0.0, $baseWeight),
            Num::divide($quality, Num::plus(1.0, $penalty))
        );
    }

    private function recordPerformance(
        string $strategyId,
        string $strategyVersion,
        array $metrics,
        string $contextKey
    ): void {
        if ($strategyId === '' || $strategyVersion === '') {
            return;
        }

        $key = StrategyRouteAdvice::key($strategyId, $strategyVersion);
        $contexts = [$this->normalizeContextKey($contextKey)];
        if ($contexts[0] !== 'global') {
            $contexts[] = 'global';
        }

        foreach ($contexts as $context) {
            $bucket = $this->_strategyPerformance[$key][$context] ?? $this->performanceDefaults();
            if ($metrics['observation'] ?? false) {
                $bucket['observations'] = (int)Num::plus($bucket['observations'], 1);
            }
            if (Arr::hasKey($metrics, 'successful') && $metrics['successful'] !== null) {
                $bucket['outcome_samples'] = (int)Num::plus($bucket['outcome_samples'], 1);
                if ((bool)$metrics['successful']) {
                    $bucket['successes'] = (int)Num::plus($bucket['successes'], 1);
                } else {
                    $bucket['failures'] = (int)Num::plus($bucket['failures'], 1);
                }
            }
            if ($metrics['feedback'] ?? false) {
                $bucket['feedback_samples'] = (int)Num::plus($bucket['feedback_samples'], 1);
            }

            foreach ([
                'accuracy' => 'accuracy',
                'prediction_accuracy' => 'prediction_accuracy',
                'score' => 'score',
                'latency_ms' => 'latency_ms',
                'cost' => 'cost',
                'energy' => 'energy',
                'confidence' => 'confidence',
            ] as $metric => $bucketPrefix) {
                if (!Arr::hasKey($metrics, $metric) || $metrics[$metric] === null) {
                    continue;
                }

                $value = (float)$metrics[$metric];
                if (Arr::has(['accuracy', 'prediction_accuracy', 'score', 'confidence'], $metric, true)) {
                    $value = $this->boundedRatio($value);
                } else {
                    $value = Num::max(0.0, $value);
                }
                $bucket[$bucketPrefix . '_total'] = Num::plus($bucket[$bucketPrefix . '_total'], $value);
                $bucket[$bucketPrefix . '_samples'] = (int)Num::plus(
                    $bucket[$bucketPrefix . '_samples'],
                    1
                );
            }

            $this->_strategyPerformance[$key][$context] = $bucket;
        }
    }

    private function performanceSnapshot(array $bucket): array
    {
        $successRate = $bucket['outcome_samples'] > 0
            ? Num::divide($bucket['successes'], $bucket['outcome_samples'])
            : null;
        $accuracy = $this->averageMetric($bucket, 'accuracy');
        $predictionAccuracy = $this->averageMetric($bucket, 'prediction_accuracy');
        $averageScore = $this->averageMetric($bucket, 'score');
        $averageLatency = $this->averageMetric($bucket, 'latency_ms') ?? 0.0;
        $averageCost = $this->averageMetric($bucket, 'cost') ?? 0.0;
        $averageEnergy = $this->averageMetric($bucket, 'energy') ?? 0.0;
        $averageConfidence = $this->averageMetric($bucket, 'confidence');
        $sampleCount = Num::plus($bucket['observations'], $bucket['feedback_samples']);
        $confidence = Num::min(1.0, Num::divide($sampleCount, 5));
        if ($averageConfidence !== null) {
            $confidence = Num::max($confidence, $averageConfidence);
        }

        $qualitySignals = Arr::make([
            $successRate,
            $accuracy,
            $predictionAccuracy,
            $averageScore,
        ])->filter(static fn ($value): bool => $value !== null)->values()->toArray();
        $quality = $this->averageValues($qualitySignals, 0.5);
        $efficiency = Num::divide(
            $quality,
            Num::plus(
                1.0,
                Num::plus(
                    Num::divide($averageLatency, 1000),
                    Num::plus($averageCost, $averageEnergy)
                )
            )
        );

        return [
            'observations' => $bucket['observations'],
            'successes' => $bucket['successes'],
            'failures' => $bucket['failures'],
            'outcome_samples' => $bucket['outcome_samples'],
            'feedback_samples' => $bucket['feedback_samples'],
            'success_rate' => $successRate,
            'accuracy' => $accuracy,
            'prediction_accuracy' => $predictionAccuracy,
            'average_score' => $averageScore,
            'average_latency_ms' => $averageLatency,
            'average_cost' => $averageCost,
            'average_energy' => $averageEnergy,
            'confidence' => $this->boundedRatio((float)$confidence),
            'quality' => $this->boundedRatio((float)$quality),
            'efficiency' => Num::max(0.0, (float)$efficiency),
        ];
    }

    private function performanceDefaults(): array
    {
        return [
            'observations' => 0,
            'successes' => 0,
            'failures' => 0,
            'outcome_samples' => 0,
            'feedback_samples' => 0,
            'accuracy_total' => 0.0,
            'accuracy_samples' => 0,
            'prediction_accuracy_total' => 0.0,
            'prediction_accuracy_samples' => 0,
            'score_total' => 0.0,
            'score_samples' => 0,
            'latency_ms_total' => 0.0,
            'latency_ms_samples' => 0,
            'cost_total' => 0.0,
            'cost_samples' => 0,
            'energy_total' => 0.0,
            'energy_samples' => 0,
            'confidence_total' => 0.0,
            'confidence_samples' => 0,
        ];
    }

    private function averageMetric(array $bucket, string $metric): ?float
    {
        $samples = (int)$bucket[$metric . '_samples'];

        return $samples > 0 ? Num::divide((float)$bucket[$metric . '_total'], $samples) : null;
    }

    private function averageValues(array $values, float $default): float
    {
        if (Arr::isEmpty($values)) {
            return $default;
        }

        $total = 0.0;
        foreach ($values as $value) {
            $total = Num::plus($total, (float)$value);
        }

        return Num::divide($total, Arr::count($values));
    }

    private function routeBaseWeight(string $strategyId, string $strategyVersion): float
    {
        foreach ($this->_strategyProfiles as $name => $profile) {
            if (
                $this->profileRouteId($name) === $strategyId
                && $this->profileRouteVersion($name) === $strategyVersion
                && $this->_strategies->has($name)
            ) {
                return (float)$this->_strategies->weight($name);
            }
        }

        return 1.0;
    }

    private function routeStrategyAccuracy(string $strategyId, string $strategyVersion): ?float
    {
        foreach ($this->_strategyProfiles as $name => $profile) {
            if (
                $this->profileRouteId($name) === $strategyId
                && $this->profileRouteVersion($name) === $strategyVersion
            ) {
                $strategy = $this->strategyByName($name);
                return $strategy instanceof IStrategy ? $this->strategyAccuracy($strategy) : null;
            }
        }

        return null;
    }

    private function strategyByName(string $name): ?IStrategy
    {
        $entry = $this->_strategies->toArray()[$name] ?? null;
        $strategy = Arr::is($entry) ? ($entry['value'] ?? null) : null;

        return $strategy instanceof IStrategy ? $strategy : null;
    }

    private function strategyAccuracy(IStrategy $strategy): ?float
    {
        try {
            return $this->boundedRatio($strategy->accuracy());
        } catch (Throwable) {
            return null;
        }
    }

    private function profileRouteId(string $name): string
    {
        return (string)($this->_strategyProfiles[$name]['route_id'] ?? $name);
    }

    private function profileRouteVersion(string $name): string
    {
        return (string)($this->_strategyProfiles[$name]['route_version'] ?? '1.0');
    }

    private function requestContextKey(StrategyRouteRequest $request): string
    {
        return $request->context_key !== ''
            ? $this->normalizeContextKey($request->context_key)
            : $this->inputContextKey($request->input);
    }

    private function inputContextKey(mixed $input): string
    {
        $type = $this->getType($input);

        return $type === null || $type === '' ? 'global' : 'type:' . $type;
    }

    private function normalizeContextKey(string $contextKey): string
    {
        $contextKey = Str::trim($contextKey);

        return $contextKey === '' ? 'global' : $contextKey;
    }

    private function boundedRatio(float $value): float
    {
        return (float)Num::max(0.0, Num::min(1.0, $value));
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
        return Num::divide($accuracy, Num::add(1, $executionTime));
    }

    private function mergeMap(array $base, array ...$segments): array
    {
        $merged = new Arr($base);

        foreach ($segments as $segment) {
            foreach ($segment as $key => $value) {
                $merged[$key] = $value;
            }
        }

        return $merged->toArray();
    }

    private function appendList(array $base, array ...$segments): array
    {
        $merged = new Arr($base);

        foreach ($segments as $segment) {
            foreach ($segment as $value) {
                $merged->push($value);
            }
        }

        return $merged->toArray();
    }

    private function topKeys(array $values, int $limit): array
    {
        return Arr::slice(Arr::keys($values), 0, $limit);
    }
}
