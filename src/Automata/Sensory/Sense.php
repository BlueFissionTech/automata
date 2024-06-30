<?php

namespace BlueFission\Automata\Sensory;

use BlueFission\Automata\Collections\OrganizedCollection;
use BlueFission\Behavioral\Programmable;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Action;
use BlueFission\Automata\Language\Preparer;
use BlueFission\Str;

/**
 * Possible senses: visual, textual, auditory
 */

class Sense extends Programmable {
	// Constants to define maximum values for attention, sensitivity, and depth
	const MAX_ATTENTION = 1048576;
	const MAX_SENSITIVITY = 10;
	const MAX_DEPTH = 7;

	// Configuration settings for the Sense object
	protected $_config = [
		'attention' => 1024, // TL,DR ; Maximum units of attention
		'sensitivity' => 10, // How deep are we willing to consider this?
		'quality' => 1, // Sample rate of the input
		'tolerance' => 100, // Difference tolerance between chunks - How much are we discerning the difference between chunks?
		'dimensions' => [80,24,1024,8], // Adding a dimension to how we consider the experience
		'features' => ['/\s/'], // Features to detect in the input
		'flags' => ['OnNewInput'], // What are our foremost concerns?
		'chunksize' => 8, // how large is the smallest usable "chunk" of input data?
	];

	private $_settings = [];
	private $_candidates = [];
	private $_consistency = 0;
	private $_accuracy = 0;
	private $_score = 0;
	private $_depth = -1;
	private $_parent = null;

	private $_map;
	private $_input;
	private $_matrix = [];
	private $_buffer = [];
	private $_bufferSize = 32;

	protected $_preparation;

	/**
     * Constructor initializes the Sense object with an optional parent.
     *
     * @param object|null $parent Optional parent object.
     */
	public function __construct( $parent = null ) {
		parent::__construct();

		$this->_parent = $parent;
		$this->_map = new OrganizedCollection();
		$this->_map->autoSort(false);
		$this->reset();
		// $this->config('chunksize', $this->_config['dimensions'][0]);

		// Default preparation function for input processing
		$this->_preparation = function ( $input ) {
			// Do something
	        if ($input) {
	        	if ($this->_depth == 0) {
	        		$preparer = new Preparer();
	        		$array = $preparer->tokenize($input);
				} else {
					// die(var_dump($input));
	        		// return str_split ( (string)$input, $this->_settings['chunksize'] );
					$array = [];
					$length = Str::len((string)$input);
					$chunksize =  $length <= $this->_settings['chunksize'] ? $length : $this->_settings['chunksize'];
					if ( $this->_settings['chunksize'] != $chunksize ) {
						$this->_settings['chunksize'] = $chunksize;
					}

					for ($i = 0; $i < ($length-$chunksize)+1; $i++) {
						$array[] = Str::sub((string)$input, $i, $chunksize );
					}

	        		return $array;
	        	}
	        }
	        return [];
		};
	}

	/**
     * Resets the Sense object to its initial configuration.
     */
	public function reset()
	{
		$this->_settings = $this->_config;
		$this->_map->clear();
		$this->_depth = -1;
	}

	/**
     * Builds the internal matrix for storing input data.
     *
     * @param array $input The input data to build the matrix from.
     */
	protected function build( $input )
	{
		$data = [];
		foreach ($input as $piece) {
			// if ( $this->_depth > 0) {
			// 	$data = array_merge($data, str_split((string)$piece, 1));
			// } else {
			// 	$data[] = $piece;
			// }
			$data[] = $piece;
		}

		$i = 0;
		$j = 0;
		// $remainder = $this->_settings['dimensions'][0];
		foreach ($this->_matrix as $row) {
			if ( count($this->_matrix[$i]) < $this->_settings['dimensions'][0] ) {
				// $remainer -= count($this->_matrix[$i]);
				break;
			}
		}


		foreach ($data as $datum) {
			if ( $j >= $this->_settings['dimensions'][0] ) {
				$i++;
				$j = 0;
			}

			$this->_matrix[$i][$j] = $datum;
			$j++;
		}
	}

	/**
     * Prepares the input data using the specified preparation function.
     *
     * @param mixed $input The input data to prepare.
     * @return array Prepared input data.
     */
	protected function prepare( $input ) {
		return call_user_func_array($this->_preparation, [$input]);
	}

	/**
     * Sets a custom preparation function for processing input data.
     *
     * @param callable $function The custom preparation function.
     */
	public function setPreparation( $function ) {
		$this->_preparation = $function;
	}

	/**
     * Buffers the processed data and translates it for further processing.
     *
     * @param mixed $data The data to buffer.
     * @return mixed The translated data.
     */
	protected function buffer( $data ) {
		$this->_buffer[] = $data;
		$translation = $this->translate($data);
		return $translation;
	}

	/**
     * Invokes the sense processing on the given input.
     *
     * @param mixed $input The input data to process.
     */
	public function invoke( $input ) {
		$this->_depth++;

		$parent = $this->_parent;

		$this->_buffer = [];

		$this->_input = $input;

		$input = $this->prepare($this->_input);

		$this->build($input);

		// die(var_dump($this->_input));
		// var_dump($input);
		
		$size = count($input)*$this->_settings['quality'];

		$increment = floor( 1 / $this->_settings['quality'] );
		$multiplier = .001;

		$col = $row = $i = $j = 0;
		// $k = 1;

		$this->_map->clear();

		// sensitivity loops over the data multiple times to find different types of properties
		while ( $i <= $this->_settings['attention'] && $j <= $this->_settings['sensitivity'] && $i < $size ) {
		// while ( $i <= 20 ) {
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

				if (!isset($this->_settings['dimensions'][$j])) {
					break;
				}
			}

			// Dimesions map more vectors from the data based on signal properties
			// if ( $k == $this->_config['dimensions'][$j]*$this->config('quality')) {
			// 	$k = 1;
			// }

			// $chunk = trim($_this->_matrix[$row]);
			$chunk = $this->_matrix[$row][$col];

			/* 
				What we want to do here is this:
				Translate the $chunk using a classification / association index
				Get the translation key and see if we have "learned" this association yet. 
				Increase attention from novelty
				Look for a $flag that insinuates the need for a deeper level of quality and/or sensitivity
				create a map then do regression/classification with euclidean distance testing on in a scene 
				create a 'holoscene' by association correlated points (consideration)
			*/
		
			if (!Arr::has($this->config('blacklist'), $chunk ) || $this->_depth > 0 ) {
				$translation = $this->buffer($chunk);
				// $translation = $this->translate($chunk);
				// echo "$chunk\n";
				if ( !$this->_map->has($chunk) ) {
					$this->_settings['attention'] += $this->_settings['attention'] < self::MAX_ATTENTION ? $this->_settings['dimensions'][$j]*$this->_settings['quality'] : 0; // increase attention from novelty
					$multiplier = .001;
				} else {
					// Or prepare to get bored.
					$multiplier = -.001;
				}

				if ( $this->_settings['quality'] > 0 && $this->_settings['quality'] < 1 ) {
					$this->_settings['quality'] += $multiplier;
				}

				if ( $parent && !$parent->can($translation) ) {
					$parent->behavior($translation, [$this, 'callback'] );
				}

				$this->_map->add($chunk, $translation);

				if ( $translation == $this->_settings['flags'][$j] ) {
					$this->_settings['attention'] += $this->_settings['attention'] < self::MAX_ATTENTION ? $this->_settings['dimensions'][$j]*$this->_settings['quality'] : 0; // increase attention from activity
					$this->_settings['sensitivity'] += $this->_settings['sensitivity'] <= self::MAX_SENSITIVITY ? 1 : 0;

					// $parent->perform($translation, $chunk);
					// $this->dispatch($translation, $chunk);
				}
			}

			$i++;
			$this->_settings['attention']--;
			// $k++;
			$col+=$increment;
			if ( $col >= $this->_settings['dimensions'][0]) {
				$col = 0;
				$row+=$increment;
				if ( $row >= $this->_settings['dimensions'][1]) {
					break; // Add more dimensions here or break into next "frame" of experience
				}
			}
		}

		$this->_map->sort();
		$this->_map->stats();

		$data = $this->_map->data();

		$this->dispatch(Event::SUCCESS, $data);
		$this->focus($data);
	}

	/**
     * Sets the parent object for the Sense instance.
     *
     * @param object $obj The parent object.
     */
	public function setParent( $obj ) {
		$this->_parent = $obj;
	}

	/**
     * Callback function for handling behaviors.
     *
     * @param object $obj The behavior object.
     */
	public function callback( $obj ) {
		// var_dump($obj);
	}

	/**
     * Focuses on the processed data and tweaks settings if necessary.
     *
     * @param array $data The processed data.
     */
	protected function focus( $data ) {
		// $data = $data[0];
		$this->dispatch('OnSweep', $data);
		
		if ( $data['variance1'] < 1 ) {

			$this->tweak();
			$this->_map->optimize();
			$data = $this->_map->data();

			// $this->invoke($this->_matrix);
			$event = new Action('DoEnhance');
			$event->_context = ['config'=>$this->_settings,'input'=>$this->_input, 'data'=>$data];
			$this->dispatch($event);

			if ($this->_depth < self::MAX_DEPTH) {
				$this->invoke($this->_input); // Recurse until it gets bored
			}
			// $this->dispatch('DoEnhance', ['config'=>$this->_config,'input'=>$this->_matrix]);
		}
		// echo $data['variance1'];
		// var_dump($this->_map->first());
		// $data['values'] = null;
		// die(var_dump($data['values']));
		$this->_map->optimize();
		$data = $this->_map->data();
		$this->dispatch(Event::COMPLETE, $data);
		// if ( $this->_config['attention'] ) {
		// 	$this->_config['quality'] =
		// }
		// die();
	}

	/**
     * Translates a chunk of data into a unique identifier.
     *
     * @param string $chunk The data chunk.
     * @return int The unique identifier for the chunk.
     */
	private function translate( $chunk ) {
		// If can't classify, classify input as itself.
		// die(var_dump($chunk));
		// if ( $this->_map->has($chunk) ) {
		// 	return $chunk;
		// }
		$this->dispatch('OnCapture', $chunk);
		
		return crc32 ($chunk);
		
		// return $chunk;
	}

	/**
     * Finds the longest common substring among a set of words.
     *
     * @param array $words The set of words.
     * @return string The longest common substring.
     */
	// https://stackoverflow.com/questions/336605/how-can-i-find-the-largest-common-substring-between-two-strings-in-php
	private function longest_common_substring($words) {
	    // $words = array_map('strtolower', array_map('trim', $words));
	    $sort_by_strlen = create_function('$a, $b', 'if (strlen($a) == strlen($b)) { return strcmp($a, $b); } return (strlen($a) < strlen($b)) ? -1 : 1;');
	    usort($words, $sort_by_strlen);
	    // We have to assume that each string has something in common with the first
	    // string (post sort), we just need to figure out what the longest common
	    // string is. If any string DOES NOT have something in common with the first
	    // string, return false.
	    $longest_common_substring = [];
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

	/**
     * Tweaks the configuration settings based on certain rules.
     */
	private function tweak( ) {
		$order = [
			// 'attention'=>['up', 1, self::MAX_ATTENTION],
			'chunksize'=>['down', 1, $this->_config['dimensions']],
			'tolerance'=>['down', 1, 100],
			// 'sensitivity'=>['up', 1, $self::MAX_SENSITIVITY],
			'dimensions'=>['up', 1, 7],
		];

		foreach ( $order as $attr=>$limits ) {
			if ($this->_settings[$attr] >= $limits[1] && $this->_settings[$attr] <= $limits[2]) {
				$this->_settings[$attr] += $limits[0] == 'up' ? 1 : -1;
				
				return;
			}
		}
	}

	/**
     * Initializes the Sense object, setting up behaviors.
     */
	protected function init() {
		parent::init();

		// $this->behavior( new Event( Event::SUCCESS ), [$this, 'focus'] );
		$this->behavior( new Event( Event::SUCCESS ) );
		$this->behavior( new Event( Event::COMPLETE ) );
		$this->behavior( new Event( 'OnCapture' ) );
	}
}