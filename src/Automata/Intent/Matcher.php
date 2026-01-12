<?php
namespace BlueFission\Automata\Intent;

// Matcher.php
use BlueFission\Str;
use BlueFission\Arr;
use BlueFission\Automata\Context;
use BlueFission\Automata\Analysis\IAnalyzer;
use BlueFission\Automata\Intent\Skill\ISkill;
use BlueFission\Services\Service;
use BlueFission\DevElation as Dev;

class Matcher
{
    protected $_intentAnalyzer;
    
    // Use static properties to store skills and intents globally
    protected static $skills = [];
    protected static $intents = [];
    protected static $intentSkillMap = [];


    public function __construct(IAnalyzer $intentAnalyzer)
    {
        $this->_intentAnalyzer = Dev::apply('intent.matcher.construct.analyzer', $intentAnalyzer);
        Dev::do('intent.matcher.construct', ['matcher' => $this]);
    }

    public function registerSkill(ISkill $skill): self
    {
        $skill = Dev::apply('intent.matcher.register_skill', $skill);
        self::$skills[$skill->name()] = $skill;
        Dev::do('intent.matcher.registered_skill', ['skill' => $skill]);
        return $this;
    }

    public function registerIntent($intent): self
    {
        $intent = Dev::apply('intent.matcher.register_intent', $intent);
        self::$intents[$intent->getLabel()] = $intent;
        Dev::do('intent.matcher.registered_intent', ['intent' => $intent]);
        return $this;
    }

    public function associate($intent, $skill): self
    {
        $intent = Dev::apply('intent.matcher.associate.intent', $intent);
        $skill = Dev::apply('intent.matcher.associate.skill', $skill);

        $intentLabel = $intent->getLabel();
        $skillName = $skill->name();

        if (!isset(self::$intentSkillMap[$intentLabel])) {
            self::$intentSkillMap[$intentLabel] = [];
        }

        self::$intentSkillMap[$intentLabel][] = $skillName;
        Dev::do('intent.matcher.associate', ['associate' => $intentLabel, 'skill' => $skillName]);

        return $this;
    }

    public function map()
    {
        return Dev::apply('intent.matcher.map', self::$intentSkillMap);
    }

    public function getIntent(string $intentLabel): ?Intent
    {
        $intentLabel = Dev::apply('intent.matcher.get_intent_label', $intentLabel);
        $intent = self::$intents[$intentLabel] ?? null;
        return Dev::apply('intent.matcher.get_intent', $intent);
    }

    public function getSkill(string $skillName): ?ISkill
    {
        $skillName = Dev::apply('intent.matcher.get_skill_name', $skillName);
        $skill = self::$skills[$skillName] ?? null;
        return Dev::apply('intent.matcher.get_skill', $skill);
    }

    public function getIntents(): array
    {
        return Dev::apply('intent.matcher.get_intents', self::$intents);
    }

    public function getSkills(): array
    {
        return Dev::apply('intent.matcher.get_skills', self::$skills);
    }

    public function match($input, Context $context): ?Arr
    {
        $input = Dev::apply('intent.matcher.match_input', $input);
        $context = Dev::apply('intent.matcher.match_context', $context);

        $phrases = [];
        foreach ( self::$intents as $intent ) {
            $criteria = $intent->getCriteria();
            $keywords = $criteria['keywords'] ?? [];
            foreach ($keywords as $keyword) {
                $phrases[$intent->getLabel()][] = ['weight'=>$keyword['priority'], 'text'=>$keyword['word']];
            }
        }

        Dev::do('intent.matcher.match_prepare', ['match_input' => $input, 'phrases' => $phrases]);

        $intentScores = $this->_intentAnalyzer->analyze($input, $context, $phrases);
        $intentScores = Dev::apply('intent.matcher.match_results', $intentScores);
        Dev::do('intent.matcher.match_result_event', ['match_results' => $intentScores]);

        return $intentScores;
    }

    public function process($intent, Context $context): ?string
    {
        $intent = Dev::apply('intent.matcher.process_intent', $intent);
        $context = Dev::apply('intent.matcher.process_context', $context);

        if ( Str::is($intent) ) {
            // Treat string as an intent label.
            $intent = $this->getIntent($intent);
        }
        $skillNames = isset(self::$intentSkillMap[$intent->getLabel()]) ? self::$intentSkillMap[$intent->getLabel()] : [];

        if (!empty($skillNames)) {
            $skill = $this->getSkill($skillNames[0]);
            $skill->execute($context);
            $response = $skill->response();
            Dev::do('intent.matcher.processed', ['process' => $intent->getLabel(), 'skill' => $skillNames[0], 'response' => $response]);
            return Dev::apply('intent.matcher.process_response', $response);
        }

        return Dev::apply('intent.matcher.process_none', null);
    }
}
