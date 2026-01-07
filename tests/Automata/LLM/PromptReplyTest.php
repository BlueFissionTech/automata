<?php

namespace BlueFission\Tests\Automata\LLM;

use PHPUnit\Framework\TestCase;
use BlueFission\Automata\LLM\Reply;
use BlueFission\Automata\LLM\Prompts\Prompt;

class PromptReplyTest extends TestCase
{
    public function testPromptReplacesTemplateVariables(): void
    {
        $prompt = new Prompt('Flooded bridge near Hospital A');

        $text = $prompt->prompt();

        $this->assertStringContainsString('Flooded bridge near Hospital A', $text);
        $this->assertStringContainsString('Response:', $text);
    }

    public function testReplyStoresMessagesAndSuccessFlag(): void
    {
        $reply = new Reply();

        $this->assertFalse($reply->success());

        $reply->addMessage('OK', true);
        $reply->addMessage('Second message', true);

        $messages = $reply->messages()->val();

        $this->assertTrue($reply->success());
        $this->assertSame(['OK', 'Second message'], $messages);
    }
}
