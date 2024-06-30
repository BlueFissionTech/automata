<?php
namespace BlueFission\Framework\Chat\Tools;

use App\Business\Services\OpenAIService;

class WebBrowser extends BaseTool {
    protected $name = "Web Browser";
    protected $description = "Returns the summary of a page by URL.";

    public function execute($url): string {
        $htmlContent = file_get_contents($url);
        $textContent = strip_tags($htmlContent);
        
        $openAIService = new OpenAIService();
        $summary = $openAIService->complete("Please summarize the following text: $textContent")['choices'][0]['text'];
        return $summary;
    }
}
