<?php

namespace BlueFission\Tests\Automata\LLM;

use BlueFission\Automata\LLM\Clients\IClient;
use BlueFission\Automata\LLM\FillIn;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Behavioral\Behaviors\Meta;
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

class UsageReply extends Reply
{
    public function __construct(private array $usage = [])
    {
        parent::__construct();
    }

    public function usage(): array
    {
        return $this->usage;
    }
}

class UsageRecordingClient extends RecordingClient
{
    public function __construct(string $response, private array $usage = [])
    {
        parent::__construct($response);
    }

    public function generate($input, $config = [], ?callable $callback = null): Reply
    {
        $this->prompts[] = (string)$input;

        $reply = new UsageReply($this->usage);
        $reply->addMessage('observed', true);

        if ($callback) {
            $callback('observed');
        }

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

    public function testFillInExposesGenerationMetadataAndUsage(): void
    {
        $client = new UsageRecordingClient('observed', [
            'prompt_tokens' => 11,
            'completion_tokens' => 4,
            'total_tokens' => 15,
        ]);

        $fillIn = new FillIn(
            $client,
            'Intro preface for the report body {=content profile="editorial" label="Chapter 3 / Section 2" phase="draft" chapter="3" section="2" thread="report-42" session="run-7" context_strategy="windowed-prefix" max_context_tokens="2"}'
        );
        $fillIn->registerProfileOverride('editorial', [
            'prompt' => 'You are the editorial profile.',
        ]);

        $sent = [];
        $received = [];
        $fillIn->when(Event::SENT, function ($behavior, $meta) use (&$sent) {
            $sent[] = $meta;
        });
        $fillIn->when(Event::RECEIVED, function ($behavior, $meta) use (&$received) {
            $received[] = $meta;
        });

        $fillIn->run();

        $this->assertNotEmpty($sent);
        $this->assertNotEmpty($received);
        $this->assertInstanceOf(Meta::class, $sent[0]);

        $sentData = $sent[0]->data;
        $this->assertSame('Chapter 3 / Section 2', $sentData['label']);
        $this->assertSame('draft', $sentData['phase']);
        $this->assertSame('3', $sentData['chapter']);
        $this->assertSame('2', $sentData['section']);
        $this->assertSame('editorial', $sentData['profile']);
        $this->assertSame(1, $sentData['attempt']);
        $this->assertSame('report-42', $sentData['thread']);
        $this->assertSame('run-7', $sentData['session']);
        $this->assertSame('windowed-prefix', $sentData['context']['strategy']);
        $this->assertTrue($sentData['context']['truncated']);

        $finalEvents = array_values(array_filter($received, function ($meta) {
            return $meta instanceof Meta && (($meta->data['final'] ?? false) === true);
        }));

        $this->assertNotEmpty($finalEvents);
        $finalData = $finalEvents[0]->data;

        $this->assertSame('observed', $finalData['value']);
        $this->assertTrue($finalData['accepted']);
        $this->assertSame(11, $finalData['usage']['prompt_tokens']);
        $this->assertSame(4, $finalData['usage']['completion_tokens']);
        $this->assertSame(15, $finalData['usage']['total_tokens']);
        $this->assertGreaterThan(0, $finalData['usage']['estimated_total_tokens']);

        $ledger = $fillIn->usageLedger();
        $this->assertCount(1, $ledger);
        $entries = array_values($ledger)[0];
        $this->assertCount(1, $entries);
        $this->assertSame('Chapter 3 / Section 2', $entries[0]['label']);
        $this->assertTrue($entries[0]['accepted']);
        $this->assertSame(15, $entries[0]['usage']['total_tokens']);

        $totals = $fillIn->usageTotals();
        $this->assertSame(11, $totals['prompt_tokens']);
        $this->assertSame(4, $totals['completion_tokens']);
        $this->assertSame(15, $totals['total_tokens']);
        $this->assertGreaterThan(0, $totals['estimated_total_tokens']);
    }

    public function testContextStrategyNoneSkipsPrefixContext(): void
    {
        $client = new RecordingClient('clean');
        $fillIn = new FillIn($client, 'Preface {=content context_strategy="none"}');

        $fillIn->run();

        $this->assertSame('Preface clean', $fillIn->output());
        $this->assertCount(1, $client->prompts);
        $this->assertSame('', $client->prompts[0]);
    }

    public function testGenerationAttributesSupportScopedInterpolationForThreadIds(): void
    {
        $client = new RecordingClient('threaded');
        $fillIn = new FillIn(
            $client,
            '{=content thread="book:[[book.slug|slug]]:chapter:[[chapter|pad:2]]:section:[[section.slug|slug]]" session="session:[[book.slug|slug]]:[[chapter|pad:2]]"}'
        );
        $fillIn->setVariables([
            'book' => ['slug' => 'Field Notes on Response'],
            'chapter' => 3,
            'section' => ['slug' => 'Initial Damage Survey'],
        ]);

        $sent = [];
        $fillIn->when(Event::SENT, function ($behavior, $meta) use (&$sent) {
            $sent[] = $meta;
        });

        $fillIn->run();

        $this->assertNotEmpty($sent);
        $data = $sent[0]->data;

        $this->assertSame(
            'book:field-notes-on-response:chapter:03:section:initial-damage-survey',
            $data['thread']
        );
        $this->assertSame(
            'session:field-notes-on-response:03',
            $data['session']
        );
    }
}
