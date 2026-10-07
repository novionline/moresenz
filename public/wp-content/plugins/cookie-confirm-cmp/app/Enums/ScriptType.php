<?php

namespace CookieConfirm\Enums;

class ScriptType
{
    use ValuesTrait;

    const IS = 'is';
    const CONTAINS = 'contains';
    const REGEX = 'regex';
    const STARTS_WITH = 'startsWith';
    const ENDS_WITH = 'endsWith';
}