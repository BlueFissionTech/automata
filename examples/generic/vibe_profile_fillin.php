<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\FillIn;
use BlueFission\Automata\LLM\Reply;

class ExampleProfileClient implements IClient
{
    public array $prompts = [];

    public function __construct(private string $response)
    {
    }

    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $this->prompts[] = (string)$input;

        $reply = new Reply();
        $reply->addMessage($this->response, true);

        if ($callback) {
            $callback($this->response);
        }

        return $reply;
    }

    public function complete($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage($this->response, true);

        return $reply;
    }

    public function respond($input, $config = []): Reply
    {
        $reply = new Reply();
        $reply->addMessage($this->response, true);

        return $reply;
    }
}

$tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'automata_vibe_profiles_' . uniqid();
$profileDir = $tempRoot . DIRECTORY_SEPARATOR . 'profiles';
@mkdir($profileDir, 0777, true);
file_put_contents(
    $profileDir . DIRECTORY_SEPARATOR . 'editorial-book.vibe',
    "You are an editorial book agent.\nFavor concise, publication-ready prose."
);

$defaultClient = new ExampleProfileClient('default-draft');
$editorialClient = new ExampleProfileClient('editorial-draft');

$fileProfile = new FillIn(
    $defaultClient,
    'Draft intro: {=content profile="profiles/editorial-book.vibe"}'
);
$fileProfile->setProfilePaths([$tempRoot]);
$fileProfile->run();

$namedProfile = new FillIn(
    $defaultClient,
    'Draft intro: {=content profile="editorial-fast"}'
);
$namedProfile->registerProfileOverride('editorial-fast', [
    'driver' => $editorialClient,
    'prompt' => 'You are the fast editorial profile. Prefer sharp structure and clean copy.',
]);
$namedProfile->run();

echo json_encode([
    'file_profile_output' => $fileProfile->output(),
    'file_profile_prompt' => $defaultClient->prompts[0] ?? '',
    'named_profile_output' => $namedProfile->output(),
    'named_profile_prompt' => $editorialClient->prompts[0] ?? '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
