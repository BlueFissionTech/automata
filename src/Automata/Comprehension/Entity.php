<?php
namespace BlueFission\Automata\Comprehension;

use BlueFission\Collections\Collection;
use BlueFission\Obj;
use BlueFission\Prototypes\Entity as PrototypeEntity;
use BlueFission\Prototypes\Position;
use BlueFission\Prototypes\Proto;

class Entity extends Obj
{
	use Proto {
		explain as protected prototypeExplain;
		snapshot as protected prototypeSnapshot;
	}
	use PrototypeEntity;
	use Position;

	public function __construct( string $name, ?string $description = null, $meta = null ) {
		parent::__construct();

		$this->protoId($name);
		$this->name($name);
		$this->reactive(true);

		if ($description !== null) {
			$this->description($description);
		}

		if ($meta !== null) {
			$this->meta($meta);
		}
	}

	public function description(?string $description = null): mixed
	{
		if (func_num_args() === 0) {
			$value = $this->property('description');
			return $value === null ? null : (string)$value;
		}

		$this->property('description', $description);
		return $this;
	}

	public function meta(mixed $meta = null): mixed
	{
		if (func_num_args() === 0) {
			return $this->property('meta');
		}

		$this->property('meta', $meta);
		return $this;
	}

	public function explain(): string
	{
		$parts = [
			$this->name(),
			$this->description(),
			'entity[' . ($this->protoId() ?: $this->name() ?: 'unidentified') . ']',
			'labels=' . count($this->labels()),
			'relations=' . count($this->relations()),
			'history=' . count($this->history()),
		];

		$summary = implode(' | ', (new Collection($parts))
			->filter(fn ($part) => $part !== null && $part !== '')
			->contents());
		$this->summary($summary);

		return $summary;
	}

	public function snapshot(): array
	{
		$snapshot = $this->prototypeSnapshot();
		$snapshot['description'] = $this->description();
		$snapshot['meta'] = $this->meta();
		$snapshot['summary'] = $this->summary() ?: 'entity[' . ($this->protoId() ?: $this->name() ?: 'unidentified') . ']';

		return $snapshot;
	}
}
