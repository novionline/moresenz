<?php

namespace CookieConfirm\Enums;

trait ValuesTrait
{
    public static function values(): array
    {
        $reflection = new \ReflectionClass(self::class);

        return $reflection->getConstants();
    }
}