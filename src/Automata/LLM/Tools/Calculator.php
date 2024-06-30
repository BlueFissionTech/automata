<?php
namespace BlueFission\Framework\Chat\Tools;

class Calculator extends BaseTool
{
    protected $name = "Calculator";
    protected $description = "Useful for when you need to answer questions about math.";

    public function execute($input): string
    {
        // Implement the logic to perform a calculation here
        // Make sure to safely evaluate the input
        return eval("return $input;");
    }
}