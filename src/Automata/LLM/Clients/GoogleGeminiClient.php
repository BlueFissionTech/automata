<?php

namespace BlueFission\Automata\LLM\Clients;

use BlueFission\Services\Client;
use Gemini\Data\{GenerationConfig, SafetySetting};
use Gemini\Enums\{HarmBlockThreshold, HarmCategory};
use BlueFission\Automata\LLM\Prompts\IPrompt;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Arr;

class GoogleGeminiClient extends Client implements IClient {

	public function __construct( $apiKey ) {
		$this->_apiKey = $apiKey;
		$this->_client = \Gemini::client($this->_apiKey);
	}

    /**
     * Get completion based on the input.
     *
     * @param string $input
     * @return Reply
     */
    public function complete($input, $config = []): Reply
    {
    	if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

    	if ( Arr::size($config) > 0 ) {
    		$this->configPro($config);
    	}

        $result = $this->_client
        	->geminiPro()
        	->generateContent($input);
        
        return $this->processResponse($result);
    }

    public function generate($input, $config = [], ?callable $callback = null): Reply
	{
		if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

		if ( Arr::size($config) > 0 ) {
			$this->configPro($config);
		}

		$result = $this->_client
        	->geminiPro()
        	->generateContent($input);
        
        return $this->processResponse($result);
    }

    /**
     * Get completion based on the input.
     *
     * @param string $input
     * @return void
     */
    public function streamComplete($input, callable $callback, $config =  []): void
    {
    	if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

    	if ( Arr::size($config) > 0 ) {
    		$this->configPro($config);
    	}

        $result = $this->_client
        	->geminiPro()
        	->streamGenerateContent($input);

        foreach ($stream as $response) {
			$callback($response);
		}
    }

    public function vision($input, $base64ImageData = null): Reply
    {
    	if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

    	if ( Arr::size($config) > 0 ) {
    		$this->configProVision($config);
    	}

    	$prompt = [];

    	$prompt[] = $input;
    	if ($base64ImageData) {
    		$prompt[] = new Blob(
				mimeType: MimeType::IMAGE_JPEG,
				data: $base64ImageData
			);
		}

    	$result = $client
			->geminiProVision()
			->generateContent($prompt);

		return $this->processResponse($result);
    }

    /**
     * Get chat completion based on the input.
     *
     * @param string $input
     * @return array
     */
    public function respond($input, $config = [], $history = []): Reply
    {
    	if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

    	if ( Arr::size($config) > 0 ) {
    		$this->configPro($config);
    	}

    	$conversation = [];
    	
        $chat = $this->_client->geminiPro();
        if (Arr::size($history) > 0) {
    		foreach ($history as $item) {
    			$message = $item['message'] ?? '';
    			$role = $item['role'] ?? 0;

    			if ( $message == '' ) {
					continue;
				}

    			$conversation[] = Content::parse(part: $item['message'], role: $item['role'] == 0 ? Role::MODEL : null );
			}

			$this->_client->startChat(history: $conversation);
		}

		return new Reply($chat->sendMessage($input), true);
    }

    public function embeddings($input): array
	{
		if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

		$response = $this->_client
		 ->embeddingModel()
		 ->embedContent($inut);

		 return $response->embedding->values;
	}

    private function configPro($config) {

    	// Implement this
    	$safetySettingDangerousContent = new SafetySetting(
		    category: HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT,
		    threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
		);

		$safetySettingHateSpeech = new SafetySetting(
		    category: HarmCategory::HARM_CATEGORY_HATE_SPEECH,
		    threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
		);

    	$defaults = [
    		'max_tokens' => 800,
    		'stop' => [],
    		'temperature' => 1,
    		'top_p' => 0.8,
    		'top_k' => 10,
    		'frequency_penalty' => 0,
    		'presence_penalty' => 0,
    	];

    	$config = array_merge($defaults, $config);

		$generationConfig = new GenerationConfig(
			stopSequences: $config['stop'],
			maxOutputTokens: $config['max_tokens'],
			temperature: $config['temperature'],
			topP: $config['top_p'],
			topK: $config['top_k']
		);

		$this->_client->geminiPro()
			->withGenerationConfig($generationConfig)
			->withSafetySetting($safetySettingHateSpeech)
	 		->withGenerationConfig($generationConfig);
    }

    private function configProVision($config) {

    	// Implement this
    	$safetySettingDangerousContent = new SafetySetting(
		    category: HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT,
		    threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
		);

		$safetySettingHateSpeech = new SafetySetting(
		    category: HarmCategory::HARM_CATEGORY_HATE_SPEECH,
		    threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
		);

    	$defaults = [
    		'max_tokens' => 800,
    		'stop' => [],
    		'temperature' => 1,
    		'top_p' => 0.8,
    		'top_k' => 10,
    		'frequency_penalty' => 0,
    		'presence_penalty' => 0,
    	];

    	$config = Arr::merge($defaults, $config);

		$generationConfig = new GenerationConfig(
			stopSequences: $config['stop'],
			maxOutputTokens: $config['max_tokens'],
			temperature: $config['temperature'],
			topP: $config['top_p'],
			topK: $config['top_k']
		);

		$this->_client->geminiProVision()
			->withGenerationConfig($generationConfig)
			->withSafetySetting($safetySettingHateSpeech)
	 		->withGenerationConfig($generationConfig);
    }

    private function processResponse($response): Reply
    {
    	$reply = new Reply();

		$success = false;

		$message = $response->text();

		if ($message != '') {
			$success = true;
            $reply->addMessage($message, $success);
        } else {
        	$reply->addMessage("No response", $success);
        }

        return $reply;
    }
}