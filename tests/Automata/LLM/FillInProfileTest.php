<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\FillIn;
use BlueFission\Automata\LLM\Reply;
use PHPUnit\Framework\TestCase;

class RecordingClient implements IClient
{
    public array $prompts = [];
    private string $response;

    public function __construct(string $response)
    {
        $this->response = $response;
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

class ReplyOnlyClient extends RecordingClient
{
    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $this->prompts[] = (string)$input;

        $reply = new Reply();
        $reply->addMessage('reply-only', true);

        return $reply;
    }
}

class FillInProfileTest extends TestCase
{
    public function testFillInUsesReplyOutputWhenClientDoesNotStream(): void
    {
        $client = new ReplyOnlyClient('unused');
        $fillIn = new FillIn($client, 'Hello {=content}');

        $fillIn->run();

        $this->assertSame('Hello reply-only', $fillIn->output());
        $this->assertCount(1, $client->prompts);
        $this->assertSame('Hello ', $client->prompts[0]);
    }

    public function testProfileFileIsPrependedToPromptContext(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'automata_fillin_' . uniqid();
        $profiles = $dir . DIRECTORY_SEPARATOR . 'profiles';
        mkdir($profiles, 0777, true);
        file_put_contents($profiles . DIRECTORY_SEPARATOR . 'editorial-book.vibe', 'You are an editorial book agent.');

        $client = new RecordingClient('profiled');
        $fillIn = new FillIn($client, 'Hello {=content profile="profiles/editorial-book.vibe"}');
        $fillIn->setProfilePaths([$dir]);

        $fillIn->run();

        $this->assertSame('Hello profiled', $fillIn->output());
        $this->assertCount(1, $client->prompts);
        $this->assertStringStartsWith("You are an editorial book agent.\n\nHello ", $client->prompts[0]);
    }

    public function testNamedProfileOverrideCanSwapGenerationDriver(): void
    {
        $default = new RecordingClient('default');
        $editor = new RecordingClient('editor');

        $fillIn = new FillIn($default, 'Hello {=content profile="editorial"}');
        $fillIn->registerProfileOverride('editorial', [
            'driver' => $editor,
            'prompt' => 'You are the editorial profile.',
        ]);

        $fillIn->run();

        $this->assertSame('Hello editor', $fillIn->output());
        $this->assertCount(0, $default->prompts);
        $this->assertCount(1, $editor->prompts);
        $this->assertStringStartsWith("You are the editorial profile.\n\nHello ", $editor->prompts[0]);
    }
}
