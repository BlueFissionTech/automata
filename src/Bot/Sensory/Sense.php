<?php

namespace BlueFission\Bot\Sensory;

use BlueFission\Bot\Collections\OrganizedCollection;
use BlueFission\Behavioral\Programmable;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Action;
/**
 * Possible senses: visual, textual, auditory
 */

class Sense extends Programmable {
	const MAX_ATTENTION = 1048576;
	const MAX_SENSITIVITY = 10;
	protected $_config = array(
		'attention' => 1024, // TL,DR
		'sensitivity' => 10, // How deep are we willing to consider this?
		'quality' => 1, // Sample rate of the input
		'tolerance' => 100, // How much are we discerning the difference between chunks?
		'dimensions' => array(100), // Adding a dimension to how we consider the experience
		'features' => array('/\s/'),
		'flags' => array('OnNewInput'), // What are our foremost concerns?
		'blacklist' => array(), // What data do we learn to ignore??
		'chunksize' => 1, // how large is the smallest usable "chunk" of input data?
	);

	private $_candidates = array();
	private $_consistency = 0;
	private $_accuracy = 0;
	private $_score = 0;
	private $_parent = null;

	private $_map;
	private $_input;

	protected $_preparation;

	public function __construct( $parent ) {
		parent::__construct();

		$this->_parent = $parent;
		$this->_map = new OrganizedCollection();
		// $this->config('chunksize', $this->_config['dimensions'][0]);

		$this->_preparation = function ( $input ) {
			// Do something
	        // return preg_split($this->boundaries[0], strtolower($input), -1, PREG_SPLIT_NO_EMPTY);
	        return str_split ( (string)$input, $this->config('chunksize') );
		};
	}

	protected function prepare( $input ) {
		return call_user_func_array($this->_preparation, array($input));
	}

	private function setPreparation( $function ) {
		$this->_preparation = $function;
	}

	public function invoke( $input ) {
		$parent = $this->_parent;

		$this->_input = $input; 

		$input = $this->prepare($input);
		
		$size = count($input)*$this->config('quality');

		$increment = floor( 1 / $this->config('quality') );
		$multiplier = .001;

		$i = $j = 0;
		$k = 1;

		$this->_map->clear();

		// sensitivity loops over the data multiple times to find different types of properties
		while ( $i <= $this->config('attention') && $j <= $this->config('sensitivity') && $i < $size ) {
			if ( $i > $size ) {

				$i = 0;
				$j++;

				$this->_map->sort();
				$stats = $this->_map->stats();
				$min = $stats['min'];
				$max = $stats['max'];
				$std1 = $stats['std1'];

				$diff = $max - $min;

				if ( $std1 >= ($diff*.25) ) {
					$j--;
				}

				if (!isset($this->_config['dimensions'][$j])) {
					break;
				}
			}

			// Dimesions map more vectors from the data based on signal properties
			if ( $k == $this->_config['dimensions'][$j]*$this->config('quality')) {
				$k = 1;
			}

			if ( in_array($input, $this->_config['blacklist']) ) {
				continue;
			}

			// $chunk = trim($input[$i]);
			$chunk = $input[$i];

			/* 
				What we want to do here is this:
				Translate the $chunk using a classification / association index
				Get the translation key and see if we have "learned" this association yet. 
				Increase attention from novelty
				Look for a $flag that insinuates the need for a deeper level of quality and/or sensitivity
				create a map then do regression/classification with euclidean distance testing on in a scene 
				create a 'holoscene' by association correlated points (consideration)
			*/

			$translation = $this->translate($chunk);
			// echo "$chunk\n";
			
			if ( !$this->_map->has($chunk) ) {
				$this->_config['attention'] += $this->_config['attention'] < self::MAX_ATTENTION ? $this->_config['dimensions'][$j]*$this->_config['quality'] : 0; // increase attention from novelty
				$muliplier = .001;
			} else {
				// Or prepare to get bored.
				$muliplier = -.001;
			}

			if ( $this->_config['quality'] > 0 && $this->_config['quality'] < 1 ) {
				$this->_config['quality'] += $muliplier;
			}

			if ( !$parent->can($translation) ) {
				$parent->behavior($translation, array($this, 'callback') );
			}

			$this->_map->add($chunk, $translation);

			if ( $translation == $this->_config['flags'][$j] ) {
				$this->_config['attention'] += $this->_config['attention'] < self::MAX_ATTENTION ? $this->_config['dimensions'][$j]*$this->_config['quality'] : 0; // increase attention from activity
				$this->_config['sensitivity'] += $this->_config['sensitivity'] <= self::MAX_SENSITIVITY ? 1 : 0;

				// $parent->perform($translation, $chunk);
				// $this->dispatch($translation, $chunk);
			}

			$k++;
			$i+=$increment;
		}

		$this->_map->sort();
		$this->_map->stats();

		$data = $this->_map->data();

		$this->dispatch(Event::COMPLETE, $data);
	}

	public function setParent( $obj ) {
		$this->_parent = $obj;
	}

	public function callback( $obj ) {
		// var_dump($obj);
	}

	public function focus( $behavior, $data ) {
		$data = $data[0];
		$this->dispatch('OnSweep', $data);
		
		if ( $data['variance1'] < 1 ) {

			$this->tweak();
			// $this->invoke($this->_input);
			$event = new Action('DoEnhance');
			$event->_context = array('config'=>$this->_config,'input'=>$this->_input);
			$this->dispatch($event);
			// $this->dispatch('DoEnhance', array('config'=>$this->_config,'input'=>$this->_input));
		} else {
			// echo $data['variance1'];
			// var_dump($this->_map->first());
			// $data['values'] = null;
			die(var_dump($data['values']));
		}
		// if ( $this->_config['attention'] ) {
		// 	$this->_config['quality'] =
		// }
	}

	private function translate( $chunk ) {
		// If can't classify, classify input as itself.
		if ( $this->_map->has($chunk) ) {
			return $chunk;
		}
		return $chunk;
		
		$this->dispatch('OnCapture', $chunk);
	}

	// https://stackoverflow.com/questions/336605/how-can-i-find-the-largest-common-substring-between-two-strings-in-php
	private function longest_common_substring($words) {
	    // $words = array_map('strtolower', array_map('trim', $words));
	    $sort_by_strlen = create_function('$a, $b', 'if (strlen($a) == strlen($b)) { return strcmp($a, $b); } return (strlen($a) < strlen($b)) ? -1 : 1;');
	    usort($words, $sort_by_strlen);
	    // We have to assume that each string has something in common with the first
	    // string (post sort), we just need to figure out what the longest common
	    // string is. If any string DOES NOT have something in common with the first
	    // string, return false.
	    $longest_common_substring = array();
	    $shortest_string = str_split(array_shift($words));

	    while (sizeof($shortest_string)) {
	        array_unshift($longest_common_substring, '');
	        foreach ($shortest_string as $ci => $char) {
	            foreach ($words as $wi => $word) {
	                if (!strstr($word, $longest_common_substring[0] . $char)) {
	                    // No match
	                    break 2;
	                } // if
	            } // foreach
	            // we found the current char in each word, so add it to the first longest_common_substring element,
	            // then start checking again using the next char as well
	            $longest_common_substring[0].= $char;
	        } // foreach
	        // We've finished looping through the entire shortest_string.
	        // Remove the first char and start all over. Do this until there are no more
	        // chars to search on.
	        array_shift($shortest_string);
	    }
	    // If we made it here then we've run through everything
	    usort($longest_common_substring, $sort_by_strlen);
	    return array_pop($longest_common_substring);
	}

	private function tweak( ) {
		$order = array(
			// 'attention'=>array('up', 1, self::MAX_ATTENTION),
			'chunksize'=>array('down', 1, $this->_config['dimensions']),
			'tolerance'=>array('down', 1, 100),
			// 'sensitivity'=>array('up', 1, $self::MAX_SENSITIVITY),
			'dimensions'=>array('up', 1, 7),
		);

		foreach ( $order as $attr=>$limits ) {
			if ($this->_config[$attr] >= $limits[1] && $this->_config[$attr] <= $limits[2]) {
				$this->_config[$attr] += $limits[0] == 'up' ? 1 : -1;
				
				return;
			}
		}
	}

	protected function init() {
		parent::init();

		// $this->behavior( new Event( Event::UPDATE ) );
		$this->behavior( new Event( Event::COMPLETE ), array($this, 'focus'));
		$this->behavior( new Event( 'OnCapture' ) );
	}
}