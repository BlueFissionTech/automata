<?php
namespace BlueFission\Bot\NaturalLanguage;

use BlueFission\Bot\NaturalLanguage\StemmerLemmatizer;
use BlueFission\Bot\Collections\OrganizedCollection as Collection;

class Grammar
{
    private $rules;
    private $commands;
    private $tokens;
    private $index;
    private $depth;
    private $previous;
    private $stemmer;

    protected static $pos = [
		'not'=>'T_NEGATION', // Not, no
		'pre'=>'T_PREFIX', // Word parts at the start
		'ind'=>'T_INDICATOR', //Particles like "the"
		'sym'=>'T_SYMBOL', // Names, words, meaning packages
		'ent'=>'T_ENTITY', // Objects, properties, nouns
		'ref'=>'T_ALIAS', // Pronouns and place holders
		'opr'=>'T_OPERATOR', // Actions, verbs
		'des'=>'T_DESCRIPTOR', // Adjectives/qualities
		'det'=>'T_DETERMINER', // Amounts, quantities
		'dir'=>'T_DIRECTOR', // Prepositions
		'mod'=>'T_MODIFIER', // Adverbs and words that express the mode of something
		'suf'=>'T_SUFFIX', // Word parts at the end
		'spc'=>'T_WHITESPACE', // Word and expression boundaries
		'con'=>'T_CONJUNCTION', // and, but, then
		'int'=>'T_INTERJECTION',
		'pun'=>'T_PUNCTUATION',
	];

    public function __construct( StemmerLemmatizer $stemmer, $rules = null, $commands = null, $tokens = null )
	{

        $this->rules = [];
        $this->commands = [];
        $this->tokens = [];
        $this->index = 0;
        $this->depth = 0;
        $this->previous = "";
        $this->stemmer = $stemmer;

		if ($rules) {
			foreach ( $rules as $terminal=>$rule ) {
				foreach ( $rule as $elements) {
					$this->addRule($terminal, $elements);
				}
			}
		}
		if ($commands) {
			foreach ( $commands as $terminal=>$command ) {
				foreach ( $command as $name=>$values) {
					$this->addCommand($terminal, $name, $values);
				}
			}
		}
		if ($tokens) {
			foreach ( $tokens as $term=>$classification ) {
				foreach ($classification as $class) {
					$this->addTerm($term, $class);
				}
			}
		}
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
        $this->tokens[$term][] = $classification;
    }

    public function tokenize($input)
	{
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
	            // if (substr($currentSegment, -strlen($term)) === $term) {
	            	if (!is_array($classification)) {
	            		$classification = [$classification];
	            	}
	                foreach ($classification as $class) {
	                    if (count($next) < 1 || in_array($class, $next)) {
	                        $classifications[] = $class;
	                    }
	                }

	                if (count($classifications) < 1) {
	                    throw new \Exception("Undefined input '{$currentSegment}' on line, {$line}.", 1);
	                }

			        $term = $this->stemmer->lemmatize($term);

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
	                    $output[$j] = ['classifications' => $classifications, 'token' => $currentSegment, 'expects' => $expected, 'match' => $term, 'line' => $line];
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
		if ($this->depth == 0)
			// var_dump($tokens);
	    if (!isset($this->rules[$nonterminal])) {
	        throw new \Exception("Unknown nonterminal: $nonterminal");
	    }

	    if ($this->depth > 10) return;

	    $originalIndex = $this->index;
	    $node = ['type' => $nonterminal, 'children' => []];
	    // echo "depth: {$this->depth}\n";
    	// echo "Iteration {$this->index} of " . count($tokens) . "\n";

	    foreach ($this->rules[$nonterminal] as $rule) {
	    	// echo "Trying rule: $nonterminal\n";

	        $node['children'] = [];
	        $this->index = $originalIndex;
	        $matched = $this->processRule($rule, $tokens, $node);

	        if ($matched) {
	            return $node;
	        }
	    }
	    $node = $this->pruneHangingBranches($node);

	    return $node;
	}

	private function processRule($rule, $tokens, &$node)
	{
	    $matched = true;

	    foreach ($rule as $elements) {
	        $optional = false;
	        $isCluster = false;

	        if (!is_array($elements)) {
	            $elements = [$elements];
	        } else {
	        	// TODO: Add logic for element clusters
	        	$isCluster = true;
	        }

	        $matched = $this->processElements($elements, $tokens, $node, $optional, $isCluster);
	        if (!$matched) {
	        	break;
	        }
	    }

	    return $matched;
	}

	private function processElements($elements, $tokens, &$node, &$optional, $isCluster)
	{
	    foreach ($elements as $element) {
	    	// echo "Trying element: $element\n";

	        if (!isset($tokens[$this->index])) return false;
	        
	        foreach ($tokens[$this->index]['classifications'] as $classification) {
	            if (!$this->processElement($element, $classification, $tokens, $node, $optional)) {
	                return false;
	            }
	        }
	    }

	    return true;
	}

	private function processElement($element, $classification, $tokens, &$node, &$optional)
	{
	    if (strpos($element, '|') !== false) {
	        $element = explode('|', $element)[0];
	        $optional = true;
	    }

	    // echo "Classification: $classification\n";
	    $previous = $this->previousSibling($node['children'], count($node['children']));
	    $previous = $previous ? $previous['type'] : null;

	    if (!$this->isStructureAllowed($previous, $element)) {
	    	// echo "Not allowed: $previous -> $element\n";

	        return false;
	    }

	    return $this->processClassification($classification, $element, $tokens, $node, $optional);
	}

	private function processClassification($classification, $element, $tokens, &$node, $optional)
	{
		// echo "Token: '" . $tokens[$this->index]['match'] . "'\n";
	    if ($classification == 'T_WHITESPACE') {
	        $this->index++;
			// echo "Token switched to: '" . $tokens[$this->index]['match'] . "'\n";

	        // return true;
	    }

	    if (isset($tokens[$this->index]) && $element == $classification) {
	    	// echo "Matched: $classification -> $element\n";

	        if ($this->isExpected($this->previous, $element)) {
	            $node['children'][] = [
	                'type' => $element,
	                'value' => $tokens[$this->index]['match']
	            ];
	            $this->index++;
	            $this->previous = $element;
	            return true;
			}
		} elseif (isset($this->rules[$element])) {
			$this->depth++;
			$child = $this->parseNonterminal($element, $tokens);
			$this->depth--;
		    $node['children'][] = $child;
		} elseif (!$optional) {
		    return false;
		}

		return true;
	}

	private function previousSibling($children, $index)
	{
	    if ($index > 0 && isset($children[$index - 1])) {
	        return $children[$index - 1];
	    }
	    return null;
	}

	private function isExpected($classification, $element)
	{
	    if (isset($this->commands[$classification]) && isset($this->commands[$classification]['expects'])) {
	        return in_array($element, $this->commands[$classification]['expects']);
	    }
	    return true;
	}

	// Add the method to check for illegal structures
    private function isStructureAllowed($previous, $current)
    {
        $disallowedStructures = [
            ['T_ENTITY', 'T_NOUN_PHRASE'],
            ['T_ENTITY', 'T_PUNCTUATION'],
            ['T_OPERATOR', 'T_OPERATOR'],
            ['T_STATEMENT', 'T_STATEMENT'],
        ];

        foreach ($disallowedStructures as $structure) {
            if ($previous === $structure[0] && $current === $structure[1]) {
                return false;
            }
        }

        return true;
    }

	private function pruneHangingBranches($node)
	{
	    $prunedChildren = [];

	    foreach ($node['children'] as $child) {
	        if (!empty($child) && isset($this->rules[$child['type']])) {
	            $child = $this->pruneHangingBranches($child);
	        }

	        if (!empty($child) && isset($child['children']) && count($child['children']) > 0) {
	            $prunedChildren[] = $child;
	        } elseif (empty($child) || !isset($this->rules[$child['type']])) {
	            $prunedChildren[] = $child;
	        }
	    }

	    $node['children'] = $prunedChildren;
	    return $node;
	}

}