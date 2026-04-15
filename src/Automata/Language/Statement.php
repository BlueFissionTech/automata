<?php
namespace BlueFission\Automata\Language;

use BlueFission\Arr;
use BlueFission\Automata\Comprehension\Entity as ComprehensionEntity;
use BlueFission\Automata\Context;
use BlueFission\Collections\Collection;
use BlueFission\Func;
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
		name as protected prototypeName;
		labels as protected prototypeLabels;
		stateValue as protected prototypeStateValue;
		property as protected prototypeProperty;
		properties as protected prototypeProperties;
		relations as protected prototypeRelations;
	}
	use Position {
		position as protected prototypePosition;
		dimensions as protected prototypeDimensions;
		coordinates as protected prototypeCoordinates;
	}
	use HasConditions {
		normalizeConditionRecord as protected prototypeNormalizeConditionRecord;
		conditions as protected prototypeConditions;
		hasCondition as protected prototypeHasCondition;
		conditionsMet as protected prototypeConditionsMet;
		unmetConditions as protected prototypeUnmetConditions;
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

	private bool $_semanticDirty = true;
	private bool $_semanticSyncInProgress = false;
	private int $_semanticUpdateDepth = 0;

	public function __construct()
	{
		parent::__construct();
		$this->protoId('statement_' . Str::uuid4());
		$this->kind('statement');
		$this->syncSemanticState();
	}

	public function field( string $field, $value = null ): mixed
	{
		if (func_num_args() > 1) {
			if (!$this->isSemanticField($field)) {
				return parent::field($field, $value);
			}

			$this->_data[$field] = $this->normalizeSemanticFieldValue($field, $value);
			$this->markSemanticDirty();

			return $this;
		}

		if ($this->isPrototypeProjectionField($field)) {
			$this->ensureSemanticSync();
		}

		return parent::field($field);
	}

	public function assign( $data ): \BlueFission\IObj
	{
		if ( !is_object($data) && !Arr::isAssoc($data) ) {
			return parent::assign($data);
		}

		$this->openSemanticWindow();

		try {
			foreach ($data as $field => $value) {
				$this->field((string)$field, $value);
			}
		} finally {
			$this->closeSemanticWindow();
		}

		return $this;
	}

	public function normalize(): static
	{
		$this->syncSemanticState();

		return $this;
	}

	public function finalize(): static
	{
		$this->syncSemanticState();

		return $this;
	}

	public function name(?string $name = null): mixed
	{
		if (func_num_args() > 0) {
			return $this->prototypeName($name);
		}

		$this->ensureSemanticSync();

		return $this->prototypeName();
	}

	public function labels(?array $labels = null): mixed
	{
		if (func_num_args() > 0) {
			return $this->prototypeLabels($labels);
		}

		$this->ensureSemanticSync();

		return $this->prototypeLabels();
	}

	public function stateValue(?string $key = null, mixed $value = null): mixed
	{
		if (func_num_args() > 1) {
			return $this->prototypeStateValue($key, $value);
		}

		$this->ensureSemanticSync();

		return func_num_args() === 0
			? $this->prototypeStateValue()
			: $this->prototypeStateValue($key);
	}

	public function property(string $name, mixed $value = null): mixed
	{
		if (func_num_args() > 1) {
			return $this->prototypeProperty($name, $value);
		}

		$this->ensureSemanticSync();

		return $this->prototypeProperty($name);
	}

	public function properties(?array $properties = null): mixed
	{
		if (func_num_args() > 0) {
			return $this->prototypeProperties($properties);
		}

		$this->ensureSemanticSync();

		return $this->prototypeProperties();
	}

	public function relations(?string $relation = null): array
	{
		$this->ensureSemanticSync();

		return $this->prototypeRelations($relation);
	}

	public function position(mixed $position = null): mixed
	{
		if (func_num_args() > 0) {
			$this->field('position', $position);
			$this->ensureSemanticSync();

			return $this;
		}

		$this->ensureSemanticSync();

		return $this->prototypePosition();
	}

	public function dimensions(): array
	{
		$this->ensureSemanticSync();

		return $this->prototypeDimensions();
	}

	public function coordinates(): array
	{
		$this->ensureSemanticSync();

		return $this->prototypeCoordinates();
	}

	public function conditions(): array
	{
		$this->ensureSemanticSync();

		return $this->prototypeConditions();
	}

	public function hasCondition(string $name): bool
	{
		$this->ensureSemanticSync();

		return $this->prototypeHasCondition($name);
	}

	public function conditionsMet(array $context = []): bool
	{
		$this->ensureSemanticSync();

		return $this->prototypeConditionsMet($context);
	}

	public function unmetConditions(array $context = []): array
	{
		$this->ensureSemanticSync();

		return $this->prototypeUnmetConditions($context);
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
		$this->ensureSemanticSync();

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
		$this->ensureSemanticSync();

		$parts = [
			'statement[' . ($this->protoId() ?: 'unidentified') . ']',
			$this->semanticTextValue($this->field('subject')),
			$this->semanticTextValue($this->field('behavior')),
			$this->semanticTextValue($this->field('object')),
			'satisfied=' . $this->percentSatisfied(),
			'conditions=' . Arr::size($this->conditions()),
			'relations=' . Arr::size($this->relations()),
		];

		$summary = $this->joinTextParts((new Collection($parts))
			->filter(fn ($part) => $part !== null && $part !== '')
			->contents(), ' | ');
		$this->summary($summary);

		return $summary;
	}

	private function ensureSemanticSync(bool $force = false): void
	{
		if ($this->_semanticSyncInProgress) {
			return;
		}

		if ($this->_semanticUpdateDepth > 0 && !$force) {
			return;
		}

		if (!$this->_semanticDirty && !$force) {
			return;
		}

		$this->syncPrototypeState();
	}

	private function syncPrototypeState(): void
	{
		if ($this->_semanticSyncInProgress) {
			return;
		}

		$this->_semanticSyncInProgress = true;

		try {
			$this->prototypeBoot('statement');

			$state = $this->semanticStatePayload();
			$position = $this->semanticPositionPayload(parent::field('position'));
			$coordinates = $this->coordinatePayload($position);
			$dimensions = $this->dimensionPayload($coordinates);
			$stateBag = Arr::toArray($this->prototypeGet('state', []));
			$stateBag['statement'] = $state;

			$this->_data['kind'] = 'statement';
			$this->_data['properties'] = $state;
			$this->_data['state'] = $stateBag;
			$this->_data['name'] = $this->statementName();
			$this->_data['labels'] = $this->statementLabels();
			$this->_data['relations'] = $this->relationshipPayload();
			$this->_data['conditions'] = $this->conditionPayload();
			$this->_data['dimensions'] = $dimensions;
			$this->_data['coordinates'] = $coordinates;
			$this->_data['position'] = $coordinates;
			$this->_semanticDirty = false;
		} finally {
			$this->_semanticSyncInProgress = false;
		}

		$this->prototypeSignal('automata.language.statement.synced', [
			'id' => $this->protoId(),
			'name' => $this->prototypeGet('name', ''),
			'state' => $this->prototypeGet('state', []),
		]);
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

		return $this->joinTextParts($parts, ' ');
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

	private function markSemanticDirty(): void
	{
		$this->_semanticDirty = true;
	}

	private function isSemanticField(string $field): bool
	{
		return Arr::contains(self::SEMANTIC_FIELDS, $field, true);
	}

	private function isPrototypeProjectionField(string $field): bool
	{
		return Arr::contains([
			'name',
			'labels',
			'state',
			'properties',
			'relations',
			'conditions',
			'dimensions',
			'coordinates',
			'position',
			'kind',
		], $field, true);
	}

	private function semanticStatePayload(): array
	{
		$state = [];

		foreach (self::SEMANTIC_FIELDS as $field) {
			$state[$field] = $this->semanticPrototypeValue(parent::field($field));
		}

		return $state;
	}

	private function relationshipPayload(): array
	{
		$relationship = $this->semanticTextValue(parent::field('relationship'));
		$object = parent::field('object');

		if (!$this->isSatisfiedFieldValue($object)) {
			return [];
		}

		return [
			$relationship ?: 'object' => [[
				'target' => $this->semanticPrototypeValue($object),
				'meta' => [
					'subject' => $this->semanticPrototypeValue(parent::field('subject')),
					'indirect_object' => $this->semanticPrototypeValue(parent::field('indirect_object')),
					'context' => $this->semanticPrototypeValue(parent::field('context')),
				],
			]],
		];
	}

	private function conditionPayload(): array
	{
		$condition = parent::field('condition');

		if (!$this->isSatisfiedFieldValue($condition)) {
			return [];
		}

		if (Arr::is($condition) || Func::isCallable($condition)) {
			return [$this->prototypeNormalizeConditionRecord($condition)];
		}

		if (is_object($condition) && method_exists($condition, 'conditions')) {
			$records = [];

			foreach ($condition->conditions() as $record) {
				$records[] = $this->prototypeNormalizeConditionRecord($record);
			}

			return $records;
		}

		$conditionText = $this->semanticTextValue($condition);
		if ($conditionText === null || $conditionText === '') {
			return [];
		}

		return [$this->prototypeNormalizeConditionRecord([
			'name' => 'statement_condition',
			'path' => $conditionText,
			'expected' => true,
			'operator' => 'requires',
		])];
	}

	private function coordinatePayload(array $position): array
	{
		if (Arr::size($position) === 0) {
			return [];
		}

		$coordinates = [];
		foreach ($position as $dimension => $value) {
			$coordinates[(string)$dimension] = $this->prototypeSnapshotValue($value);
		}

		return $coordinates;
	}

	private function dimensionPayload(array $coordinates): array
	{
		$dimensions = [];

		foreach ($coordinates as $dimension => $value) {
			$dimensions[$dimension] = [
				'label' => (string)$dimension,
				'kind' => Num::is($value) ? 'absolute' : 'relative',
				'absolute' => Num::is($value),
				'unit' => null,
				'default' => null,
				'comparable' => true,
				'directionality' => 'bidirectional',
			];
		}

		return $dimensions;
	}

	private function openSemanticWindow(): void
	{
		$this->_semanticUpdateDepth = Num::add($this->_semanticUpdateDepth, 1);
	}

	private function closeSemanticWindow(): void
	{
		$this->_semanticUpdateDepth = (int)Num::max(0, Num::sub($this->_semanticUpdateDepth, 1));

		if ($this->_semanticUpdateDepth === 0) {
			$this->ensureSemanticSync();
		}
	}

	private function syncSemanticState(): void
	{
		$this->ensureSemanticSync(force: true);
	}

	private function joinTextParts(array $parts, string $glue): string
	{
		$text = '';

		(new Collection($parts))->each(function ($part) use (&$text, $glue): void {
			$segment = (string)$part;
			$text = $text === ''
				? $segment
				: Str::concat($text, $glue, $segment);
		});

		return $text;
	}
}
