<?php 
namespace BlueFission\Tests\Bot\NaturalLanguage;

use BlueFission\Bot\NaturalLanguage\Documenter;
use BlueFission\Bot\NaturalLanguage\Tokenizer;
use BlueFission\Bot\NaturalLanguage\Walker;

class WalkerTest extends \PHPUnit_Framework_TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Walker';

	public function setup()
	{
		$this->object = new static::$classname();
		$this->commands = array();
	}

	public function testWalkerDefinesType()
	{
		$this->commands[] = "& TYPE Person EXPECTS {'origin':'last year'}";
		$this->commands[] = "& @guy LIKE Person";
		$this->commands[] = "? @guy";

		$tokens = Tokenizer::parse($this->commands);

		$doc = new Documenter();
		foreach ( $tokens as $token ) {
			$doc->push($token);
			// $doc->process();
		}
		
		$tree = $doc->getTree();
		
		// $output = Walker::traverse($tree);
	}

	public function testWalkerDefinesSetAndGet()
	{
		$this->commands[] = "& TYPE a EXPECTS {'name'}";
		// $tokens = Walker::run($this->commands);

	}

	public function testWalkerExpressesLikeness()
	{
		$this->commands[] = "& @Tony LIKE {'name'}";
		// $this->commands[] = "& @Jack LIKE @Tony";

		$this->commands[] = "& @Tony MIGHT {'pizza'}";
		// $this->commands[] = "& DEFINE a";
		// $this->commands[] = "& a LIKE {Person}";
		// $this->commands[] = "? a.origin";
		// $tokens = Walker::run($this->commands);

	}

	public function testWalkerUnderstandsPotentialLikeness()
	{
		$this->commands[] = "& a MIGHT {'pizza'}";
		// $tokens = Walker::run($this->commands);
	}

	public function testWalkerExecutesOperations()
	{
		$this->commands[] = "& a DOES @omelet";
		// $tokens = Walker::run($this->commands);
	}

	public function testWalkerChangesContext()
	{
		// CONTEXT
	}

	public function testWalkerLoadsLibrary()
	{
		// USE
	}

	public function testWalkerMarksIndex()
	{
		// INDEX
	}

	public function testWalkerSavesWalker()
	{
		// COMMIT
	}

	public function testWalkerDeletesNodes()
	{
		// FROM
	}

	public function testWalkerDefinesBehaviors()
	{
		// DEFINE
	}

	public function testWalkerRunsInteractiveMode()
	{
		// INTERACTIVE
	}

	public function testWalkerUsesControlStructure()
	{
		// HANDLES
	}

	/*
		"needs" hard typing. Cannot instantiate without these properties.

		DEFINE homonid NEEDS {"origin"};

		"expects" can update in def. Instantiates without these

		DEFINE person LIKE homonid EXPECTS {"name"};

		"like" returns matches between two sets

		? person LIKE homonid

		"might" definition allows set to fit into other set criteria

		& person MIGHT {"move"}

		"does" defines properties to update in an operation

		& person DOES cooking TO eggs

		"could" has properties in definition to fulfill "does" function

		-----

		"will" defines operations to conduct in given sitatuation
		"would" is operation condition defined

		"handles" event handler
		"should" defines if handler is defined

		"commits" publishes property as public
		"queries" requests property to be satisfied

		"intends" describes design of particular object
		"must" parameters for conditions object requires to persist

	*/
}