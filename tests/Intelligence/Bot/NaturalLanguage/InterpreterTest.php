<?php 
namespace BlueFission\Tests\Bot\NaturalLanguage;

use BlueFission\Bot\NaturalLanguage\Interpreter;
// use BlueFission\Bot\NaturalLanguage\Grammar;

class InterpreterTest extends \PHPUnit_Framework_TestCase {
 	static $classname = 'BlueFission\Bot\NaturalLanguage\Interpreter';

	public function setup()
	{
		// $this->object = new static::$classname();
		$this->commands = array();
	}

	public function testInterpreterGeneratesStatement()
	{

	}

	/** 
 	 * expectedException Exception
 	 */
	public function testInterpreterExpectsToken()
	{
		$this->commands[] = "TYPE Person EXPECTS {'name'}";
		// $tokens = Interpreter::run($this->commands);
	}

	public function testInterpreterDefinesType()
	{
		$this->commands[] = "& TYPE Person EXPECTS {'origin'}";
		$this->commands[] = "& @guy LIKE Person";
		$this->commands[] = "? @guy";
		// $tokens = Interpreter::run($this->commands);
	}

	public function testInterpreterDefinesSetAndGet()
	{
		$this->commands[] = "& TYPE a EXPECTS {'name'}";
		// $tokens = Interpreter::run($this->commands);

	}

	public function testInterpreterExpressesLikeness()
	{
		$this->commands[] = "& @Tony LIKE {'name'}";
		// $this->commands[] = "& @Jack LIKE @Tony";

		$this->commands[] = "& @Tony MIGHT {'pizza'}";
		// $this->commands[] = "& DEFINE a";
		// $this->commands[] = "& a LIKE {Person}";
		// $this->commands[] = "? a.origin";
		// $tokens = Interpreter::run($this->commands);

	}

	public function testInterpreterUnderstandsPotentialLikeness()
	{
		$this->commands[] = "& a MIGHT {'pizza'}";
		// $tokens = Interpreter::run($this->commands);
	}

	public function testInterpreterExecutesOperations()
	{
		$this->commands[] = "& a DOES @omelet";
		// $tokens = Interpreter::run($this->commands);
	}

	public function testInterpreterChangesContext()
	{
		// CONTEXT
	}

	public function testInterpreterLoadsLibrary()
	{
		// USE
	}

	public function testInterpreterMarksIndex()
	{
		// INDEX
	}

	public function testInterpreterSavesDocument()
	{
		// COMMIT
	}

	public function testInterpreterDeletesNodes()
	{
		// FROM
	}

	public function testInterpreterDefinesBehaviors()
	{
		// DEFINE
	}

	public function testInterpreterRunsInteractiveMode()
	{
		// INTERACTIVE
	}

	public function testInterpreterUsesControlStructure()
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