<?php

namespace BlueFission\Automata\Language;

class ContractionNormalizer
{
    public static function normalize(string $input, array $map = []): string
    {
        $input = self::normalizeApostrophes($input);
        $map = array_merge(self::defaultMap(), $map);

        foreach ($map as $pattern => $replacement) {
            $input = preg_replace_callback(
                $pattern,
                function (array $matches) use ($replacement): string {
                    return self::matchCase($matches[0], $replacement);
                },
                $input
            );
        }

        $input = preg_replace_callback(
            "/\\b([A-Za-z]+)n't\\b/i",
            function (array $matches): string {
                return self::expandWithSuffix($matches[1], 'not');
            },
            $input
        );

        $input = preg_replace_callback(
            "/\\b([A-Za-z]+)'re\\b/i",
            function (array $matches): string {
                return self::expandWithSuffix($matches[1], 'are');
            },
            $input
        );

        $input = preg_replace_callback(
            "/\\b([A-Za-z]+)'ve\\b/i",
            function (array $matches): string {
                return self::expandWithSuffix($matches[1], 'have');
            },
            $input
        );

        $input = preg_replace_callback(
            "/\\b([A-Za-z]+)'ll\\b/i",
            function (array $matches): string {
                return self::expandWithSuffix($matches[1], 'will');
            },
            $input
        );

        $input = preg_replace_callback(
            "/\\b([A-Za-z]+)'d\\b/i",
            function (array $matches): string {
                return self::expandWithSuffix($matches[1], 'would');
            },
            $input
        );

        $input = preg_replace_callback(
            "/\\b([A-Za-z]+)'m\\b/i",
            function (array $matches): string {
                return self::expandWithSuffix($matches[1], 'am');
            },
            $input
        );

        return $input;
    }

    private static function defaultMap(): array
    {
        return [
            "/\\bain't\\b/i" => 'is not',
            "/\\bcan't\\b/i" => 'cannot',
            "/\\bwon't\\b/i" => 'will not',
            "/\\bshan't\\b/i" => 'shall not',
            "/\\blet's\\b/i" => 'let us',
            "/\\bwhat's\\b/i" => 'what is',
            "/\\bthat's\\b/i" => 'that is',
            "/\\bthere's\\b/i" => 'there is',
            "/\\bhere's\\b/i" => 'here is',
            "/\\bwho's\\b/i" => 'who is',
            "/\\bwhere's\\b/i" => 'where is',
            "/\\bwhen's\\b/i" => 'when is',
            "/\\bwhy's\\b/i" => 'why is',
            "/\\bhow's\\b/i" => 'how is',
            "/\\bit's\\b/i" => 'it is',
            "/\\bhe's\\b/i" => 'he is',
            "/\\bshe's\\b/i" => 'she is',
        ];
    }

    private static function matchCase(string $match, string $replacement): string
    {
        if ($match === strtoupper($match)) {
            return strtoupper($replacement);
        }

        if (ctype_upper($match[0])) {
            return ucfirst($replacement);
        }

        return $replacement;
    }

    private static function expandWithSuffix(string $word, string $suffix): string
    {
        $suffixOut = $suffix;
        if ($word === strtoupper($word) && strlen($word) > 1) {
            $suffixOut = strtoupper($suffix);
        }

        return $word . ' ' . $suffixOut;
    }

    private static function normalizeApostrophes(string $input): string
    {
        return str_replace(
            ["\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB4"],
            "'",
            $input
        );
    }
}
