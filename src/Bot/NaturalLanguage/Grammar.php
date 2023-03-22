<?php
namespace BlueFission\Bot\NaturalLanguage;

use BlueFission\Bot\Collections\OrganizedCollection as Collection;

class Grammar
{
    private $rules;
    private $commands;
    private $tokens;
    private $index;

    public function __construct()
    {
        $this->rules = [];
        $this->commands = [];
        $this->tokens = [];
        $this->index = 0;
    }

    public function addRule($nonterminal, $terminal)
    {
        if (!isset($this->rules[$nonterminal])) {
            $this->rules[$nonterminal] = [];
        }
        $this->rules[$nonterminal][] = $terminal;
    }

    public function addCommand($terminal, $name, $values)
    {
        if (!isset($this->commands[$terminal])) {
            $this->commands[$terminal] = [];
        }
        $this->commands[$terminal][$name] = $values;
    }

    public function addTerm($term, $classification)
    {
        $this->tokens[$term] = $classification;
    }

    public function tokenize($input)
{
	    // self::prepare();

	    $classifications = [];
	    $expected = [];
	    $next = [];
	    $buffer = '';
	    $output = [];
	    $currentSegment = '';
	    $line = 1;

	    $tokens = mb_str_split($input);
	    $inputLength = count($tokens);

	    $j = 0;
	    for ($i = 0; $i < $inputLength; $i++) {
	        $current = $tokens[$i];

	        if ($current === "\n") {
	            $line++;
	        }

	        $currentSegment .= $current;
	        foreach ($this->tokens as $term => $classification) {
	            $classifications = [];


	            if ($currentSegment == $term) {
	            	if (!is_array($classification)) {
	            		$classification = [$classification];
	            	}
	                foreach ($classification as $class) {
	                    if (count($next) < 1 || in_array($class, $next)) {
	                        $classifications[] = $class;
	                    }
	                }

	                if (count($classifications) < 1) {
	                    // throw new \Exception("Undefined input '{$currentSegment}' on line, {$line}.", 1);
	                }

	                $new = true;
	                foreach ($classifications as $classification) {
	                    $expectation = $this->commands[$classification];
	                    $guess = $expectation ? $expectation : [];
	                    if ($guess && isset($guess['expects'])) {
	                        if ($guess['expects'][0] != 'C_PREVIOUS') {
	                            if ($new) {
	                                $next = [];
	                                $expected = [];
	                                $new = false;
	                            }

	                            $expected[$classification] = $guess['expects'];
	                            $next = array_merge($next, $expected[$classification]);
	                        } else {
	                            for ($k = 0; $k < count($next); $k++) {
	                                $or = strpos($next[$k], '|');
	                                if ($or) {
	                                    $expects = explode('|', $next[$k]);
	                                    for ($l = 0; $l < count($expects); $l++) {
	                                        if ($expects[$l] == $classification) {
	                                            unset($expects[$l]);

	                                            $next[$k] = implode('|', $expects);
	                                        }
	                                    }
	                                }
	                            }
	                        }
	                    }
	                }

	                if (count($classifications)) {
	                    $output[$j] = ['classifications' => $classifications, 'token' => '', 'expects' => $expected, 'match' => $currentSegment, 'line' => $line];
	                    $currentSegment = '';
	                    $j++;
	                }
	            }
	        }
	    }

	    return $output;
	}


    public function parse($tokens)
    {
        $this->index = 0;
        return $this->parseNonterminal('T_DOCUMENT', $tokens);
    }

    private function parseNonterminal($nonterminal, $tokens)
	{
	    if (!isset($this->rules[$nonterminal])) {
	        throw new \Exception("Unknown nonterminal: $nonterminal");
	    }

	    $originalIndex = $this->index;
	    $node = ['type' => $nonterminal, 'children' => []];

	    echo "Rules\n";
	    var_dump($this->rules[$nonterminal]);

	    foreach ($this->rules[$nonterminal] as $rule) {
	        $node['children'] = [];
	        $this->index = $originalIndex;
	        $matched = true;

	        foreach ($rule as $element) {
	            $optional = false;

	            if (is_array($element)) {
	                $optional = in_array('optional', $element);
	                $element = array_filter($element, function ($e) {
	                    return $e !== 'optional';
	                });
	                $element = array_values($element);
	            }

	            if (is_array($element)) {
	                $element = $element[0];
	            }
	            if (strpos($element, '|') !== false) {
	                $element = explode('|', $element)[0];
	            }

	            var_dump($tokens[$this->index]['classifications']);
	            var_dump($element);
	            if (isset($tokens[$this->index]) && in_array($element, $tokens[$this->index]['classifications'])) {
	            	die(var_dump('test'));
	                $node['children'][] = [
	                    'type' => $element,
	                    'value' => $tokens[$this->index]['term']
	                ];
	                $this->index++;
	            } elseif (isset($this->rules[$element])) {
	            	die(var_dump('test2'));

	                $child = $this->parseNonterminal($element, $tokens);

	                if ($child) {
	                    $node['children'][] = $child;
	                } elseif (!$optional) {
	                    $matched = false;
	                    break;
	                }
	            } elseif (!$optional) {
	            	die(var_dump('test3'));

	                $matched = false;
	                break;
	            }
	        }

	        if ($matched) {
	            return $node;
	        }
	    }

	    return null;
	}

}