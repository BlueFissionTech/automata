<?php
namespace BlueFission\Framework\Chat\Tools;

use App\Business\Services\OpenAIService;

class NotePad extends BaseTool {
    protected $name = "Note Pad";
    protected $description = "Generates text on a given topic and saves it as a file.";

    public function execute($topic): string {
        $openAIService = new OpenAIService();
        $note = $openAIService->complete($topic)['choices'][0]['text'];
        file_put_contents("/path/to/notes/{$topic}.txt", $note);
        return "/path/to/notes/{$topic}.txt";
    }
}