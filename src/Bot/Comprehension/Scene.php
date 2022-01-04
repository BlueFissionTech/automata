<?php 

class Scene {
	
	private $_drive;
	private $_analyzer

	private $_frames;
	private $_new;
	private $_variance;
	private $_groups;
	private $_stack;

	private $_group_tolerance = 100;
	private $_variance_tolerance = 100;
	private $_new_tolerance = 50;

	private $_buffer_size = 10;

	public function __construct( ) {

	}

	public function analyze() {

	}
}