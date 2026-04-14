<?php
namespace BlueFission\Automata\Language;

use BlueFission\Arr;
use BlueFission\Collections\Collection;
use BlueFission\Flag;
use BlueFission\Num;
use BlueFission\Obj;
use BlueFission\Prototypes\HasConditions;
use BlueFission\Prototypes\Position;
use BlueFission\Prototypes\Proto;
use BlueFission\Str;

/*
The intended meanings for vocalizations were grouped into six main categories: animate entities (child, man, woman, tiger, snake, deer), inanimate entities (knife, fire, rock, water, meat, fruit), actions (gather, cook, hide, cut, pound, hunt, eat, sleep), properties (dull, sharp, big, small, good, bad), quantifiers (one, many) and demonstratives (this, that).

https://www.livescience.com/iconic-vocalizations-lead-to-human-languages.html
*/

class Statement extends Obj {
	use Proto {
		explain as protected prototypeExplain;
		snapshot as protected prototypeSnapshot;
	}
	use Position;
	use HasConditions {
		normalizeConditionRecord as protected prototypeNormalizeConditionRecord;
	}

	private const SEMANTIC_FIELDS = [
		'type',
		'context',
		'priority',
		'subject',
		'negation',
		'modality',
		'behavior',
		'condition',
		'object',
		'relationship',
		'indirect_object',
		'position',
	];

	protected $_data = [
		'type'=>1, // interogative, imperative, declarative
		'context'=>'',
		'priority'=>0,
		'subject'=>'',
		'negation'=>true, // figure out a better word for this later
		'modality'=>'',
		'behavior'=>'',
		'condition'=>'',
		'object'=>'',
		'relationship'=>'',
		'indirect_object'=>'',
		'position'=>''
	];

	public function __construct()
	{
		parent::__construct();
		$this->protoId('statement_' . Str::uuid4());
		$this->kind('statement');
		$this->syncPrototypeState();
	}

	public function field( string $field, $value = null ): mixed
	{
		$result = parent::field($field, $value);

		if (func_num_args() > 1) {
			$this->syncPrototypeState();
		}

		return $result;
	}

	public function percentSatisfied() {
		$parts = Arr::size(self::SEMANTIC_FIELDS);
		$satisfied = 0;
		foreach ( self::SEMANTIC_FIELDS as $part ) {
			if ( $this->isSatisfiedFieldValue($this->field($part)) ) {
				$satisfied++;
			}
		}
		return (float)Num::divide($satisfied, $parts);
	}

	public function satisfy() {
		foreach ( self::SEMANTIC_FIELDS as $part ) {
			if ( !$this->isSatisfiedFieldValue($this->field($part)) ) {
				return "{$part}";
			}
		}

		return null;
	}

	public function entities() {
		return (new Collection([
			$this->field('subject'),
			$this->field('object'),
			$this->field('indirect_object'),
		]))
			->filter(fn ($entity) => $entity !== null && $entity !== '')
			->contents();
	}

	public function snapshot(): array
	{
		$snapshot = $this->prototypeSnapshot();
		foreach (self::SEMANTIC_FIELDS as $field) {
			$snapshot[$field] = $this->field($field);
		}
		$snapshot['kind'] = 'statement';
		$snapshot['entities'] = $this->entities();
		$snapshot['percentSatisfied'] = $this->percentSatisfied();
		$snapshot['summary'] = $this->summary() ?: $this->explain();

		return $snapshot;
	}

	public function explain(): string
	{
		$parts = [
			'statement[' . ($this->protoId() ?: 'unidentified') . ']',
			$this->field('subject'),
			$this->field('behavior'),
			$this->field('object'),
			'satisfied=' . $this->percentSatisfied(),
			'conditions=' . Arr::size($this->conditions()),
			'relations=' . Arr::size($this->relations()),
		];

		$summary = implode(' | ', (new Collection($parts))
			->filter(fn ($part) => $part !== null && $part !== '')
			->contents());
		$this->summary($summary);

		return $summary;
	}

	private function syncPrototypeState(): void
	{
		$this->kind('statement');
		$this->properties([]);
		$this->stateValue('statement', []);

		$state = [];
		foreach (self::SEMANTIC_FIELDS as $field) {
			$value = $this->field($field);
			$state[$field] = $value;
			$this->property($field, $value);
		}

		$this->stateValue('statement', $state);
		$this->name($this->statementName());
		$this->labels($this->statementLabels());
		$this->prototypeSet('relations', [], 'automata.language.statement.relations_reset');
		$this->prototypeSet('conditions', [], 'automata.language.statement.conditions_reset');
		$this->syncRelationshipPrototype();
		$this->syncConditionPrototype();
		$this->syncPositionPrototype();
	}

	private function syncRelationshipPrototype(): void
	{
		$relationship = Str::trim((string)$this->field('relationship'));
		$object = $this->field('object');

		if ($object === null || $object === '') {
			return;
		}

		$this->relate($relationship !== '' ? $relationship : 'object', $object, [
			'subject' => $this->field('subject'),
			'indirect_object' => $this->field('indirect_object'),
		]);
	}

	private function syncConditionPrototype(): void
	{
		$condition = $this->field('condition');
		if ($condition === null || $condition === '') {
			return;
		}

		$this->addCondition($this->prototypeNormalizeConditionRecord([
			'name' => 'statement_condition',
			'path' => (string)$condition,
			'expected' => true,
			'operator' => 'requires',
		]));
	}

	private function syncPositionPrototype(): void
	{
		$position = $this->field('position');
		$this->prototypeSet('coordinates', [], 'automata.language.statement.coordinates_reset');

		if (Arr::is($position)) {
			foreach ($position as $dimension => $value) {
				$this->defineDimension((string)$dimension);
				$this->coordinate((string)$dimension, $value);
			}
			$this->position($this->coordinates());
			return;
		}

		if ($position !== null && $position !== '') {
			$this->defineDimension('position');
			$this->coordinate('position', $position);
			$this->position($this->coordinates());
			return;
		}

		$this->position([]);
	}

	private function statementName(): string
	{
		$parts = (new Collection([
			$this->field('subject'),
			$this->field('behavior'),
			$this->field('object'),
		]))
			->filter(fn ($part) => $part !== null && $part !== '')
			->contents();

		return implode(' ', $parts);
	}

	private function statementLabels(): array
	{
		return (new Collection([
			Str::trim((string)$this->field('context')),
			Str::trim((string)$this->field('modality')),
			Str::trim((string)$this->field('relationship')),
		]))
			->filter(fn ($label) => $label !== '')
			->contents();
	}

	private function isSatisfiedFieldValue(mixed $value): bool
	{
		if (Arr::is($value)) {
			return Arr::size($value) > 0;
		}

		if (Flag::isTrue($value) || Flag::isFalse($value)) {
			return Flag::isTrue($value);
		}

		if (Num::is($value)) {
			return true;
		}

		return Str::trim((string)$value) !== '';
	}
}
