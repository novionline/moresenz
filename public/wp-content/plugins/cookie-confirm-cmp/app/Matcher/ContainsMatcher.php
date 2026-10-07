<?php

namespace CookieConfirm\Matcher;

class ContainsMatcher implements MatcherInterface
{
    public function matches(string $ruleSrc, string $scriptSrc): bool
    {
        return str_contains($scriptSrc, $ruleSrc);
    }
}