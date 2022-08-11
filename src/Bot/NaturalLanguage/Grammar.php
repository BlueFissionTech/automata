<?php
namespace BlueFission\Bot\NaturalLanguage;

use BlueFission\Bot\Collections\OrganizedCollection as Collection;

// http://ogden.basic-english.org/verbs.html

/*
 Sense Routing
 	By device
 	By metainfo
 	By mime
 	By classification
 */

 	// belief
 	// desire
 	// temperament

/* 
 Grammar Expression syntax
	([ind] ent{2-3}  | alias)

 */

class Grammar {// extends DevObject {

	protected static $_syntax;

	protected static $_rules;

	protected static $_dictionary;

	protected static $_tokens = array(
		'not'=>'T_NEGATION', // Not, no
		'pre'=>'T_PREFIX', // Word parts at the start
		'ind'=>'T_INDICATOR', //Particles like "the"
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
	);

	public function __construct() {
		static::$_syntax = new Collection();
		static::$_rules = new Collection();
		static::$_dictionary = new Collection();
	}

	public static function addPattern($expression) {
		// expressions: ENTITY.last = (DETERMINER > 1 && ENTITY.last is 'y' ? 'ie' : 'y')
		// expressions: ENTITY + (DETERMINER > 1 ? 's' : '')
		// $this->_rules[] = array(
		// 	'expresson'=>'',
		// 	'conditions'=>$conditions
		// );

		static::$_syntax->add($expression, $expression);
	}

	public static function addRule($nonterminal, $terminal) {
		if ( static::$_rules->has($nonterminal) ) {
			$operation = static::$_rules->get($nonterminal);
		} else {
			$operation = new Collection();
		}
		$operation->add($terminal, $terminal);
		static::$_rules->add($operation, $nonterminal);
	}

	public static function addTerm($term, $classification)
	{
		if ( static::$_dictionary->has($term) ) {
			$definition = static::$_dictionary->get($term);
		} else {
			$definition = new Collection();
		}
		$definition->add($classification, $classification);
		static::$_dictionary->add($definition, $term);
	}

	public static function tokenize( $line ) 
	{
		$candidates = [];
		$buffer = '';
		$output = [];

		$tokens = mb_str_split($line);
		$inputLength = count($tokens);

		for ($i = 0; $i < $inputLength; $i++) {
			$current = $tokens[$i];

			/*
			if ( !$statement ) {
				$statement = new Statement();
			}

			if ( !$entity ) {
				$entity = new Entity();
			}
			*/
			
			foreach( static::$_dictionary as $term=>$definition ) {
				if ( in_array($current, $definition) ) {
					// $buffer .= $currentSegment;


					// $candidates[] = $term;
     //    			$currentSegment = '';
				}
			}
 
    		$currentSegment .= $current;
		}

		return $output;
	}
}