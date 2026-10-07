<?php

namespace CookieConfirm\Matcher;

class MatcherFactory
{
    public static function create(string|null $type): MatcherInterface
    {
        return match ($type) {
            'regex'      => new RegexMatcher(),
            'startsWith' => new StartsWithMatcher(),
            'endsWith'   => new EndsWithMatcher(),
            'contains'   => new ContainsMatcher(),
            default      => new IsMatcher(),
        };
    }
}