<?php
namespace BlueFission\Automata\Language;

use BlueFission\Arr;
use BlueFission\Automata\Comprehension\Entity as ComprehensionEntity;
use BlueFission\Automata\Context;
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

	private const SATISFACTION_WEIGHTS = [
		'type' => 0.05,
		'context' => 0.025,
		'priority' => 0.0,
		'subject' => 0.25,
		'negation' => 0.05,
		'modality' => 0.025,
		'behavior' => 0.25,
		'condition' => 0.05,
		'object' => 0.2,
		'relationship' => 0.05,
		'indirect_object' => 0.05,
		'position' => 0.05,
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
		if (func_num_args() > 1) {
			$this->_data[$field] = $this->normalizeSemanticFieldValue($field, $value);
			$this->syncPrototypeState();

			return $this;
		}

		return parent::field($field);
	}

	public function percentSatisfied() {
		$score = 0.0;

		foreach ( self::SEMANTIC_FIELDS as $part ) {
			if ( $this->isSatisfiedFieldValue($this->field($part)) ) {
				$score = Num::add($score, self::SATISFACTION_WEIGHTS[$part] ?? 0.0);
			}
		}

		return (float)Num::min(1.0, $score);
	}

	public function satisfy() {
		foreach ( $this->requiredFields() as $part ) {
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
			$snapshot[$field] = $this->semanticPrototypeValue($this->field($field));
		}
		$snapshot['kind'] = 'statement';
		$snapshot['entities'] = (new Collection($this->entities()))
			->map(fn ($entity) => $this->semanticPrototypeValue($entity))
			->contents();
		$snapshot['percentSatisfied'] = $this->percentSatisfied();
		$snapshot['summary'] = $this->summary() ?: $this->explain();

		return $snapshot;
	}

	public function explain(): string
	{
		$parts = [
			'statement[' . ($this->protoId() ?: 'unidentified') . ']',
			$this->semanticTextValue($this->field('subject')),
			$this->semanticTextValue($this->field('behavior')),
			$this->semanticTextValue($this->field('object')),
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
			$state[$field] = $this->semanticPrototypeValue($value);
			$this->property($field, $state[$field]);
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
		$relationship = $this->semanticTextValue($this->field('relationship'));
		$object = $this->field('object');

		if (!$this->isSatisfiedFieldValue($object)) {
			return;
		}

		$this->relate($relationship ?: 'object', $this->semanticPrototypeValue($object), [
			'subject' => $this->semanticPrototypeValue($this->field('subject')),
			'indirect_object' => $this->semanticPrototypeValue($this->field('indirect_object')),
			'context' => $this->semanticPrototypeValue($this->field('context')),
		]);
	}

	private function syncConditionPrototype(): void
	{
		$condition = $this->field('condition');
		if (!$this->isSatisfiedFieldValue($condition)) {
			return;
		}

		if (Arr::is($condition) || is_callable($condition)) {
			$this->addCondition($this->prototypeNormalizeConditionRecord($condition));
			return;
		}

		if (is_object($condition) && method_exists($condition, 'conditions')) {
			foreach ($condition->conditions() as $record) {
				$this->addCondition($this->prototypeNormalizeConditionRecord($record));
			}
			return;
		}

		$conditionText = $this->semanticTextValue($condition);
		if ($conditionText === null || $conditionText === '') {
			return;
		}

		$this->addCondition($this->prototypeNormalizeConditionRecord([
			'name' => 'statement_condition',
			'path' => $conditionText,
			'expected' => true,
			'operator' => 'requires',
		]));
	}

	private function syncPositionPrototype(): void
	{
		$position = $this->semanticPositionPayload($this->field('position'));
		$this->prototypeSet('dimensions', [], 'automata.language.statement.dimensions_reset');
		$this->prototypeSet('coordinates', [], 'automata.language.statement.coordinates_reset');

		if (Arr::is($position) && Arr::size($position) > 0) {
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
			$this->semanticTextValue($this->field('subject')),
			$this->semanticTextValue($this->field('behavior')),
			$this->semanticTextValue($this->field('object')),
		]))
			->filter(fn ($part) => $part !== null && $part !== '')
			->contents();

		return implode(' ', $parts);
	}

	private function statementLabels(): array
	{
		return (new Collection([
			...$this->semanticLabels($this->field('context')),
			...$this->semanticLabels($this->field('modality')),
			...$this->semanticLabels($this->field('relationship')),
		]))
			->filter(fn ($label) => Str::trim((string)$label) !== '')
			->contents();
	}

	private function isSatisfiedFieldValue(mixed $value): bool
	{
		if ($value instanceof Context) {
			return Arr::size($value->all()) > 0
				|| Arr::size($value->tags()) > 0
				|| Arr::size($value->normalizations()) > 0;
		}

		if (Arr::is($value)) {
			return Arr::size($value) > 0;
		}

		if (is_object($value)) {
			return $this->semanticTextValue($value) !== null
				|| Arr::size($this->semanticPositionPayload($value)) > 0
				|| $this->semanticPrototypeValue($value) !== $value;
		}

		if (Flag::isTrue($value) || Flag::isFalse($value)) {
			return Flag::isTrue($value);
		}

		if (Num::is($value)) {
			return true;
		}

		return Str::trim((string)$value) !== '';
	}

	private function requiredFields(): array
	{
		return [
			'subject',
			'behavior',
			'object',
		];
	}

	private function normalizeSemanticFieldValue(string $field, mixed $value): mixed
	{
		if (Arr::contains(['subject', 'object', 'indirect_object'], $field, true) && Arr::isAssoc($value)) {
			$name = $value['name'] ?? $value['value'] ?? $value['label'] ?? null;
			if ($name !== null) {
				return new ComprehensionEntity(
					(string)$name,
					isset($value['description']) ? (string)$value['description'] : null,
					$value['meta'] ?? $value
				);
			}
		}

		if ($field === 'context' && Arr::isAssoc($value)) {
			return new Context($value);
		}

		return $value;
	}

	private function semanticPrototypeValue(mixed $value): mixed
	{
		if ($value instanceof Context) {
			return [
				'data' => $value->all(),
				'tags' => $value->tags(),
				'normalizations' => $value->normalizations(),
			];
		}

		if (is_object($value) && method_exists($value, 'all')) {
			return $value->all();
		}

		if (is_object($value) && method_exists($value, 'coordinates')) {
			$coordinates = $value->coordinates();
			if (Arr::is($coordinates) && Arr::size($coordinates) > 0) {
				return [
					'coordinates' => $coordinates,
					'name' => $this->semanticTextValue($value),
				];
			}
		}

		return $this->prototypeSnapshotValue($value);
	}

	private function semanticTextValue(mixed $value): ?string
	{
		if ($value instanceof Context) {
			$candidate = $value->normalizedValue('label')
				?? $value->get('label')
				?? $value->normalizedValue('value')
				?? $value->get('value');

			if ($candidate !== null) {
				$candidate = Str::trim((string)$candidate);
				return $candidate === '' ? null : $candidate;
			}

			return null;
		}

		if (is_object($value) && method_exists($value, 'name')) {
			$candidate = Str::trim((string)$value->name());
			return $candidate === '' ? null : $candidate;
		}

		if (is_object($value) && method_exists($value, 'protoId')) {
			$candidate = Str::trim((string)$value->protoId());
			return $candidate === '' ? null : $candidate;
		}

		if (is_object($value) && method_exists($value, '__toString')) {
			$candidate = Str::trim((string)$value);
			return $candidate === '' ? null : $candidate;
		}

		if (is_object($value)) {
			return null;
		}

		if ($value === null) {
			return null;
		}

		$candidate = Str::trim((string)$value);
		return $candidate === '' ? null : $candidate;
	}

	private function semanticLabels(mixed $value): array
	{
		if ($value instanceof Context) {
			return (new Collection([
				$value->get('label'),
				$value->normalizedValue('label'),
				...Arr::keys($value->tags()),
				...Arr::keys($value->normalizations()),
			]))
				->filter(fn ($label) => $label !== null && Str::trim((string)$label) !== '')
				->map(fn ($label) => Str::trim((string)$label))
				->contents();
		}

		if (is_object($value) && method_exists($value, 'labels')) {
			return (new Collection($value->labels()))
				->filter(fn ($label) => $label !== null && Str::trim((string)$label) !== '')
				->map(fn ($label) => Str::trim((string)$label))
				->contents();
		}

		$label = $this->semanticTextValue($value);

		return $label === null ? [] : [$label];
	}

	private function semanticPositionPayload(mixed $position): array
	{
		if (Arr::is($position)) {
			return Arr::toArray($position);
		}

		if (is_object($position) && method_exists($position, 'coordinates')) {
			$coordinates = $position->coordinates();
			return Arr::is($coordinates) ? Arr::toArray($coordinates) : [];
		}

		if (is_object($position) && method_exists($position, 'position')) {
			$payload = $position->position();
			return Arr::is($payload) ? Arr::toArray($payload) : [];
		}

		if (is_object($position) && method_exists($position, 'snapshot')) {
			$snapshot = $position->snapshot();
			if (Arr::isAssoc($snapshot)) {
				$coordinates = $snapshot['coordinates'] ?? $snapshot['position'] ?? null;
				return Arr::is($coordinates) ? Arr::toArray($coordinates) : [];
			}
		}

		if ($position !== null && $position !== '') {
			return ['position' => $position];
		}

		return [];
	}
}
