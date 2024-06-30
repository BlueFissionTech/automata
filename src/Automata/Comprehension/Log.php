<?php
namespace BlueFission\Automata\Comprehension;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Automata\Comprehension\Entity;

class Log {

	private $_entities;
	private $_facts;
	private $_tags;
	private $_description;
	private $_time;
	private $_place;
	private $_logs;
	
	public function __construct()
	{
		$this->_entities = new OrganizedCollection();
		$this->_facts = [];
		$this->_tags = [];
	}

	public function setTime( $time )
	{
		$this->_time = $time;
	}

	public function setPlace( $place )
	{
		$this->_place = $place;
	}

	public function compose()
	{
		$output = $this->buildHeader();

	}

	public function addEntity(String $name, String $description, Array $meta = null) {
		$entity = new Entity($name, $description, $meta);
		$this->_entities->add($entity);
	}

	public function addFact(String $data) {
		$this->_facts[] = $data;
	}

	public function addTag(String $tag) {
		$this->_tags[] = $data;
	}

	public function setDescription(String $description) {
		$this->_description = $description;
	}

	protected function buildHeader()
	{
		$output = "";

		$output .= "##Scene\n"
		.date(l, F j, Y)."at {$place}\n\n";
		// ."{day}, {month} {date}, {year} "

		$output .= "##Context\n"
		."A business environment where professionalism is expected from all parties.\n\n";

		$output .= "##Tags\n"
		.implode(',', $this->_tags)"\n\n";

		$output .= "##Characters\n";
		foreach ($this->_entities as $entity) {
			$output .= $entity->name()." - ".$entity->description()."\n";
		}
		$output .= "\n";

		$output .= "##Facts\n";
		foreach ($this->_facts as $fact) {
			$output .= "* ".$fact."\n";
		}
		$output .= "\n";

		$output .= "##Description\n";
		$output .= $this->_description."\n\n";

		return $output;
	}
}