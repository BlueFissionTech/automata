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
        // Whenever this generator sends/receives/errors or flips RUNNING/IDLE,
        // mirror those events onto the parent dispatcher.
        $parent->echo($this, [
          Event::SENT,
          Event::RECEIVED,
          Event::ERROR,
          State::RUNNING,
          State::IDLE,
        ]);
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

        $retries = 5;
        $attempt = 0;

        do {
            $attempt++;
            try {
                $this->generateFromLLM($element, $config);
            } catch (Exception $e) {
                $this->dispatch(Event::ERROR, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $element->getTag(),
                    'error' => $e->getMessage()
                ], src: $this));
            }

            $output = $this->buffer[$element->getUuid()];

            if ($pattern && !preg_match($pattern, $output)) {
                // throw new Exception("Generated value '{$output}' does not match required pattern.");
                $this->dispatch(Event::ERROR, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $element->getTag(),
                    'error' => "Generated value '{$output}' does not match required pattern {$pattern}."
                ], src: $this));
                $this->buffer[$element->getUuid()] = '';
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

    protected function generateFromLLM(Element $element, array $config): void
    {
        $profile = $this->resolveProfile($element);
        $driver = $profile['driver'] ?? $this->llm;

        if (!$driver) {
            throw new Exception("No LLM assigned to Element");
        }

        $prompt = $this->gatherPromptContext($element);
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

        $this->dispatch(Event::SENT, new Meta(when: State::RUNNING, data: [
            'placeholder' => $element->getTag(),
            'config' => $config,
            'profile' => $profile['name'] ?? null,
        ], src: $this));

        $pattern = $element->getAttribute('pattern') ?? (isset($options) && count($options) > 0 ? '/\b(' . implode('|', array_map('preg_quote', $options)) . ')\b/xi' : null);

        $reply = $driver->generate($prompt, $config, function($output) use ($config, $target, $options, $pattern, $element) {
            $this->dispatch(Event::RECEIVED, new Meta(when: State::RUNNING, data: [
                'placeholder' => $element->getTag(),
                'value' => $output
            ], src: $this));

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

                // create a pattern variant that cheecks if the existiing output partially matches
                if (!empty($pattern) && preg_match($pattern, $output)) {
                    return true;
                }

                return false;
            }
        });

        $this->consumeReply($reply, $target, $element, $options, $pattern);
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

    protected function gatherPromptContext(Element $element): string
    {
        $closed = $element->getAttribute('closed') === 'true';
        if ($closed) {
            return '';
        }

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
        $this->dispatch(Event::RECEIVED, new Meta(when: State::RUNNING, data: [
            'placeholder' => $element->getTag(),
            'value' => $output
        ], src: $this));
    }
}
