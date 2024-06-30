<?php
namespace BlueFission\Automata\LLM\Tools;

use App\Business\Services\OpenAIService;

class ImageGenerator extends BaseTool {
    protected $name = "Dall-E Image Generator";
    protected $description = "Generates images using Dall-E.";

    public function execute($input): string {
        $openAIService = new OpenAIService();
        $imageResponse = $openAIService->image($input);

        // Extract the image URL from the response.
        // Note: You'll need to adjust this line based on the actual response format.
        $imageUrl = $imageResponse['image_url'];

        // Download the image data.
        $imageData = file_get_contents($imageUrl);

        // Determine a location to save the image.
        // Note: This directory must exist and be writable by the web server.
        // It should also be accessible from the web, so the image can be served to users.
        // Adjust the path as necessary for your server configuration.
        $saveDirectory = "/path/to/web-accessible/directory/";
        $filename = uniqid("dall_e_") . ".png";
        $savePath = $saveDirectory . $filename;

        // Save the image data to the file.
        file_put_contents($savePath, $imageData);

        // Construct the URL of the saved image.
        // Note: You'll need to adjust the URL based on your server configuration.
        $imageUrl = "http://yourdomain.com/path/to/web-accessible/directory/" . $filename;

        return $imageUrl;
    }
}
