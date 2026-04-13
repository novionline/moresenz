<?php

namespace NoviOnline\Core;

/**
 * class PaginationComponent
 * @package NoviOnline\Core
 */
class PaginationComponent extends Singleton
{

    /**
     * Get pagination HTML
     * @param string $hash
     * @return string
     */
    public static function getPaginationHtml(string $hash = ''): string
    {
        return Partial::render('components/pagination', ['hash' => $hash], false, WCP_PARTIAL_PATH);
    }
}