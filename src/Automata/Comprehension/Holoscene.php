<?php

/*
 resolve Gestalt
 Proximity
 Similarity
 Continuity
 Pragnanz (simplicity)
 Symmetry
 Closure
 Common Fate

 Holoscene is intended as a higher-level container for Scenes, capturing
 a holistic view of experience across multiple episodes. Gestalt concepts
 guide which scenes stand out and how they are related.
*/
namespace BlueFission\Automata\Comprehension;

use BlueFission\Behavioral\Dispatches;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Behavioral\IDispatcher;
use BlueFission\DevElation as Dev;

class Holoscene implements IDispatcher
{
	use Dispatches;

	/**
	 * @var OrganizedCollection<string,mixed> Map of scene keys to scene-like objects
	 */
	protected OrganizedCollection $_holo;

	/**
	 * @var array Cached assessment data
	 */
	private array $_assessment = [];

	public function __construct()
	{
		$this->_holo = new OrganizedCollection();
        Dev::do('comprehension.holoscene.construct', ['collection' => $this->_holo]);
	}

	/**
	 * Push a scene or scene-like object into the holoscene under a key.
	 *
	 * @param string $key   Identifier for this scene (e.g., episode id)
	 * @param mixed  $scene Scene or structure representing an episode.
	 */
	public function push(string $key, $scene): void
	{
        $scene = Dev::apply('comprehension.holoscene.push_scene', $scene);
		$this->_holo->add($scene, $key);
        Dev::do('comprehension.holoscene.pushed', ['key' => $key, 'scene' => $scene]);
	}

	/**
	 * Review all stored scenes and compute an assessment structure.
	 *
	 * For now, this simply mirrors the underlying OrganizedCollection
	 * contents; in the future it can incorporate stats, Gestalt measures,
	 * and cross-scene relationships.
	 */
	public function review(): void
	{
        Dev::do('comprehension.holoscene.review_start', ['collection' => $this->_holo]);
		$this->_assessment = $this->_holo->contents();
        Dev::do('comprehension.holoscene.reviewed', ['assessment' => $this->_assessment]);
	}

	public function assessment(): array
	{
        return Dev::apply('comprehension.holoscene.assessment', $this->_assessment);
	}
}
