<?php

namespace BlueFission\Bot\NaturalLanguage;

class StemmerLemmatizer {
    protected $irregularWords = [
        // Example irregular words
        'children' => 'child',
        'men' => 'man',
        'mice' => 'mouse',
        'teeth' => 'tooth',
    ];

    protected $prefixes = [
        // Example prefixes
        'dis',
        'un',
        'in',
        'im',
        'non',
    ];

    protected $suffixes = [
        // Example suffixes
        'ing',
        'ed',
        'er',
        'est',
        's',
    ];

    public function __construct($config = []) {
        if (isset($config['irregularWords'])) {
            $this->irregularWords = $config['irregularWords'];
        }

        if (isset($config['prefixes'])) {
            $this->prefixes = $config['prefixes'];
        }

        if (isset($config['suffixes'])) {
            $this->suffixes = $config['suffixes'];
        }
    }

    public function lemmatize($word) {
        // Check for irregular words
        if (isset($this->irregularWords[$word])) {
            return $this->irregularWords[$word];
        }

        // Remove prefixes
        foreach ($this->prefixes as $prefix) {
            if (substr($word, 0, strlen($prefix)) === $prefix) {
                $word = substr($word, strlen($prefix));
                break;
            }
        }

        // Remove suffixes
        foreach ($this->suffixes as $suffix) {
            if (substr($word, -strlen($suffix)) === $suffix) {
                $word = substr($word, 0, -strlen($suffix));
                break;
            }
        }

        return $word;
    }
}