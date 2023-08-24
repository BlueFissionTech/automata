<?php
// The sapphire of wisdom

class Composer extends Programmable {
	
	protected $_tree = array();

	public function example() {
		$this->_tree = array(
			'blocks'=>array(
				0=>array(
					'sentences'=>array(
						1=>array(
							'relation'=>'add',
							'sentiments'=>array(
								'attitude'=>'',
								'injunction'=>'',
								'mood'=>'',
								'respect'=>''
							),
							'entities'=> array(
								0=>array(
									0=>array(
										'referant'=>'tree',
										'determiner'=>1,
										'properties'=>array('big','tall')
									),
									1=>array(
										'referant'=>2345,
										'determiner'=>3,
										'properties'=>null
									)
								),
								1=>array(
									0=>array(
										'referant'=>'tree',
										'determiner'=>1,
										'properties'=>array('big','tall')
									),
								)
							), // end first entities
							'operators'=>array(
								0=>array(
									'behavior'=>'',
									'modality'=>'',
									'modifier'=>'',
								)
							),
							'positions'=>array(
								0=>array(
									'director'=>'to',
									'time'=>'',
									'place'=>''
								)
							)
						)
					)
				),
				1=>array(
					'sentences'=>array, 

				)
			)
		);
	}

	public function parse( $statement ) {

	}

	public function buildPhrase() {

	}
}