<?php
namespace BlueFission\Framework\Chat\Tools;

use App\Business\Services\OpenWeatherService;

class Weather extends BaseTool {
    protected $name = "Weather Tool";
    protected $description = "Returns the current weather for a given location.";

    public function execute($location): string {
        $openWeatherService = new OpenWeatherService();
        $weather = $openWeatherService->getWeatherByLocation($location);
        return $weather;
    }
}
