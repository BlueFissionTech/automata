<?php

namespace BlueFission\Automata\Parsing\Generators;

use BlueFission\Automata\LLM\Reply;
use BlueFission\Parsing\Contracts\IGenerator;
use BlueFission\Parsing\Element;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;
use Exception;

class LLMGenerator implements IGenerator, IDispatcher {
    
    use Dispatches {
        Dispatches::__construct as private __dispatchConstruct;
    }

    protected array $buffer = [];
    protected array $usageLedger = [];
    protected array $usageTotals = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
        'estimated_prompt_tokens' => 0,
        'estimated_completion_tokens' => 0,
        'estimated_total_tokens' => 0,
    ];
    protected $llm;
    protected $profileResolver = null;

    public function setDriver($llm): void
    {
        $this->llm = $llm;
    }

    public function setProfileResolver(?callable $resolver): void
    {
        $this->profileResolver = $resolver;
    }

    public function usageLedger(): array
    {
        return $this->usageLedger;
    }

    public function usageTotals(): array
    {
        return $this->usageTotals;
    }

    public function resetUsage(): void
    {
        $this->usageLedger = [];
        $this->usageTotals = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'estimated_prompt_tokens' => 0,
            'estimated_completion_tokens' => 0,
            'estimated_total_tokens' => 0,
        ];
    }

    public function __construct() {
        $this->__dispatchConstruct();

        $this->behavior(Event::SENT);
        $this->behavior(Event::RECEIVED);
        $this->behavior(Event::ERROR);
        $this->behavior(State::RUNNING);
        $this->behavior(State::IDLE);
    }

    public function registerEchoes(IDispatcher $parent): void
    {
        foreach ([
          Event::SENT,
          Event::RECEIVED,
          Event::ERROR,
          State::RUNNING,
          State::IDLE,
        ] as $behavior) {
            $this->when($behavior, function ($event, $meta = null) use ($parent, $behavior) {
                $parent->dispatch($behavior, $meta);
            });
        }
    }

    public function generate(Element $element): string
    {
        $options = $element->getAttribute('options');
        $pattern = $element->getAttribute('pattern');
        $config = $element->getAttributes();

        $pattern = $pattern ?? 
            (isset($options) && count($options) > 0 
                ? '/\b(' . implode('|', array_map('preg_quote', $options)) . ')\b/xi' : null);

        $config = (new Collection($config))->filter(function($value, $key) {
            return in_array($key, ['model', 'prompt', 'max_tokens', 'n', 'presence_penalty', 'seed', 'stop', 'temperature', 'top_p']);
        })->toArray();

        if (!isset($this->buffer[$element->getUuid()])) {
            $this->buffer[$element->getUuid()] = '';
        }

        if (!isset($this->usageLedger[$element->getUuid()])) {
            $this->usageLedger[$element->getUuid()] = [];
        }

        $retries = 5;
        $attempt = 0;

        do {
            $attempt++;
            try {
                $generation = $this->generateFromLLM($element, $config, $attempt, $retries);
            } catch (Exception $e) {
                $generation = null;
                $this->dispatch(Event::ERROR, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $element->getTag(),
                    'error' => $e->getMessage()
                ], src: $this));
            }

            $output = $this->buffer[$element->getUuid()];
            $accepted = true;

            if ($pattern && !preg_match($pattern, $output)) {
                // throw new Exception("Generated value '{$output}' does not match required pattern.");
                $this->dispatch(Event::ERROR, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $element->getTag(),
                    'error' => "Generated value '{$output}' does not match required pattern {$pattern}."
                ], src: $this));
                $accepted = false;
                $this->buffer[$element->getUuid()] = '';
            }

            if (Arr::is($generation)) {
                $usage = $generation['usage'] ?? [];
                $metadata = $generation['metadata'] ?? [];
                $this->recordUsage($element, $metadata, $usage, $output, $accepted);
                $this->dispatch(Event::RECEIVED, new Meta(when: State::RUNNING, data: array_merge(
                    $metadata,
                    [
                        'value' => $output,
                        'final' => true,
                        'accepted' => $accepted,
                        'usage' => $usage,
                    ]
                ), src: $this));
            }

            if ($attempt >= $retries) {
                $this->dispatch(Event::ERROR, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $element->getTag(),
                    'error' => "Failed to generate value after {$retries} attempts."
                ], src: $this));
                unset($this->buffer[$element->getUuid()]);
                break;
            }

        } while ($this->buffer[$element->getUuid()] == '' && $attempt < $retries);

        return $this->buffer[$element->getUuid()] ?? '';
    }

    protected function generateFromLLM(Element $element, array $config, int $attempt, int $retries): array
    {
        $profile = $this->resolveProfile($element);
        $driver = $profile['driver'] ?? $this->llm;

        if (!$driver) {
            throw new Exception("No LLM assigned to Element");
        }

        $context = $this->buildPromptContext($element);
        $prompt = $context['prompt'];
        $profilePrompt = Str::trim((string)($profile['prompt'] ?? ''));
        if ($profilePrompt !== '') {
            $prompt = $profilePrompt . "\n\n" . $prompt;
        }

        $options = $element->getAttribute('options');

        if ($options) {
            $optionStr = implode(', ', $options);
            $prompt .= "(Choose the best of the following options to complete the previous statement: [" . $optionStr . "]): ";
            if (isset($this->buffer[$element->getUuid()])) {
                $prompt .= $this->buffer[$element->getUuid()];
            }
        }

        $target = $element->getUuid();
        $metadata = $this->buildEventMetadata($element, $config, $profile, $context, $attempt, $retries, $prompt);

        $this->dispatch(Event::SENT, new Meta(when: State::RUNNING, data: $metadata, src: $this));

        $pattern = $element->getAttribute('pattern') ?? (isset($options) && count($options) > 0 ? '/\b(' . implode('|', array_map('preg_quote', $options)) . ')\b/xi' : null);

        $reply = $driver->generate($prompt, $config, function($output) use ($target, $options, $pattern, $element, $metadata, $prompt) {
            if ($element->getUuid() === $target) {
                if (!isset($this->buffer[$target])) {
                    $this->buffer[$target] = '';
                }

                // if there's a pattern, see if the output looks like it matches or is about to
                // if it does, add to the buffer and continue
                $output = $this->buffer[$target] . $output;

                if (!empty($options) && !$this->matchPrefixOption($output, $options)) {
                    return false;
                }
                    
                // if the output matches a prefix option, we can continue

                // if it matches completely, break and output
                // if it doesn't match match, throw an expection, restart the loop, 
                // and append the existing buffer to the prompt so completion takes over
                // with a hint

                $this->buffer[$target] = $output;

                $this->dispatch(Event::RECEIVED, new Meta(when: State::RUNNING, data: array_merge(
                    $metadata,
                    [
                        'value' => $output,
                        'chunk' => $output,
                        'final' => false,
                        'accepted' => null,
                        'usage' => $this->normalizeUsage(null, $prompt, $output),
                    ]
                ), src: $this));

                // create a pattern variant that cheecks if the existiing output partially matches
                if (!empty($pattern) && preg_match($pattern, $output)) {
                    return true;
                }

                return false;
            }
        });

        $this->consumeReply($reply, $target, $element, $options, $pattern);

        return [
            'metadata' => $metadata,
            'usage' => $this->normalizeUsage($reply, $prompt, $this->buffer[$target] ?? ''),
        ];
    }

    protected function matchPrefixOption(string $buffer, array $options): ?string
    {
        $buffer = Str::lower(Str::trim($buffer));
        
        foreach ($options as $option) {
            if (stripos($option, $buffer) === 0) {
                return $option;
            }
        }

        return null; // no prefix match
    }

    protected function buildPromptContext(Element $element): array
    {
        $closed = $element->getAttribute('closed') === 'true';
        if ($closed) {
            return [
                'prompt' => '',
                'requested_strategy' => 'none',
                'strategy' => 'none',
                'supported' => true,
                'thread' => $element->getAttribute('thread'),
                'session' => $element->getAttribute('session'),
                'max_context_tokens' => 0,
                'truncated' => false,
                'estimated_tokens' => 0,
                'original_estimated_tokens' => 0,
                'dropped_estimated_tokens' => 0,
            ];
        }

        $requestedStrategy = Str::lower(Str::trim((string)($element->getAttribute('context_strategy') ?? 'prefix')));
        $requestedStrategy = $requestedStrategy === '' ? 'prefix' : $requestedStrategy;
        $supported = in_array($requestedStrategy, ['none', 'prefix', 'windowed-prefix'], true);
        $strategy = $supported ? $requestedStrategy : 'prefix';
        $context = '';

        if ($strategy !== 'none') {
            $root = $element->getRoot();
            $context = $root ? $root->getContent() : '';
            $match = $element->getMatch();

            if (Str::is($context) && Str::is($match)) {
                $position = Str::pos($context, $match);
                if ($position !== false) {
                    $context = (string)Str::sub($context, 0, $position);
                }
            }
        }

        $originalEstimatedTokens = $this->estimateTokens($context);
        $maxContextTokens = $this->normalizePositiveInt($element->getAttribute('max_context_tokens')) ?? 0;
        $truncated = false;

        if ($context !== '' && $maxContextTokens > 0 && $originalEstimatedTokens > $maxContextTokens) {
            $truncated = true;
            $context = $this->truncateToTokenWindow($context, $maxContextTokens);
        }

        $estimatedTokens = $this->estimateTokens($context);

        return [
            'prompt' => $context,
            'requested_strategy' => $requestedStrategy,
            'strategy' => $strategy,
            'supported' => $supported,
            'thread' => $element->getAttribute('thread'),
            'session' => $element->getAttribute('session'),
            'max_context_tokens' => $maxContextTokens,
            'truncated' => $truncated,
            'estimated_tokens' => $estimatedTokens,
            'original_estimated_tokens' => $originalEstimatedTokens,
            'dropped_estimated_tokens' => max(0, $originalEstimatedTokens - $estimatedTokens),
        ];
    }

    protected function buildEventMetadata(
        Element $element,
        array $config,
        array $profile,
        array $context,
        int $attempt,
        int $retries,
        string $prompt
    ): array {
        return [
            'placeholder' => $element->getTag(),
            'element' => $this->resolveElementName($element),
            'tag' => $element->getTag(),
            'label' => $element->getAttribute('label'),
            'phase' => $element->getAttribute('phase'),
            'chapter' => $element->getAttribute('chapter'),
            'section' => $element->getAttribute('section'),
            'profile' => $profile['name'] ?? null,
            'attempt' => $attempt,
            'retries' => $retries,
            'config' => $config,
            'thread' => $context['thread'] ?? null,
            'session' => $context['session'] ?? null,
            'context' => [
                'requested_strategy' => $context['requested_strategy'] ?? 'prefix',
                'strategy' => $context['strategy'] ?? 'prefix',
                'supported' => $context['supported'] ?? true,
                'max_context_tokens' => $context['max_context_tokens'] ?? 0,
                'truncated' => $context['truncated'] ?? false,
                'estimated_tokens' => $context['estimated_tokens'] ?? 0,
                'original_estimated_tokens' => $context['original_estimated_tokens'] ?? 0,
                'dropped_estimated_tokens' => $context['dropped_estimated_tokens'] ?? 0,
            ],
            'usage' => $this->normalizeUsage(null, $prompt, ''),
        ];
    }

    protected function truncateToTokenWindow(string $context, int $maxTokens): string
    {
        if ($context === '' || $maxTokens <= 0) {
            return $context;
        }

        $maxCharacters = $maxTokens * 4;
        if (Str::len($context) <= $maxCharacters) {
            return $context;
        }

        return (string)Str::sub($context, -$maxCharacters);
    }

    protected function normalizePositiveInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    protected function estimateTokens(string $text): int
    {
        $text = Str::trim($text);
        if ($text === '') {
            return 0;
        }

        $characters = Str::len($text);
        preg_match_all('/\S+/u', $text, $matches);
        $wordCount = count($matches[0] ?? []);

        return max($wordCount, (int)ceil($characters / 4));
    }

    protected function extractUsage($reply): array
    {
        $usage = null;

        if (Val::is($reply) && method_exists($reply, 'usage')) {
            $usage = $reply->usage();
        } elseif (Val::is($reply) && property_exists($reply, 'usage')) {
            $usage = $reply->usage;
        } elseif (Val::is($reply) && method_exists($reply, 'metadata')) {
            $metadata = $reply->metadata();
            $usage = Arr::is($metadata) ? ($metadata['usage'] ?? null) : null;
        } elseif (Val::is($reply) && method_exists($reply, 'meta')) {
            $metadata = $reply->meta();
            $usage = Arr::is($metadata) ? ($metadata['usage'] ?? null) : null;
        }

        return Arr::is($usage) ? $usage : [];
    }

    protected function normalizeUsage($reply, string $prompt, string $output): array
    {
        $usage = $this->extractUsage($reply);

        $promptTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null;
        $completionTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? $usage['generated_tokens'] ?? null;
        $totalTokens = $usage['total_tokens'] ?? null;

        if ($totalTokens === null && is_numeric($promptTokens) && is_numeric($completionTokens)) {
            $totalTokens = (int)$promptTokens + (int)$completionTokens;
        }

        return [
            'prompt_tokens' => is_numeric($promptTokens) ? (int)$promptTokens : null,
            'completion_tokens' => is_numeric($completionTokens) ? (int)$completionTokens : null,
            'total_tokens' => is_numeric($totalTokens) ? (int)$totalTokens : null,
            'estimated_prompt_tokens' => $this->estimateTokens($prompt),
            'estimated_completion_tokens' => $this->estimateTokens($output),
            'estimated_total_tokens' => $this->estimateTokens($prompt) + $this->estimateTokens($output),
        ];
    }

    protected function recordUsage(Element $element, array $metadata, array $usage, string $output, bool $accepted): void
    {
        $entry = [
            'element' => $metadata['element'] ?? $this->resolveElementName($element),
            'placeholder' => $metadata['placeholder'] ?? $element->getTag(),
            'label' => $metadata['label'] ?? null,
            'phase' => $metadata['phase'] ?? null,
            'chapter' => $metadata['chapter'] ?? null,
            'section' => $metadata['section'] ?? null,
            'profile' => $metadata['profile'] ?? null,
            'attempt' => $metadata['attempt'] ?? 1,
            'retries' => $metadata['retries'] ?? 1,
            'thread' => $metadata['thread'] ?? null,
            'session' => $metadata['session'] ?? null,
            'context' => $metadata['context'] ?? [],
            'accepted' => $accepted,
            'output' => $output,
            'usage' => $usage,
        ];

        $this->usageLedger[$element->getUuid()][] = $entry;

        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens', 'estimated_prompt_tokens', 'estimated_completion_tokens', 'estimated_total_tokens'] as $key) {
            if (isset($usage[$key]) && is_numeric($usage[$key])) {
                $this->usageTotals[$key] += (int)$usage[$key];
            }
        }
    }

    protected function gatherPromptContext(Element $element): string
    {
        $root = $element->getRoot();
        $context = $root ? $root->getContent() : '';
        $match = $element->getMatch();

        if (Str::is($context) && Str::is($match)) {
            $position = Str::pos($context, $match);
            if ($position !== false) {
                return (string)Str::sub($context, 0, $position);
            }
        }

        return '';
    }

    protected function resolveElementName(Element $element): string
    {
        $name = $element->getAttribute('name');
        if (Str::is($name) && Str::trim($name) !== '') {
            return (string)$name;
        }

        return $element->getTag() . '_' . substr(md5($element->getUuid()), 0, 8);
    }

    protected function resolveProfile(Element $element): array
    {
        $profile = $element->getAttribute('profile');
        if (!$profile) {
            return [
                'name' => null,
                'driver' => $this->llm,
                'prompt' => '',
            ];
        }

        if (!$this->profileResolver) {
            throw new Exception("No profile resolver configured for '{$profile}'.");
        }

        $resolved = call_user_func($this->profileResolver, (string)$profile, $element);
        if (!Arr::is($resolved)) {
            throw new Exception("Profile resolver must return an array for '{$profile}'.");
        }

        $resolved['name'] = $resolved['name'] ?? (string)$profile;
        $resolved['driver'] = $resolved['driver'] ?? $this->llm;
        $resolved['prompt'] = $resolved['prompt'] ?? '';

        return $resolved;
    }

    protected function consumeReply($reply, string $target, Element $element, ?array $options, ?string $pattern): void
    {
        if (!isset($this->buffer[$target]) || $this->buffer[$target] !== '') {
            return;
        }

        if (!$reply instanceof Reply) {
            return;
        }

        $messages = $reply->messages()->toArray();
        $last = end($messages);
        if (!Str::is($last) || Str::trim($last) === '') {
            return;
        }

        $output = Str::trim($last);
        if (!empty($options) && !$this->matchPrefixOption($output, $options) && !in_array($output, $options, true)) {
            return;
        }

        if (!empty($pattern) && !preg_match($pattern, $output)) {
            return;
        }

        $this->buffer[$target] = $output;
    }
}
