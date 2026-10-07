<?php

namespace CookieConfirm\Matcher;

class EndsWithMatcher implements MatcherInterface
{
    public function matches(string $ruleSrc, string $scriptSrc): bool
    {
        return str_ends_with($scriptSrc, $ruleSrc);
    }
}