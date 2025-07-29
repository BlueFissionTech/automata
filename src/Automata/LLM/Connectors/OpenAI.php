<?php
//https://stackoverflow.com/questions/72711031/stream-data-from-openai-gpt-3-api-using-php
namespace BlueFission\Automata\LLM\Connectors;

use BlueFission\Connections\Curl;

class OpenAI
{
    protected $_api_key;
    protected $_curl;

    /**
     * OpenAIService constructor.
     */
    public function __construct( $apiKey )
    {
        $this->_api_key = $apiKey;
        $this->_curl = new Curl([
            'method' => 'post'
        ]);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->_api_key,
        ];

        $this->_curl->config('headers', $headers);
    }

    public function generate($input, $config = [], ?callable $callback = null )
    {
        if ($callback === null) {
            $callable = function(&$input) {};
        }

        $request_data = array_merge([
            'prompt' => $input,
            'model' => 'gpt-3.5-turbo-instruct',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.6,
            'stop' => null,
            'stream' => true
        ], $config);

        $this->_curl->clear();
        $this->_curl->config('target', 'https://api.openai.com/v1/completions');

        $buffer = '';

        $this->_curl->option(CURLOPT_WRITEFUNCTION, function($curl, $data) use ($callback, &$buffer) {
            $buffer .= $data;
            // Process multiple JSON objects if present (newline-delimited)
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);
                // Only lines starting with "data: " are valid stream lines
                if (str_starts_with($line, 'data: ')) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') {
                        break;
                    }

                    $decoded = json_decode($jsonStr, true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        // Incomplete JSON fragment; re-buffer
                        $buffer = $line . "\n" . $buffer;
                        break;
                    }

                    if (isset($decoded['error'])) {
                        throw new \Exception($decoded['error']['message']);
                    }

                    if (isset($decoded['choices'][0]['text'])) {
                        $complete = call_user_func($callback, $decoded['choices'][0]['text']);
                        if ($complete === true) {
                            curl_setopt($this->_curl->connection(), CURLOPT_NOBODY, true);
                            break;
                        }
                    }
                }
            }

            return strlen($data);
        });

        $this->_curl->open();
        $this->_curl->query($request_data);
        $this->_curl->close();
    }

    /**
     * Get GPT-3 completion based on the input.
     *
     * @param string $input
     * @return array
     */
    public function complete($input, $config = [])
    {
        $request_data = [
            'prompt' => $input,
            'model' => 'gpt-3.5-turbo-instruct',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.6,
            'stop' => null
        ];

        $request_data = array_merge($request_data, $config);

        $this->_curl->clear();
        $this->_curl->config('target', 'https://api.openai.com/v1/completions');
        $this->_curl->open();
        $this->_curl->query($request_data);
        $response = $this->_curl->result();
        $this->_curl->close();

        return json_decode($response, true);
    }

    /**
     * Get GPT-3.5 chat completion based on the input.
     *
     * @param string $input
     * @return array
     */
    public function respond($input, $config = [])
    {
        $request_data = [
            'messages' => [['role' => 'user', 'content' => $input]],
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.6,
            'stop' => null
        ];

        $request_data = array_merge($request_data, $config);

        $this->_curl->clear();
        $this->_curl->config('target', 'https://api.openai.com/v1/chat/completions');
        $this->_curl->open();
        $this->_curl->query($request_data);
        $response = $this->_curl->result();
        $this->_curl->close();

        return json_decode($response, true);
    }

    /**
     * Get GPT-3.5 chat completion based on the input.
     *
     * @param string $input
     * @return array
     */
    public function chat($input, $config = [])
    {
        if ( is_string($input)) {
            $messages = [['role' => 'user', 'content' => $input]];
        } else if ( is_array($input)) {
            $messages = $input;
        } else {
            throw new \Exception('Invalid input type.');
        }


        $request_data = [
            'messages' => $messages,
            // 'model' => 'gpt-3.5-turbo',
            'model' => 'gpt-4o-mini',
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.6,
            'stop' => null
        ];

        $request_data = array_merge($request_data, $config);

        $this->_curl->clear();
        $this->_curl->config('target', 'https://api.openai.com/v1/chat/completions');
        $this->_curl->open();
        $this->_curl->query($request_data);
        $response = $this->_curl->result();
        $this->_curl->close();

        return json_decode($response, true);
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
        $request_data = [
            'prompt' => $prompt,
            'model' => 'image-alpha-001',
            'size' => "{$width}x{$height}",
        ];

        $this->_curl->config('target', 'https://api.openai.com/v1/images/generations');
        $this->_curl->open();
        $this->_curl->query($request_data);
        $response = $this->_curl->result();
        $this->_curl->close();

        return json_decode($response, true);
    }

    /**
     * Get embeddings from the Ada model based on the input.
     *
     * @param string $input
     * @return array
     */
    public function embeddings($input)
    {
        $request_data = [
            'prompt' => $input,
            'model' => 'text-ada-002',
            'max_tokens' => 1, // Set the number of tokens to be generated
            'n' => 1, // Number of completions to generate for each prompt
            'stop' => null,
            'temperature' => 1,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ];

        $this->_curl->config('target', 'https://api.openai.com/v1/completions');
        $this->_curl->open();
        $this->_curl->query($request_data);
        $response = $this->_curl->result();
        $this->_curl->close();

        return json_decode($response, true);
    }
}