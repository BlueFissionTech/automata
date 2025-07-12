<?php
namespace BlueFission\Automata\LLM;

use BlueFission\Behavioral\IDispatcher;
use BlueFission\Behavioral\Dispatches;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Behavioral\Behaviors\Meta;
// Inspired by microsoft/guidance
// https://towardsdatascience.com/the-art-of-prompt-design-use-clear-syntax-4fc846c1ebd5
// https://towardsdatascience.com/the-art-of-prompt-design-prompt-boundaries-and-token-healing-3b2448b0be38

// TODO: implement token healing
class FillIn implements IDispatcher
{
    use Dispatches {
        Dispatches::__construct as private __dispatchesConstruct;
    }

    const OPERATOR_EQUALS = '==';
    const OPERATOR_NOT_EQUALS = '!=';
    const OPERATOR_GREATER_THAN = '>';
    const OPERATOR_LESS_THAN = '<';
    const OPERATOR_GREATER_THAN_EQUALS = '>=';
    const OPERATOR_LESS_THAN_EQUALS = '<=';

    private $llm;
    private $prompt;
    private $originalPrompt;
    private $placeholders = [];
    private $placeholderNames = [];
    private $vars = [];
    private $conditions = [];
    private $loops = [];
    private $tools = [];
    private $loopGlue = "";
    private $iteratedData = [];
    private $iteratedMax = 0;
    private $iteratedContent = "";
    private $iterationAssignment;

    public function __construct($llm, $prompt)
    {
        $this->__dispatchesConstruct();

        $this->llm = $llm;
        $this->setPrompt($prompt);
    }

    public function setPrompt($prompt)
    {
        $this->prompt = $prompt;
        $this->originalPrompt = $prompt;
        $this->placeholders = [];
        $this->placeholderNames = [];
        $this->extractPlaceholders();
    }

    public function addTool($name, ITool $tool)
    {
        $this->tools[$name] = $tool;
    }

    private function invokeTool($name, $params)
    {
        if (isset($this->tools[$name])) {
            $tool = $this->tools[$name];
            return $tool->execute($params);
        } else {
            throw new \Exception("Tool '$name' not found.");
        }
    }

    private function extractPlaceholders()
    {
        // Extract placeholders
        if(preg_match_all("/\{=(.*?)\}/", $this->prompt, $matches)) {
            foreach($matches[1] as $placeholder){
                // Extract placeholder options
                preg_match("/([^[\s]+)(?:\[(.*?)\])?/", $placeholder, $optionMatches);
                if (!empty($optionMatches)) {
                    $this->placeholderNames[] = $optionMatches[1];
                    $this->placeholders[] = $placeholder;
                }
            }
        }
    }

    private function handleVariables(&$chunk)
    {
        if(preg_match_all("/\{#var (.*?) = (.*?)\}/", $chunk, $matches)) {
            $this->vars = array_merge($this->vars, array_combine($matches[1], $matches[2]));
            $chunk = preg_replace("/\{#var (.*?) = (.*?)\}/", "", $chunk);
        }

        // Process variables, conditions, and loops directly within the run method
        foreach($this->vars as $var => $value) {
            // Handle array variables
            if (is_array($value)) {
                $value = implode(", ", $value);
            }

            // Also replace variables in conditions and loops
            foreach($this->conditions as &$condition) {
                $condition['condition'] = str_replace($var, $value, $condition['condition']);
                $condition['value'] = str_replace($var, $value, $condition['value']);
                $condition['content'] = str_replace($var, $value, $condition['content']);
            }
        }
    }

    private function handleConditions(&$chunk, &$data)
    {
        if(preg_match_all("/\{#if\((.*?)([!=<>]+)(.*?)\)\}(.*?)\{#endif\}/s", $chunk, $matches)) {
            $this->conditions = array_map(function($condition, $operator, $value, $content) {
                return ['condition' => $condition, 'operator' => $operator, 'value' => $value, 'content' => $content];
            }, $matches[1], $matches[2], $matches[3], $matches[4]);
            $condition = $matches[1][0];
            $operator = $matches[2][0];
            $value = $matches[3][0];

            if (strpos($condition, "'") === false && strpos($condition, "\"") === false) {
                $condition = $data[$condition] ?? $this->vars[$condition] ?? null;
            }

            if (strpos($value, "'") === false && strpos($value, "\"") === false) {
                $value = $data[$value] ?? $this->vars[$value] ?? null;
            }

            $condition = trim($condition, "'\"");
            $value = trim($value, "'\"");

        } elseif(preg_match_all("/\{#if\((.*?)([!=<>]+)(.*?)\)\}/s", $chunk, $matches)) {
            $condition = $matches[1][0];
            $operator = $matches[2][0];
            $value = $matches[3][0];
            if (strpos($condition, "'") === false && strpos($condition, "\"") === false) {
                $condition = $data[$condition] ?? $this->vars[$condition] ?? null;
            }

            if (strpos($value, "'") === false && strpos($value, "\"") === false) {
                $value = $data[$value] ?? $this->vars[$value] ?? null;
            }
            if ($condition) {
                $condition = trim($condition, "'\"");
            }
            $value = trim($value, "'\"");
            if ( !$this->parseCondition($condition, $value, $operator) ) {
                // $data[$placeholderName] = "";
                // $i++;
                // continue;
            }

            return $chunk;
        }


        foreach ($this->conditions as $condition) {
            $conditionValue = $data[$condition['condition']] ?? $this->vars[$condition['condition']] ?? null;

            $conditionValue = trim($conditionValue, "'\"");
            $condition['value'] = trim($condition['value'], "'\"");
            if ( $this->parseCondition($conditionValue, $condition['value'], $condition['operator']) ) {
                $chunk = preg_replace("/\{#if\((.*?)([!=<>]+)(.*?)\)\}(.*?)\{#endif\}/s", $condition['content'], $chunk);
            } else {
                $chunk = preg_replace("/\{#if\((.*?)([!=<>]+)(.*?)\)\}(.*?)\{#endif\}/s", "", $chunk);
            }
        }
    }

    private function handleLoops(&$chunk, &$data, $index, $position, $isLooping)
    {
        $startLoop = true;
        if ($isLooping) {
            $max = count($this->iteratedData) > 0 ? count($this->iteratedData) : $this->iteratedMax-1;
            if ($index >= $max) {
                $isLooping = false;
                $startLoop = false;
            }
        } else {
            $this->iteratedContent = null;
            $this->iterationAssignment = "";
            $this->iteratedData = [];
            $this->iteratedMax = 0;
            $index = 0;
        }

        if(preg_match_all("/\{#each\s*((?:\[[^\]]*\]|[^}]+))\}(.*?)\{#endeach\}/s", $chunk, $matches)) {

            $chunk = preg_replace("/\{#each\s*((?:\[[^\]]*\]|[^}]+))\}(.*?)\{#endeach\}/s", $matches[2][0], $chunk);

        } elseif($startLoop && preg_match_all("/\{#each\s*((?:\[[^\]]*\]|[^}]+))\}(.*?)$/s", $chunk, $matches)) {
            $rules = $matches[1][0];
            $this->iteratedContent ??= $matches[2][0];
            $var = "";
            $value = "";
            if (strpos($rules, "[") === 0) {
                $options = trim($rules, '[]');
                $options = $this->parseOptions($options);
            } else {
                $vars = explode('=', $rules);
                $var = trim($vars[0]);
                $value = trim($vars[1]);
            }

            if (isset($options)) {
                $this->iteratedMax = $options['iterations'];
                $this->loopGlue = $options['glue'];
                $this->iterationAssignment = $this->placeholderNames[$position];
            } elseif ($value !== "") {
                if (strpos($value, "'") === false && strpos($value, "\"") === false) {
                    $value = $data[$value] ?? $this->vars[$value] ?? null;
                } else {
                    $value = trim($value, "'\"");
                }

                $this->iteratedData = (strpos($value, '[') === 0) ? $value : $this->vars[$value];
                if (preg_match("/\[(.*?)\]/", $this->iteratedData, $matches2)) {
                    $value = explode(",", trim($matches2[1]));
                    $this->iteratedData = array_map(function($v) { return trim($v, " '\""); }, $value);
                }

                $this->iterationAssignment = $var;
            }

            $render = "";
            if ( isset($this->iterationAssignment) && !isset($data[$this->iterationAssignment])) {
                $render = $this->iteratedContent;
                if (!empty($this->iteratedData)) {
                    $render = str_replace('{@current}', $this->iteratedData[$index], $render);
                }
                $render = str_replace('{@index}', $index+1, $render);

                $data[$this->iterationAssignment] = [];
            }

            $chunk = str_replace($this->iteratedContent, $render, $chunk);
            
            $isLooping = true;
        }
        return $isLooping;
    }

    private function parseOptions($str)
    {
        $str = preg_replace("/\s*,\s*/", ",", $str); // Remove spaces surrounding commas
        $str = preg_replace("/\s*\:\s*/", ":", $str); // Remove spaces surrounding colons
        
        // Matches key-value pairs. This pattern considers the options inside single quotes, brackets and commas.
        preg_match_all("/(\w+):'([^']*)'|\d|(\w+):([^']*)|\[(.*?)\]/", $str, $matches);

        $options = [];

        // Group the matches into key-value pairs
        for ($i = 0; $i < count($matches[0]); $i++) {

            $refValue = null;
            $rawValue = explode(':', $matches[0][$i])[1] ?? '';
            if ($this->isValidVariableName($rawValue)) {
                $refValue = $this->getVariableValue($rawValue);
            }

            if(!empty($matches[1][$i])) {
                $options[$matches[1][$i]] = $matches[3][$i] != "" ? $matches[3][$i] : ($refValue ?? $matches[2][$i]);
                if (preg_match("/\[(.*?)\]/", $options[$matches[1][$i]], $matches2)) {
                    $value = explode(",", trim($matches2[1]));
                    $options[$matches[1][$i]] = array_map(function($v) { return trim($v, " '\""); }, $value);
                }
            } else {
                $value = $refValue ?? $matches[4][$i];
                $options['options'] = explode(',', str_replace('\'', '', $value));
            }
        }
        if (isset($options['stop'])) {
            $options['stop'] = str_replace('\n', "\n", $options['stop']);
        }

        return $options;
    }

    private function parseCondition($left, $right, $operator = self::OPERATOR_EQUALS)
    {
        switch($operator)
        {
            default:
            case self::OPERATOR_EQUALS:
                return $left == $right;
            case self::OPERATOR_NOT_EQUALS:
                return $left != $right;
            case self::OPERATOR_GREATER_THAN:
                return $left > $right;
            case self::OPERATOR_LESS_THAN:
                return $left < $right;
            case self::OPERATOR_GREATER_THAN_EQUALS:
                return $left >= $right;
            case self::OPERATOR_LESS_THAN_EQUALS:
                return $left <= $right;
        }
    }

    private function getNextChunk($index)
    {
        if($index >= count($this->placeholders)) {
            return '';
        }

        $placeholder = $this->placeholders[$index];
        $parts = explode("{=$placeholder}", $this->originalPrompt);

        if (isset($parts[1])) {
            $this->originalPrompt = implode("{=$placeholder}", array_slice($parts, 1));
        }

        return $parts[0];
    }

    private function getLastGeneratedData($data, $placeholderName)
    {
        $output = "";
        if ( $placeholderName && isset($data[$placeholderName]) )
        {
            if (is_array($data[$placeholderName])) {
                $output = end($data[$placeholderName]);
            } else {
                $output = $data[$placeholderName];
            }
        }
        return $output;
    }

    private function addLoopData($index, $data)
    {
        $render = "";
        if ($this->iteratedMax > 0) {
            // $render = implode($this->loopGlue, end($data));
            $placeholder = end($data);
            $render = end($placeholder);
        } elseif ($index < count($this->iteratedData)) {
            $render = $this->iteratedContent;
            $render = str_replace('{@current}', $this->iteratedData[$index], $render);
            $render = str_replace('{@index}', $index+1, $render);
        }
        return $render;
    }

    private function isValidVariableName($term): bool
    {
        // Check if term is not quoted, started with a letter or underscore, and contains only alphanumeric characters or underscores
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $term) && !preg_match('/["\']/', $term);
    }

    private function getVariableValue($name)
    {
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        } elseif (isset($this->data[$name])) {
            return $this->data[$name];
        } else {
            throw new \Exception("Variable '$name' not found.");
        }
    }


    public function run($config = [])
    {
        $data = [];
        $chunk = "";
        $isLooping = false;
        $index = 0;

        $validConfigs = [
            'prompt',
            'model',
            'max_tokens',
            'temperature',
            'top_p',
            'frequency_penalty',
            'presence_penalty',
            'stop'
        ];

        $i = 0;
        while($i < count($this->placeholders)) {
            $placeholder = $this->placeholders[$i];
            $lastPlaceHolderName = $placeholderName ?? null;
            $placeholderName = $this->placeholderNames[$i];

            if ($isLooping) { // If we are isLooping over an array
                // Here we append the previously generated data to the last chunk
                $chunk .= $this->getLastGeneratedData($data, $lastPlaceHolderName);
                $chunk .= $this->addLoopData($index, $data);
            } else {
                // Get the next chunk for generation
                $chunk .= $this->getLastGeneratedData($data, $lastPlaceHolderName);
                $chunk .= $this->getNextChunk($i);
            }

            // Process variables
            $this->handleVariables($chunk);

            // Process conditions
            $this->handleConditions($chunk, $data);

            // Run loops
            $isLooping = $this->handleLoops($chunk, $data, $index, $i, $isLooping);
            if ( $isLooping ) {
                $placeholderName = $this->iterationAssignment;
            }
            
            // Extract placeholder options
            preg_match("/([^\[]+)\[(.*)\]$/", $placeholder, $optionMatches);
            $options = [];
            $sanitizedConfig = [];
            if (!empty($optionMatches[2])) {
                $options = $this->parseOptions($optionMatches[2]);
                if ( isset( $options['max_tokens'] ) ) {
                    $options['max_tokens'] = (int) $options['max_tokens'];
                }
            }

            // Merge the passed config and the options from the placeholder
            $newConfig = array_merge($config, $options);

            foreach ($validConfigs as $validConfig) {
                if (isset($newConfig[$validConfig])) {
                    $sanitizedConfig[$validConfig] = $newConfig[$validConfig];
                }
            }

            // Append the option list to the chunk if options are present
            $prompt = $chunk;
            $prompt = preg_replace("/\{#each\s*((?:\[[^\]]*\]|[^}]+))\}/s", "", $prompt);
            $prompt = preg_replace("/\{#if\((.*?)([!=<>]+)(.*?)\)\}/s", "", $prompt);

            if (isset($options['options'])) {
                $prompt = $chunk." (select " . implode(", ", $options['options']) . "): ";
            }

            if (isset($options['use']) && isset($this->tools[$options['use']])) {
                $tool = $this->tools[$options['use']];
                $param = null;
                if (isset($options['param'])) {
                    $param = $options['param'];
                }

                $this->dispatch(Event::STARTED, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $placeholderName,
                ], src: $this));
                $value = $this->invokeTool($options['use'], $param);
                $this->dispatch(Event::COMPLETE, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $placeholderName,
                    'value' => $value
                ], src: $this));
                // Replace the placeholder in the prompt with the tool value
                $this->prompt = str_replace("{=$placeholder}", $value, $this->prompt);
                if (isset($data[$placeholderName]) && is_array($data[$placeholderName])) {
                    $data[$placeholderName][] = $value;
                } else {
                    $data[$placeholderName] = $value;
                }
            } else  {
                // Perform generation
                $this->dispatch(Event::SENT, new Meta(when: State::RUNNING, data: [
                    'placeholder' => $placeholderName
                ], src: $this));
                $this->llm->generate($prompt, $sanitizedConfig, function($response) use ($placeholder, $placeholderName, &$data, $sanitizedConfig) {
                    $value = trim($response);

                    $this->dispatch(Event::RECEIVED, new Meta(when: State::RUNNING, data: [
                        'placeholder' => $placeholderName,
                        'value' => $value
                    ], src: $this));

                    // If a pattern is provided in the config, match the response against the pattern
                    if (isset($config['pattern'])) {
                        if (preg_match($config['pattern'], $value, $matches)) {
                            $value = $matches[0];
                        } else {
                            $value = '';
                        }
                    }

                    // Replace the placeholder in the prompt with the generated value
                    $this->prompt = str_replace("{=$placeholder}", $value, $this->prompt);
                    if (isset($data[$placeholderName]) && is_array($data[$placeholderName])) {
                        $data[$placeholderName][] = $value;
                    } else {
                        $data[$placeholderName] = $value;
                    }

                    // If the generated value doesn't satisfy the pattern, throw an exception
                    if (isset($config['pattern']) && !preg_match($config['pattern'], $value)) {
                        throw new \Exception("Generated text for placeholder '{$placeholderName}' doesn't match the provided pattern");
                    }
                });
            }
            if (!isset($data[$placeholderName])) {
                throw new \Exception("Failed to generate text for placeholder '{$placeholderName}'");
            }

            $this->dispatch(Event::CHANGE, new Meta(when: State::RUNNING, data: [
                'placeholder' => $placeholderName,
                'value' => $data[$placeholderName]
            ], src: $this));

            if ($isLooping) { // only increase placeholder/chunk iterator if not doing internal loops
                $index++;
            } else {
                $index = 0;
                $i++;
            }
        }

        return $data;
    }
}