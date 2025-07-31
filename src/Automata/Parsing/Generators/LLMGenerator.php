<?php

namespace BlueFission\Automata\Parsing\Generators;

use BlueFission\Parsing\Contracts\IGenerator;
use BlueFission\Parsing\Element;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\Collections\Collection;
use BlueFission\Str;
use Exception;

class LLMGenerator implements IGenerator, IDispatcher {
    
    use Dispatches {
        Dispatches::__construct as private __dispatchConstruct;
    }

    protected array $buffer = [];
    protected $llm;

    public function setDriver($llm): void
    {
        $this->llm = $llm;
    }

    public function __construct() {
        $this->__dispatchConstruct();

        $this->behavior(Event::SENT);
        $this->behavior(Event::RECEIVED);
        $this->behavior(Event::ERROR);
        $this->behavior(State::RUNNING);
        $this->behavior(State::IDLE);
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
        if (!$this->llm) {
            throw new Exception("No LLM assigned to Element");
        }

        $prompt = $this->gatherPromptContext($element);

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
            'config' => $config
        ], src: $this));

        $pattern = $element->getAttribute('pattern') ?? (isset($options) && count($options) > 0 ? '/\b(' . implode('|', array_map('preg_quote', $options)) . ')\b/xi' : null);
        
        $this->llm->generate($prompt, $config, function($output) use ($config, $target, $options, $pattern, $element) {
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
    }

    protected function matchPrefixOption(string $buffer, array $options): ?string
    {
        $buffer = strtolower(trim($buffer));
        
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

        if (!$closed) {
            $element = $element;
            $match = '';
            $body = $element->getMatch();
            $context = '';
            // $context = $this->getTop()?->getContent();
            while ($element->getTag() != '__ROOT__') {
                $match = $element->getMatch();
                $tempbody = $element->getContent();
                $element = $element->getParent();
                $context = $element->getContent();
                $context = Str::replace($context, $match, $body);
                $body = $tempbody;
            }

            // split the text by the first occurence of the element
            $context = explode($element->getMatch(), $context, 2)[0] ?? '';
        }

        return (string)$context;
    }
}
