<?php

namespace CookieConfirm\Matcher;

class StartsWithMatcher implements MatcherInterface
{
    public function matches(string $ruleSrc, string $scriptSrc): bool
    {
        return str_starts_with($scriptSrc, $ruleSrc);
    }
}