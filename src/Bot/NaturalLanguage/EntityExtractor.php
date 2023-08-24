<?php
namespace BlueFission\Bot\NaturalLanguage;

class EntityExtractor
{
    public function date($input)
    {
        return $this->extract('/(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}|\d{2}[-.\/]\d{2}[-.\/]\d{2,4})/', $input);
    }

    public function time($input)
    {
        return $this->extract('/(0?[1-9]|1[0-2]):[0-5][0-9](\s?[ap]m)?/', $input);
    }

    public function web($input)
    {
        return $this->extract('/https?:\/\/[^\s]+/', $input);
    }

    public function phone($input)
    {
        // return $this->extract('/\d{3}[-\.\s]??\d{3}[-\.\s]??\d{4}/', $input);
        return $this->extract('/^\+?\d{1,4}[\s-]?\d{1,4}(?:[\s-]?\d{1,4}){1,4}$/', $input);
    }

    public function email($input)
    {
        return $this->extract('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $input);
    }

    public function address($input)
    {
        return $this->extract('/\d+\s+([a-zA-Z]+\s){1,2}/', $input);
    }

    public function name($input)
    {
        return $this->extract('/[A-Z][a-z]+\s[A-Z][a-z]+/', $input);
    }

    public function object($input)
    {
        return $this->extract('/\b[a-z]+\b/', $input);
    }

    public function number($input)
    {
        return $this->extract('/\b(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten)\b/', $input);
    }

    public function adverb($input)
    {
        return $this->extract('/\b\w+ly\b/', $input);
    }

    public function hex($input)
    {
        return $this->extract('/\b0x[a-fA-F0-9]+\b/', $input);
    }

    public function file($input)
    {
        return $this->extract('/\b(?:[a-zA-Z]:\\|\/|\\\\)[^<>:"|?*\n]*[^<>:"\\/|?*\n\s]\b/', $input);
    }

    public function literals($input)
    {
        return $this->extract('/(?<![\\\\])["\']((?:.(?!(?<![\\\\])["\']))*.)["\']/', $input);
    }

    public function mentions($input)
    {
        return $this->extract('/@[a-zA-Z]\w*/', $input);
    }

    public function tags($input)
    {
        return $this->extract('/#[a-zA-Z]\w*/', $input);
    }

    public function values($input)
    {
        return $this->extract('/\$[a-zA-Z]\w*/', $input);
    }

    public function operation($input)
    {
        $operators = '\+|-|\*|\/|==?|&&|\|\||<<|>>|>|<|<>|!=';
        return $this->extract('/(\w+)\s*(' . $operators . ')\s*(\w+)/', $input);
    }

    private function extract($pattern, $input)
    {
        if (is_string($input)) {
            $input = [$input];
        }
        $matches = [];
        foreach ($input as $str) {
            preg_match_all($pattern, $str, $results);
            $matches = array_merge($matches, $results[0]);
        }
        return $matches;
    }
}

