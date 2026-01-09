<?php
namespace BlueFission\Automata\Comprehension;

use BlueFission\Automata\Collections\OrganizedCollection;

class Log {

	private OrganizedCollection $_entities;
	private array $_facts;
	private array $_tags;
	private ?string $_description = null;
	private ?string $_time = null;
	private ?string $_place = null;
	
	public function __construct()
	{
		$this->_entities = new OrganizedCollection();
		$this->_facts = [];
		$this->_tags = [];
	}

	public function setTime(string $time): void
	{
		$this->_time = $time;
	}

	public function setPlace(string $place): void
	{
		$this->_place = $place;
	}

	public function compose(): string
	{
		return $this->buildHeader();
	}

	public function addEntity(string $name, string $description, array $meta = null): void
	{
		$entity = new Entity($name, $description, $meta);
		$this->_entities->add($entity);
	}

	public function addFact(string $data): void
	{
		$this->_facts[] = $data;
	}

	public function addTag(string $tag): void
	{
		$this->_tags[] = $tag;
	}

	public function setDescription(string $description): void
	{
		$this->_description = $description;
	}

	protected function buildHeader(): string
	{
		$output = "";

		$time  = $this->_time ?: date('Y-m-d H:i:s');
		$place = $this->_place ?: 'Unknown location';

		$output .= "##Scene\n";
		$output .= "{$time} at {$place}\n\n";

		// Context is intentionally generic; callers may override or extend later.
		$output .= "##Context\n";
		$output .= "Recorded digital experience.\n\n";

		$output .= "##Tags\n";
		$output .= implode(',', $this->_tags) . "\n\n";

		$output .= "##Characters\n";
		foreach ($this->_entities->contents() as $entry) {
			$entity = $entry['value'];
			if ($entity instanceof Entity) {
				$output .= $entity->name() . " - " . ($entity->description() ?? '') . "\n";
			}
		}
		$output .= "\n";

		$output .= "##Facts\n";
		foreach ($this->_facts as $fact) {
			$output .= "* " . $fact . "\n";
		}
		$output .= "\n";

		$output .= "##Description\n";
		$output .= ($this->_description ?? '') . "\n\n";

		return $output;
	}
}
