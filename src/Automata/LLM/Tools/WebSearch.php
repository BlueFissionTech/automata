<?php
namespace BlueFission\Framework\Chat\Tools;

use App\Business\Services\DuckDuckGoSearchService;

class WebSearch extends BaseTool {
    protected $name = "WebSearch";
    protected $description = "Returns search results from a given search query.";

    public function execute($query): string {
        $duckDuckGoSearchService = new DuckDuckGoSearchService();
        $results = $duckDuckGoSearchService->search($query);
        return json_encode($results);
    }
}