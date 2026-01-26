<?php

namespace BlueFission\Automata\Anomaly;

use BlueFission\Automata\Context;
use BlueFission\DataTypes;
use BlueFission\Obj;
use BlueFission\DevElation as Dev;

class Activity extends Obj
{
    protected $_data = [
        'id' => '',
        'timestamp' => 0,
        'features' => [],
        'tags' => [],
        'context' => null,
        'meta' => [],
    ];

    protected $_types = [
        'id' => DataTypes::STRING,
        'timestamp' => DataTypes::INTEGER,
        'features' => DataTypes::ARRAY,
        'tags' => DataTypes::ARRAY,
        'context' => DataTypes::GENERIC,
        'meta' => DataTypes::ARRAY,
    ];

    public function __construct(array $data = [])
    {
        parent::__construct();

        $this->assign([
            'id' => (string)($data['id'] ?? ''),
            'timestamp' => (int)($data['timestamp'] ?? time()),
            'features' => $data['features'] ?? [],
            'tags' => $data['tags'] ?? [],
            'context' => $data['context'] ?? new Context(),
            'meta' => $data['meta'] ?? [],
        ]);

        if (!($this->context instanceof Context)) {
            $context = new Context();
            $context->set('activity', $this->id());
            $this->context = $context;
        }

        Dev::do('anomaly.activity.created', ['activity' => $this]);
    }

    public function id(): string
    {
        return (string)$this->field('id');
    }

    public function timestamp(): int
    {
        return (int)$this->field('timestamp');
    }

    public function features(): array
    {
        $features = $this->field('features');
        return is_array($features) ? $features : [];
    }

    public function tags(): array
    {
        $tags = $this->field('tags');
        return is_array($tags) ? $tags : [];
    }

    public function context(): Context
    {
        $context = $this->field('context');
        return $context instanceof Context ? $context : new Context();
    }

    public function fingerprint(): Fingerprint
    {
        return new Fingerprint([
            'features' => $this->features(),
            'tags' => $this->tags(),
            'context' => $this->context(),
            'meta' => [
                'activity_id' => $this->id(),
                'timestamp' => $this->timestamp(),
            ],
        ]);
    }
}
