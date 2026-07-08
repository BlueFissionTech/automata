<?php
namespace BlueFission\Automata\Collections;

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use BlueFission\Chronicler\Storage\Structures\WeightedCollection;
use BlueFission\Collections\Collection;
use BlueFission\Collections\ICollection;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * @deprecated Prefer BlueFission\Chronicler\Storage\Structures\WeightedCollection for new ranking stores.
 */
class OrganizedCollection extends Collection implements ICollection, ArrayAccess, IteratorAggregate {
	protected $_decay = .001;
	private $_max = 1048576;
	private $_autosort = true;

	private $_do_sort = true;
	private $_do_decay = false;

	private WeightedCollection $_weighted;

	private $_stats = [
		'count'=>'',
		'total'=>'',
		'min'=>'',
		'max'=>'',
		'mode'=>'',
		'median'=>'',
		'mean'=>'',
		'mean1'=>'',
		'mean2'=>'',
		'mean3'=>'',
		'variance1'=>'',
		'variance2'=>'',
		'variance3'=>'',
		'popvariance1'=>'',
		'popvariance2'=>'',
		'popvariance3'=>'',
		'std1'=>'',
		'std2'=>'',
		'std3'=>'',
		'popstd1'=>'',
		'popstd2'=>'',
		'popstd3'=>'',
		'cv1'=>'',
		'cv2'=>'',
		'cv3'=>'',
		'outliers'=>'',
		'super_outliers'=>'',
	];

	public function __construct($value = null, ?WeightedCollection $weighted = null)
	{
		parent::__construct();

		$this->_weighted = $weighted ?? new WeightedCollection($this->_max, $this->_decay);

		if (is_array($value)) {
			foreach ($value as $key => $entry) {
				if (is_array($entry) && array_key_exists('value', $entry)) {
					$this->_weighted->add($entry['value'], $key, $entry['weight'] ?? 1);
				} else {
					$this->_weighted->add($entry, $key);
				}
			}
		} elseif ($value !== null) {
			$this->_weighted->add($value);
		}

		$this->syncFromWeighted();
	}

	public function weighted(): WeightedCollection
	{
		return $this->_weighted;
	}

	public function sort(?callable $callback = null) {
		if (!$this->_do_sort) {
			$this->syncFromWeighted();
			return new Collection($this->contents());
		}

		$this->_weighted->sort($callback);
		$this->syncFromWeighted();

		return new Collection($this->contents());
	}

	public function autoSort($value = true)
	{
		$this->_autosort = (bool)$value;
		$this->_weighted->autoSort($this->_autosort);
		$this->syncFromWeighted();

		return $this;
	}

	public function setSort($sorts) {
		$this->_do_sort = (bool)$sorts;
		$this->_weighted->setSort((bool)$sorts);
		$this->syncFromWeighted();

		return $this;
	}

	public function setMax($max) {
		$this->_max = (int)$max;
		$this->_weighted->setMax($this->_max);
		$this->syncFromWeighted();

		return $this;
	}

	public function setDecay($decays, $rate = null) {
		$this->_do_decay = (bool)$decays;
		$this->_decay = $rate ?? $this->_decay;
		$this->_weighted->setDecay($this->_do_decay, (float)$this->_decay);
		$this->syncFromWeighted();

		return $this;
	}

	public function sort_function($a, $b)
	{
		if ($this->_do_decay) {
			$a['weight'] -= floor($a['decay'] * (time() - $a['timestamp']));
			$b['weight'] -= floor($b['decay'] * (time() - $b['timestamp']));
		}

	    if ($a['weight'] == $b['weight']) {
	        return 0;
	    }
	    return ($a['weight'] < $b['weight']) ? 1 : -1;
	}

	public function get($key) {
		$this->assertScalarKey($key);

		$value = $key === null ? null : $this->_weighted->get($key);
		$this->syncFromWeighted();

		return $value;
	}

	public function has($key) {
		$this->assertScalarKey($key);

		return $key !== null && $this->_weighted->has($key);
	}

	public function stats() {
		$this->_stats = $this->_weighted->stats();
		$this->syncFromWeighted();

		return $this->_stats;
	}

	public function data() {
		$this->_stats = $this->_weighted->data();
		$this->syncFromWeighted();

		return $this->_stats;
	}

	public function weight($key, $weight = null) {
		$this->assertScalarKey($key);
		if ($key === null) {
			return null;
		}

		$result = $this->_weighted->weight($key, $weight);
		$this->syncFromWeighted();

		return $result;
	}

	public function add($object, $key = null, int $weight = 1): ICollection
	{
		$this->assertScalarKey($key);

		$this->_weighted->add($object, $key, $weight);
		$this->syncFromWeighted();

		return $this;
	}

	protected function create($value, int $weight = 1) {
		return ['weight'=>$weight, 'percentage'=>$this->findPercentage($weight), 'value'=>$value, 'decay'=>$this->_decay, 'timestamp'=>time()];
	}

	protected function findPercentage($amount) {
		$total = $this->_weighted->stats()['total'] ?? 0;

		return ($amount) / ($total > 0 ? $total : 1);
	}

	public function remove($key): ICollection
	{
		$this->assertScalarKey($key);
		if ($key !== null) {
			$this->_weighted->remove($key);
		}
		$this->syncFromWeighted();

		return $this;
	}

	public function clear(): ICollection
	{
		$this->_weighted->clear();
		$this->_stats = [];
		$this->syncFromWeighted();

		return $this;
	}

	public function optimize($tolerance = 10, $noise = [])
    {
        $this->_weighted->optimize($tolerance, $noise);
		$this->syncFromWeighted();

		return $this;
    }

	public function __serialize(): array
	{
		return [
			'entries' => $this->_weighted->data()['values'] ?? [],
			'max' => $this->_max,
			'decay' => $this->_decay,
			'autosort' => $this->_autosort,
			'do_sort' => $this->_do_sort,
			'do_decay' => $this->_do_decay,
		];
	}

	public function __unserialize(array $data): void
	{
		$this->_max = (int)($data['max'] ?? $this->_max);
		$this->_decay = (float)($data['decay'] ?? $this->_decay);
		$this->_autosort = (bool)($data['autosort'] ?? $this->_autosort);
		$this->_do_sort = (bool)($data['do_sort'] ?? $this->_do_sort);
		$this->_do_decay = (bool)($data['do_decay'] ?? $this->_do_decay);
		$this->_weighted = new WeightedCollection($this->_max, $this->_decay);
		$this->_weighted->autoSort($this->_autosort);
		$this->_weighted->setDecay($this->_do_decay, $this->_decay);

		foreach (($data['entries'] ?? []) as $key => $entry) {
			if (is_array($entry) && array_key_exists('value', $entry)) {
				$this->_weighted->add($entry['value'], $key, $entry['weight'] ?? 1);
			}
		}

		$this->syncFromWeighted();
	}

	public function getIterator(): ArrayIterator
	{
		$this->syncFromWeighted();

		return new ArrayIterator($this->_value);
	}

	public function __toString() {
		return (string)$this->_weighted;
	}

	private function syncFromWeighted(): void
	{
		$this->_value = new ArrayObject($this->_weighted->data()['values'] ?? []);
		$this->_iterator = new ArrayIterator($this->_value);
	}

	private function assertScalarKey($key): void
	{
		if (!is_scalar($key) && !is_null($key)) {
			throw new InvalidArgumentException('Label must be scalar');
		}
	}
}
