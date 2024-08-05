<?php

namespace BlueFission\Automata\Language;

use BlueFission\Arr;
use BlueFission\Val;
use BlueFission\Collections\Collection;

class Term {
	protected $_value;
	protected $_classifications;
	protected $_variations;
	protected $_relationships;

	protected $_types = [];

	public function __construct($value) {
		$this->_value = $value;
		$this->_classifications = new Arr()
		$this->_variations = new Arr();
		$this->_relationships = new Collection();

		$this->_meta = new Arr();

		$this->_types = ['synonyms', 'antonyms', 'homonyms', 'homophones', 'hypernyms', 'hyponyms', 'meronyms', 'holonyms', 'troponyms', 'coordinateTerms', 'relatedTerms'];
	}

	public function match($term) {
		$match = $this->_value == $term;

		if( !$match ) {
			$match = $this->_variations->has($term);
		}

		return $match;
	}

	public function value() {
		return $this->_value;
	}

	public function classifications() {
		return $this->_classifications;
	}

	public function variations() {
		return $this->_variations;
	}

	public function relationships($classification, $type = null) {
		if ( !$this->_classifications->has($classification) ) {
			return null;
		}

		if (Val::isNotEmpty($type)) {
			return $this->_relationships->get($classification)->get($type);
		}

		return $this->_relationships;
	}

	public function meta() {
		return $this->_meta;
	}

	public function addClassification($classification) {
		$this->_classifications->set($classification)->unique()->sort();

		$this->prepareRelationships($classification)
	}

	public function addVariation($variation) {
		$this->_variations->set($variation)->unique()->sort();
	}

	public function addRelationship($term, $type = 'relatedTerms') {
		$type = $this->qualifyRelationshipType($term, $type);

		$term = $this->toTerm($term);

		$this->_relationships->get($type)->set($term->value(), $term)->sort(function($a, $b) {
			return strcmp($a->value(), $b->value());
		});
	}

	private function qualifyRelationshipType($term, $type) {
		if (Val::isEmpty($type)) {
			$type = 'relatedTerms';
		}

		if ($type == 'homophones' && $term == $this->_value) {
			$type = 'homonyms';
		}

		return $type;
	}

	public function addMeta($key, $value) {
		$this->_meta->set($key, $value);
	}

	protected function prepareRelationships( $classification ) {
		$types = $this->_types;

		if ( $this->_relationships->has($classification) ) {
			return;
		}

		$this->_relationships->set($classification, new Collection());

		foreach ($types as $type) {
			$this->_relationships->get($classification)->set($type, new Arr());
		}
	}

	private function toTerm($term) {
		if (is_a($term, Term::class)) {
			return $term;
		}

		$search = $this->_relationships->filter(function($entry) use ($term) {
			if ( is_a($entry, Collection::class) ) {
				return $entry->filter(function($item) use ($term) {
					if ( is_a($item, Arr::class) ) {
						return $item->hasKey($term) ? $item->get($term) : null;
					}
				})->first();
			}
		})->first();

		if (Val::isNotEmpty($search)) return $search;

		return new Term($term);
	}

	public function __toString() {
		return $this->_value;
	}
}