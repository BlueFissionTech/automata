<?php

namespace BlueFission\Automata\Feedback;

use BlueFission\Obj;
use BlueFission\Automata\Context;
use BlueFission\DevElation as Dev;

abstract class FeedbackItem extends Obj
{
    public function __construct(array $data = [])
    {
        parent::__construct();

        $data['tags'] = $data['tags'] ?? [];
        $data['context'] = $data['context'] ?? new Context();
        $data['timestamp'] = $data['timestamp'] ?? microtime(true);

        if (!($data['context'] instanceof Context)) {
            $context = new Context();
            if (is_array($data['context'])) {
                foreach ($data['context'] as $key => $value) {
                    $context->set($key, $value);
                }
            }
            $data['context'] = $context;
        }

        $this->assign($data);
        Dev::do('feedback.item.created', ['item' => $this]);
    }

    public function tags(): array
    {
        $tags = $this->field('tags');
        return is_array($tags) ? $tags : [];
    }

    public function context(): Context
    {
        return $this->field('context');
    }

    public function timestamp(): float
    {
        $timestamp = $this->field('timestamp');
        return is_numeric($timestamp) ? (float)$timestamp : 0.0;
    }
}
