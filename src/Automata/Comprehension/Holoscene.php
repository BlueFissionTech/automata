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

use BlueFission\Arr;
use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Collections\Collection;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Prototypes\Domain;
use BlueFission\Prototypes\Proto;
use BlueFission\Str;

class Holoscene extends Obj
{
	use Proto {
		explain as protected prototypeExplain;
		snapshot as protected prototypeSnapshot;
	}
	use Domain;

	/**
	 * @var OrganizedCollection<string,mixed> Map of scene keys to scene-like objects
	 */
	protected OrganizedCollection $_holo;

	/**
	 * @var array Cached assessment data
	 */
	private array $_assessment = [];

	public function __construct(?string $name = null)
	{
		parent::__construct();
		$this->_holo = new OrganizedCollection();
		$name = Str::trim((string)($name ?? 'holoscene'));
		$this->protoId($name);
		$this->domainName($name);
		$this->summary("domain[{$name}]");
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
		$this->addMember($this->sceneSnapshotValue($scene), $key);
		$this->record('scene_pushed', ['key' => $key, 'scene' => $this->sceneSnapshotValue($scene)]);
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
		$this->domainState('assessment', $this->_assessment);
		$this->measure('scene_count', Arr::size($this->_assessment));
		Dev::do('comprehension.holoscene.reviewed', ['assessment' => $this->_assessment]);
	}

	public function assessment(): array
	{
		return Dev::apply('comprehension.holoscene.assessment', $this->_assessment);
	}

	public function snapshot(): array
	{
		$snapshot = $this->prototypeSnapshot();
		$snapshot['kind'] = 'domain';
		$snapshot['assessment'] = $this->assessment();
		$snapshot['sceneCount'] = Arr::size($this->_holo->contents());
		$snapshot['summary'] = $this->summary() ?: $this->explain();

		return $snapshot;
	}

	public function explain(): string
	{
		$parts = [
			'domain[' . ($this->protoId() ?: $this->domainName() ?: 'holoscene') . ']',
			'name=' . ($this->domainName() ?: 'holoscene'),
			'members=' . Arr::size($this->members()),
			'assessment=' . Arr::size($this->assessment()),
			'history=' . Arr::size($this->history()),
		];

		$summary = implode(' | ', (new Collection($parts))
			->filter(fn ($part) => $part !== null && $part !== '')
			->contents());
		$this->summary($summary);

		return $summary;
	}

	private function sceneSnapshotValue(mixed $scene): mixed
	{
		if (is_object($scene) && method_exists($scene, 'snapshot')) {
			return $scene->snapshot();
		}

		if (is_object($scene) && method_exists($scene, 'toArray')) {
			return $scene->toArray();
		}

		if (is_object($scene) && (method_exists($scene, 'data') || method_exists($scene, 'stats'))) {
			return [
				'kind' => 'scene',
				'data' => method_exists($scene, 'data') ? $scene->data() : [],
				'stats' => method_exists($scene, 'stats') ? $scene->stats() : [],
			];
		}

		return $this->prototypeSnapshotValue($scene);
	}
}
