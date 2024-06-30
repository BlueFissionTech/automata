<?php
namespace BlueFission\Automata\LLM\Tools;

use App\Business\Services\OpenAIService;

class CodeGenerator extends BaseTool {
    protected $name = "Code Generator";
    protected $description = "Produces code and saves it as a file.";

    public function execute($language): string {
        $openAIService = new OpenAIService();
        $code = $openAIService->complete("Write a simple $language program.")['choices'][0]['text'];
        file_put_contents("/path/to/code/{$language}.txt", $code);
        return "/path/to/code/{$language}.txt";
    }
}