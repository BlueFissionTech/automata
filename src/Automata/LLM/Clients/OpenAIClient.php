<?php

namespace BlueFission\Automata\LLM\Clients;

use BlueFission\Services\Service;
use BlueFission\Automata\LLM\Connectors\OpenAI;
use BlueFission\Automata\LLM\Prompts\IPrompt;
use BlueFission\Automata\LLM\Reply;


class OpenAIClient extends Service implements IClient
{
	protected $_openAI;

	/**
	 * OpenAIService constructor.
	 */
	public function __construct( string $apiKey )
	{
		$this->_openAI = new OpenAI( $apiKey );
	}

	public function generate($input, $config = [], ?callable $callback = null): Reply
	{
		if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

		$response = $this->_openAI->generate($input, $config, $callback);

		return $this->processResponse($response);
	}

	/**
	 * Get GPT-3 completion based on the input.
	 *
	 * @param string $input
	 * @return array
	 */
	public function complete($input, $config = []): Reply
	{
		if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

		$response = $this->_openAI->complete($input, $config);

		return $this->processResponse($response);
	}

	/**
	 * Get GPT-3.5 chat completion based on the input.
	 *
	 * @param string $input
	 * @return array
	 */
	public function respond($input, $config = []): Reply
	{
		if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

		$response = $this->_openAI->respond($input, $config);

		return $this->processResponse($response);
	}

	/**
	 * Get image based on the input.
	 *
	 * @param string $prompt
	 * @param string $width
	 * @param string $height
	 * @return array
	 */
	private function image($prompt, $width = '256', $height = '256')
	{
		if ( $input instanceof IPrompt ) {
			$input = $input->prompt();
		}

		$response = $this->_openAI->image($prompt, $width, $height);
		
		return $response;
	}

	/**
	 * Process response from OpenAI.
	 * @param  $response The response from OpenAI
	 * @return Reply          The reply object
	 */
	private function processResponse($response)
	{
		$reply = new Reply();

		$success = false;

		$message = '';

		if (isset($response['error'])) {
			$message = $response['error']['message'];

			$reply->addMessage($message, $success);
		} elseif (isset($response['choices'])) {
			$success = true;
            if (isset($response['choices'])) {
            	foreach ($response['choices'] as $choice) {
            		if (isset($choice['message'])) {
            			$message = $choice['message']['content'];
	               	} elseif (isset($choice['text'])) {
	                    $message = $choice['text'];
	                }

                	$reply->addMessage($message, $success);
                }
            }
        } else {
        	$reply->addMessage("No response", $success);
        }

        return $reply;
	}
}