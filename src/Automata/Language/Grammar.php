<?php
namespace BlueFission\Automata\Language;

use BlueFission\Automata\Language\StemmerLemmatizer;
use BlueFission\Automata\Collections\OrganizedCollection as Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Automata\Language\Token;

class Grammar
{
    private $rules;
    private $commands;
    private $tokens;
    private $index;
    private $depth;
    private $previous;
    private $stemmer;
    private bool $statementBoundaries = false;
    private string $statementBoundaryToken = Token::STATEMENT;
    private ?string $indentToken = null;

    public function __construct( StemmerLemmatizer $stemmer, $rules = null, $commands = null, $tokens = null )
	{

        $this->rules = [];
        $this->commands = [];
        $this->tokens = [];
        $this->index = 0;
        $this->depth = 0;
        $this->previous = "";
        $this->stemmer = $stemmer;
        Dev::do('language.grammar.construct', [
            'stemmer' => $stemmer,
            'rules' => $rules,
            'commands' => $commands,
            'tokens' => $tokens,
        ]);

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

    public function enableStatementBoundaries(bool $enabled = true, string $token = null, string $indentToken = null): void
    {
        $this->statementBoundaries = $enabled;
        if ($token !== null) {
            $this->statementBoundaryToken = $token;
        }
        $this->indentToken = $indentToken;
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
	    $segment = '';
	    $currentSegment = '';
	    $line = 1;
	    $matching = false;
	    $index = 0;
	    $count = 0;

        $input = Dev::apply('language.grammar.tokenize_input', $input);
        $input = ContractionNormalizer::normalize($input);
        $tokens = mb_str_split($input);
	    $inputLength = count($tokens);

	    $puntuations = ['.', ',', '!', '?', ';', ':', '(', ')', '[', ']', '{', '}', '<', '>'];

	    $j = 0;
	    for ($i = 0; $i < $inputLength; $i++) {
	        $current = $tokens[$i];

	        if ($current === "\n") {
	            $line++;
                if ($this->statementBoundaries) {
                    $output[$j] = [
                        'classifications' => [$this->statementBoundaryToken],
                        'token' => $current,
                        'expects' => $expected,
                        'match' => $current,
                        'line' => $line,
                    ];
                    $j++;

                    $expected = [];
                    $next = [];
                    $segment = '';
                    $currentSegment = '';

                    if ($this->indentToken) {
                        $indent = '';
                        $k = $i + 1;
                        while ($k < $inputLength && ($tokens[$k] === ' ' || $tokens[$k] === "\t")) {
                            $indent .= $tokens[$k];
                            $k++;
                        }

                        if ($indent !== '') {
                            $output[$j] = [
                                'classifications' => [$this->indentToken],
                                'token' => $indent,
                                'expects' => $expected,
                                'match' => $indent,
                                'line' => $line,
                            ];
                            $j++;
                            $i = $k - 1;
                        }
                    }

                    continue;
                }
	        }

	        $segment .= $current;
	        // echo "current: $current\n";
	        $count = 0;
	        $matches = [];
	        $matching = false;
	        foreach ($this->tokens as $term => $classification) {
	            if ( strpos($term, $segment) === 0 ) {
	            	// echo "term: $term\n";
            		// var_dump($segment);

            		if (isset($tokens[$i+1]) && (preg_match('/^\S+$/', $tokens[$i+1]) && !in_array($tokens[$i+1], $puntuations))) {
		            	$count++;
		            	$matches[] = $term;
		            }

	            	if ($segment == $term) {
	            		$classifications = [];
	            		$matching = true;
	            		$currentSegment = $segment;

		            	if (!is_array($classification)) {
		            		$classification = [$classification];
		            	}
		                foreach ($classification as $class) {
		                	$options = [];
		                	foreach ($next as $nextClass) {
		                		if (strpos($nextClass, '|')) {

		                			$options = array_merge($options, explode('|', $nextClass));
		                		} else {
		                			$options[] = $nextClass;
		                		}
		                	}

		                    if (count($options) < 1 || in_array($class, $options) || $this->classificationMatchesAlias($class, $options)) {
		                		// echo "working here: $class!";
		                        $classifications[] = $class;
		                    }
		                }
	            	}
	            }
	        }

            if (!$matching && ($current === ' ' || $current === "\t" || $current === "\r")) {
                $segment = '';
                $currentSegment = '';
                continue;
            }

	        if ($count <= 1 && $matching == true) {
	            	// echo "accepted\n";
	            	// var_dump($segment);
	            	// if ($currentSegment != " ") {
	            	// 	die($currentSegment);
	            	// }

	            // if (substr($currentSegment, -strlen($term)) === $term) {
	                // var_dump($classifications);

	                if (count($classifications) < 1) {
	                	$suggestion = $this->findSimilarWord($currentSegment, $next);
			        	if ( $suggestion ) {
			        		throw new \Exception("Unexpected input '{$currentSegment}'. Did you mean '{$suggestion}'?", 1);
						} else {
	                    	throw new \Exception("Undefined input '{$currentSegment}' on line, {$line}.", 1);
						}
	                }

			        $term = $this->stemmer->lemmatize($currentSegment);

	                $new = true;
	                foreach ($classifications as $classification) {
	                    $expectation = $this->commands[$classification];
	                    $guess = $expectation ? $expectation : [];
	                    if ($guess && isset($guess['expects']) && is_array($guess['expects'])) {
	                        $expects = $guess['expects'];
	                        if (isset($expects[0]) && $expects[0] != 'C_PREVIOUS') {
	                            if ($new) {
	                                $next = [];
	                                $expected = [];
	                                $new = false;
	                            }

	                            $expected[$classification] = $expects;
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
                        $classifications = $this->expandClassifications($classifications);
	                    $output[$j] = ['classifications' => $classifications, 'token' => $currentSegment, 'expects' => $expected, 'match' => $term, 'line' => $line];
	                    $currentSegment = '';
	                    $j++;
	                }
	                $currentSegment = '';
	                $segment = '';
	            }
	        // }
	    }

        $output = Dev::apply('language.grammar.tokenize_output', $output);
        Dev::do('language.grammar.tokenize_complete', [
            'input' => $input,
            'output' => $output,
        ]);
        return $output;
	}


    public function parse($tokens)
    {
        $tokens = Dev::apply('language.grammar.parse_tokens', $tokens);
        Dev::do('language.grammar.parse_start', ['tokens' => $tokens]);
        $node = $this->parseNonterminal('T_DOCUMENT', $tokens);
        $node = Dev::apply('language.grammar.parse_result', $node);
        Dev::do('language.grammar.parse_complete', ['node' => $node]);
        $this->index = 0;
        return $node;
    }

	private function parseNonterminal($nonterminal, $tokens)
	{
		if ($this->depth == 0)
			// var_dump($tokens);
	    if (!isset($this->rules[$nonterminal])) {
	        throw new \Exception("Unknown nonterminal: $nonterminal");
	    }

	    if ($this->depth > 5) return;

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
	    if ($classification == 'T_WHITESPACE' || $classification == 'T_SUFFIX') {
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

    private function classificationMatchesAlias(string $classification, array $options): bool
    {
        if (empty($options)) {
            return false;
        }

        $aliases = $this->aliasClasses($classification);
        if (empty($aliases)) {
            return false;
        }

        return count(array_intersect($aliases, $options)) > 0;
    }

    private function expandClassifications(array $classifications): array
    {
        $expanded = $classifications;
        foreach ($classifications as $classification) {
            $aliases = $this->aliasClasses($classification);
            if (!empty($aliases)) {
                $expanded = array_merge($expanded, $aliases);
            }
        }

        return array_values(array_unique($expanded));
    }

    private function aliasClasses(string $classification): array
    {
        $aliases = [];
        $command = $this->commands[$classification] ?? null;

        if (is_array($command) && isset($command['aliasOf'])) {
            $aliasOf = $command['aliasOf'];
            if (is_string($aliasOf)) {
                $aliases[] = $aliasOf;
            } elseif (is_array($aliasOf)) {
                $aliases = array_merge($aliases, $aliasOf);
            }
        }

        if (is_array($command) && isset($command['soft'])) {
            $soft = $command['soft'];
            if ($soft === true) {
                $aliases[] = Token::SYMBOL;
            } elseif (is_string($soft)) {
                $aliases[] = $soft;
            } elseif (is_array($soft)) {
                $aliases = array_merge($aliases, $soft);
            }
        }

        return array_values(array_unique($aliases));
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

	private function findSimilarWord($input, $expectedPos)
    {
        $bestMatch = "";
        $bestSimilarity = 0;

        foreach ($this->tokens as $term => $classification) {
            if (count(array_intersect($classification, $expectedPos) ) > 0) {
                similar_text($input, $term, $similarity);
                if ($similarity > $bestSimilarity) {
                    $bestSimilarity = $similarity;
                    $bestMatch = $term;
                }
            }
        }

        return $bestMatch;
    }
}
