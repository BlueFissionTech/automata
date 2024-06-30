<?php
namespace BlueFission\Framework\Chat\Tools;

class DateTime extends BaseTool {
    protected $name = "Date Time Tool";
    protected $description = "Returns the current date and time.";

    public function execute($input): string {
        return date("Y-m-d H:i:s");
    }
}