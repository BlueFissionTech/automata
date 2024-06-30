<?php
namespace BlueFission\Automata\Language;

class Token {
	const NEGATION = "T_NEGATION";  // Not, no
	const PREFIX = "T_PREFIX"; // Word parts at the start
	const INDICATOR = "T_INDICATOR"; //Particles like "the"
	const SYMBOL = "T_SYMBOL"; // Names, words, meaning packages
	const ENTITY = "T_ENTITY"; // Objects, properties, nouns
	const ALIAS = "T_ALIAS"; // Pronouns and place holders
	const OPERATOR = "T_OPERATOR"; // Actions, verbs
	const DESCRIPTOR = "T_DESCRIPTOR"; // Adjectives/qualities
	const DETERMINER = "T_DETERMINER"; // Amounts, quantities
	const DIRECTOR = "T_DIRECTOR"; // Prepositions
	const MODIFIER = "T_MODIFIER"; // Adverbs and words that express the mode of something
	const SUFFIX = "T_SUFFIX"; // Word parts at the end
	const WHITESPACE = "T_WHITESPACE"; // Word and expression boundaries
	const CONJUNCTION = "T_CONJUNCTION"; // and, but, then
	const INTERJECTION = "T_INTERJECTION";
	const PUNCTUATION = "T_PUNCTUATION";
}