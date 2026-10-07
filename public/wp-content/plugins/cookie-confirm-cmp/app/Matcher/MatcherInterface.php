<?php

namespace CookieConfirm\Matcher;

interface MatcherInterface
{
    public function matches(string $ruleSrc, string $scriptSrc): bool;
}