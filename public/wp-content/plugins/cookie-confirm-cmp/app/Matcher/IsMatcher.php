<?php

namespace CookieConfirm\Matcher;

class IsMatcher implements MatcherInterface
{
    public function matches(string $ruleSrc, string $scriptSrc): bool
    {
        return $ruleSrc === $scriptSrc;
    }
}