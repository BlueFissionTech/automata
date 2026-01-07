<?php
namespace BlueFission\Automata\Intent;

// Matcher.php
use BlueFission\Str;
use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\Skill\ISkill;
use BlueFission\Services\Service;

class Matcher
{
    protected $_intentAnalyzer;
    
    // Use static properties to store skills and intents globally
    protected static $skills = [];
    protected static $intents = [];
    protected static $intentSkillMap = [];


    public function __construct(IAnalyzer $intentAnalyzer)
    {
        $this->_intentAnalyzer = $intentAnalyzer;
    }

    public function registerSkill(ISkill $skill): self
    {
        self::$skills[$skill->name()] = $skill;
        return $this;
    }

    public function registerIntent($intent): self
    {
        // self::$intents[$intent->getLabel()] = $skillName;
        self::$intents[$intent->getLabel()] = $intent;
        return $this;
    }

    public function associate($intent, $skill): self
    {
        $intentLabel = $intent->getLabel();
        $skillName = $skill->name();

        if (!isset(self::$intentSkillMap[$intentLabel])) {
            self::$intentSkillMap[$intentLabel] = [];
        }

        self::$intentSkillMap[$intentLabel][] = $skillName;

        return $this;
    }

    public function map()
    {
        return self::$intentSkillMap;
    }

    public function getIntent(string $intentLabel): ?Intent
    {
        return self::$intents[$intentLabel] ?? null;
    }

    public function getSkill(string $skillName): ?ISkill
    {
        return self::$skills[$skillName] ?? null;
    }

    public function getIntents(): array
    {
        return self::$intents;
    }

    public function getSkills(): array
    {
        return self::$skills;
    }

    public function match($input, Context $context): ?Arr
    {
        $phrases = [];
        foreach ( self::$intents as $intent ) {
            // die(var_dump(self::$intents));
            foreach ($intent->getCriteria()['keywords'] as $keyword) {
                $phrases[$intent->getLabel()][] = ['weight'=>$keyword['priority'], 'text'=>$keyword['word']];
            }
        }

        $intentScores = $this->_intentAnalyzer->analyze($input, $context, $phrases);

        return $intentScores;
    }

    public function process($intent, Context $context): ?string
    {
        if ( Str::is($intent) ) {
            // Treat string as an intent label.
            $intent = $this->getIntent($intent);
        }
        $skillNames = isset(self::$intentSkillMap[$intent->getLabel()]) ? self::$intentSkillMap[$intent->getLabel()] : [];

        if (!empty($skillNames)) {
            $skill = $this->getSkill($skillNames[0]);
            $skill->execute($context);
            return $skill->response();
        }

        return null;
    }
}
