<?php

namespace CookieConfirm\Matcher;

class RegexMatcher implements MatcherInterface
{
    public function matches(string $ruleSrc, string $scriptSrc): bool
    {
        return (bool) preg_match("/$ruleSrc/", $scriptSrc);
    }
}