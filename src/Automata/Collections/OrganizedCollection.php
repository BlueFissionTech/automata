<?php
namespace BlueFission\Automata\Collections;

use ArrayAccess;
use ArrayObject;
use ArrayIterator;
use IteratorAggregate;
use BlueFission\Collections\Collection;
use BlueFission\Collections\ICollection;

class OrganizedCollection extends Collection implements ICollection, ArrayAccess, IteratorAggregate {
	protected $_decay = .001;
	private $_max = 1048576;
	private $_increment = 0;
	private $_autosort = true;

	private $_do_sort = true;
	private $_do_decay = false;

	private $_stats = [
		'count'=>'',
		'total'=>'',
		'min'=>'',
		'max'=>'',
		'mode'=>'',
		'median'=>'',
		'mean1'=>'',
		'mean2'=>'',
		'mean3'=>'',
		'variance1'=>'',
		'variance2'=>'',
		'variance3'=>'',
		'std1'=>'',
		'std2'=>'',
		'std3'=>'',
		'cv1'=>'',
		'cv2'=>'',
		'cv3'=>'',
	];

	public function sort(?callable $callback = null) {

		if ( !$callback ) {
			$callback = [$this, 'sort_function'];
		}

		if ($this->_do_sort) {
			$this->_value->uasort( $callback );
		}

		foreach ($this->_value as &$value) {
			$percent = $this->findPercentage($value['weight']);
			$value['percentage'] = $percent;
		}

		return new Collection( $this->contents() );
	}

	public function autoSort ($value = true) 
	{
		$this->_autosort = $value;
	}
	
	public function setSort( $sorts ) {
		$this->_do_sort = $sorts;
	}

	public function setMax( $max ) {
		$this->_max = $max;
	}

	public function setDecay( $decays, $rate = null ) {
		$this->_do_decay = $decays;
		$this->_decay = $rate ?? $this->_decay;
	}

	public function sort_function($a, $b)
	{
		if ( $this->_do_decay ) {
			$a['weight'] -= floor( $a['decay']*(time()-$a['timestamp']) );
			$b['weight'] -= floor( $b['decay']*(time()-$b['timestamp']) );
		}

	    if ($a['weight'] == $b['weight']) {
	        return 0;
	    }
	    return ($a['weight'] < $b['weight']) ? 1 : -1;
	}

	public function get( $key ) {
		if (!is_scalar($key) && !is_null($key)) {
			throw new InvalidArgumentException('Label must be scalar');
		}
		if ($this->has( $key )) {
			$this->_value[$key]['weight']++;
			$this->_value[$key]['timestamp'] = time();
			if ( $this->_autosort) {
				$this->sort();
			}
			return $this->_value[$key]['value'];
		}
		else 
			return null;		
	}

	public function stats() {
		$count = count($this->_value);

		if ($count < 1) return;

		// $index = $n * $count;
		// array_walk($this->_value, function( &$value, $key) {
		// 	$value['weight'];
		// });

		$total1 = 0;
		$total2 = 0;
		$total3 = 0;
		
		// die(var_dump($this->_value));

		$values = array_reverse(array_values($this->_value->getArrayCopy()));
		
		$min = $values[0]['weight'];
		$max = $values[$count-1]['weight'];
		$mode = 0;
		
		// Percentiles
		$index = .25 * $count;
		$modulus = $index%2;
		if ($modulus || $count <= 2) { // balancing arrays smaller than 2. TODO: Check is this math still works!!!
			$q1 = $values[round($index)]['weight'];
		} else {
			$q1 = ($values[round($index)]['weight'] + $values[round($index+1)]['weight']) / 2;
		}

		$index = .75 * $count;
		$modulus = $index%2;
		if ($modulus || $count <= 3) { // balancing arrays smaller than 3. TODO: Check is this math still works!!!
			$i = ($count <= 3) ? 0 : round($index);
			$q3 = $values[$i]['weight'];
		} else {
			if (count($values) == round($index+1)) { // fix for "Undefined Offset 6" error for values of length 5
				$index--; // TODO: Make sure this math still works for quartiles!
			}
			$q3 = ($values[round($index)]['weight'] + $values[round($index+1)]['weight']) / 2;
		}

		// die(var_dump($q3));
		$interquartile_range = $q3 - $q1;

		$inner_range = $interquartile_range * 1.5;
		$inner_fence1 = $q3 + $inner_range;
		$inner_fence2 = $q1 - $inner_range;

		$outer_range = $interquartile_range * 3;
		$outer_fence1 = $q3 + $outer_range;
		$outer_fence2 = $q1 - $outer_range;

		$counter1 = $counter2 = 1;

		$weights = [];
		foreach ($values as $value) {
			$weight = $value['weight'];
			$total1 += $weight;
			if ( $weight < $inner_fence1 && $weight > $inner_fence2) {
				$total2 += $weight;
				$counter1++;
			}
			if ( $weight < $outer_fence1 && $weight > $outer_fence2) {
				$total3 += $weight;
				$counter2++;
			}
			if (!isset($weights[$weight])) {
				$weights[$weight] = 0;
			}
			$weights[$weight]++;
		}
		asort($weights);

		// Mean and trimmed means
		$mean1 = $total1 / $count;
		$mean2 = $total2 / $counter1;
		$mean3 = $total3 / $counter2;
		
		$median = 0;
		$mode = 0;
		$mode_index = count($weights)-1;
		$i = 1;
		foreach ($weights as $weight) {
 			if ($i == $mode_index) {
				$mode = $weight;
				break;
			}
			$i++;
		}
		$middle = $count/2;
		$modulus = $count%2;
		
		if ($modulus && $count != 1) {
			$median = ($values[round($middle, 0, PHP_ROUND_HALF_UP)]['weight'] + $values[round($middle, 0, PHP_ROUND_HALF_DOWN)]['weight']) / 2;
		} else {
			$middle = ($count == 1) ? 0 : $middle;
			$median = $values[round($middle)]['weight'];
		}

		$variancediff1 = $variancediff2 = $variancediff3 = 0;
		foreach ($values as $value) {
			$variancediff1 += pow( ( $value['weight'] - $mean1 ), 2);

			if ( $weight < $inner_fence1 && $weight > $inner_fence2) {
				$variancediff2 += pow( ( $value['weight'] - $mean2 ), 2);
			}

			if ( $weight < $outer_fence1 && $weight > $outer_fence2) {
				$variancediff3 += pow( ( $value['weight'] - $mean3 ), 2);
			}
		}

		$variance1 = 0;
		$variance2 = 0;
		$variance3 = 0;

		$popvariance1 = 0;
		$popvariance2 = 0;
		$popvariance3 = 0;

		if ($count > 1) {
			$variance1 = $variancediff1/($count-1);
			$variance2 = 0;
			$variance3 = 0;

			if ( $counter1 > 1 && $counter2 > 1) {
				$variance2 = $counter1 >= 1 ? $variancediff2/($counter1-1) : 0;
				$variance3 = $counter2 >= 1 ? $variancediff3/($counter2-1) : 0;
			}

			$popvariance1 = $variancediff1/($count);
			$popvariance2 = $counter1 > 0 ? $variancediff2/($counter1) : 0;
			$popvariance3 = $counter2 > 0 ? $variancediff3/($counter2) : 0;
		}

		$std1 = sqrt($variance1);
		$std2 = sqrt($variance2);
		$std3 = sqrt($variance3);
		$popstd1 = sqrt($popvariance1);
		$popstd2 = sqrt($popvariance2);
		$popstd3 = sqrt($popvariance3);

		$cv1 = $std1 / $mean1;
		$cv2 = $cv3 = 0;
		if ($mean2 > 0) {
			$cv2 = $std2 / $mean2;
		}
		if ($mean3 > 0) {
			$cv3 = $std3 / $mean3;
		}

		$outliers = 0;

		$this->_stats = array(
			'count'=>$count,
			'total'=>$total1,
			'min'=>$min,
			'max'=>$max,
			'mode'=>$mode,
			'median'=>$median,
			'mean1'=>$mean1,
			'mean2'=>$mean2,
			'mean3'=>$mean3,
			'variance1'=>$variance1,
			'variance2'=>$variance2,
			'variance3'=>$variance3,
			'popvariance1'=>$popvariance1,
			'popvariance2'=>$popvariance2,
			'popvariance3'=>$popvariance3,
			'std1'=>$std1,
			'std2'=>$std2,
			'std3'=>$std3,
			'popstd1'=>$popstd1,
			'popstd2'=>$popstd2,
			'popstd3'=>$popstd3,
			'cv1'=>$cv1,
			'cv2'=>$cv2,
			'cv3'=>$cv3,
			'outliers'=>$outliers,
			'super_outliers'=>$outliers,
		);

		// echo implode(', ', array_keys($weights));
		// foreach ( $values as $value) {
		// 	$nums[] = $value['weight'];
		// }

		// echo implode(', ', $nums);

		return $this->_stats;
	}

	public function data() {
		$this->_stats['values'] = $this->_value;
		return $this->_stats;
	}

	public function weight ( $key, $weight = null ) {
		if (!isset($this->_value[$key])) {
			return null;
		}

		if ($weight === null) {
			return $this->_value[$key]['weight'];
		}

		// Explicitly set the weight and recompute percentage so that
		// external callers (for example, strategy scoring) can control
		// ordering without needing to manipulate the internal structure.
		$this->_value[$key]['weight'] = $weight;
		$this->_value[$key]['percentage'] = $this->findPercentage($weight);

		return $this->_value[$key]['weight'];
	}

	public function add( $object, $key = null, int $weight = 1 ) : ICollection
	{
		if (!is_scalar($key) && !is_null($key)) {
			throw new InvalidArgumentException('Label must be scalar');
		}

		if ( !is_null($key) && $this->has( $key ) ) {
			$this->_value[$key]['weight'] += $weight;
			$this->_value[$key]['timestamp'] = time();
			$this->_value[$key]['value'] = $object;

			$percentage = $this->findPercentage($this->_value[$key]['weight']);

			$this->_value[$key]['percentage'] = $percentage;
		} else {
			$this->_increment++;
			$total = count($this->_value);
			if ($total >= $this->_max) {
				$copy = $this->_value->getArrayCopy();
				end($copy);         // move the internal pointer to the end of the array
				$index = key($copy);
				$copy = $this->_value->offsetUnset($index);
			}
			$this->_value[$key] = $this->create($object, $weight);
		}

		if ( $this->_autosort) {
			$this->sort();
		}

		return $this;
	}

	protected function create($value, int $weight = 1) {
		$percentage = $this->findPercentage($weight);
		return ['weight'=>$weight, 'percentage'=>$percentage, 'value'=>$value, 'decay'=>$this->_decay, 'timestamp'=>time(), 'ordinance'=>$this->_increment];
	}

	protected function findPercentage( $amount ) {
		$total = 0;
		foreach ($this->_value as $value) {
			$total += $value['weight'];
		}

		return ($amount) / ($total > 0 ? $total : 1);
	}

	public function clear(): ICollection
	{
		parent::clear();
		$this->_increment = 0;

		return $this;
	}

	public function optimize($tolerance = 10, $noise = [])
    {
        $filtered = [];
        $current_time = time();

        foreach ($this->_value as $key => $value) {
            $age = $current_time - $value['timestamp'];
            $decayed_weight = $value['weight'] - floor($value['decay'] * $age);

            if ($decayed_weight >= $tolerance && !in_array($value['value'], $noise)) {
                $filtered[$key] = $value;
            }
        }

        $this->_value = new ArrayObject($filtered);
        $this->sort();
    }

	public function __toString() {
		return json_encode($this->data());
	}
}
