<?php

namespace NoviOnline\Core;

/**
 * class Link
 * @package NoviOnline\Core
 */
class Link {

    /**
     * Get home page URL
     * @return string
     */
    public static function getHomePageUrl(): string {
        return function_exists('pll_home_url') ? pll_home_url() : get_home_url();
    }

    /**
     * Parse link
     * Adds a trailing slash when needed
     * @param string $link
     * @return string
     */
    public static function parseLink(string $link = ''): string {
        if (!$link) return '';

        //trim link
        $link = trim($link);

        //make link absolute if it starts with single slash
        if (str_starts_with($link, '/') && !str_starts_with($link, '//')) $link = self::getHomePageUrl() . $link;

        //handle hash
        if (strpos($link, '#') > -1) return untrailingslashit($link);

        //handle tel link
        if (strpos($link, 'tel:') > -1) return untrailingslashit($link);

        //handle mailto link
        if (strpos($link, 'mailto:') > -1) return untrailingslashit($link);

        //handle query parameter
        if (strpos($link, '?') > -1) return untrailingslashit($link);

        //handle file extension
        if (str_contains(substr($link, strlen($link) - 5, 5), '.')) return untrailingslashit($link);

        //handle link to base domain (which should not have a trailing slash)
        $baseUrlArray = parse_url($link);
        if ($baseUrlArray) {
            $baseUrl = $baseUrlArray['scheme'] . '://' . $baseUrlArray['host'];
            if (untrailingslashit($link) === untrailingslashit($baseUrl)) return untrailingslashit($link);
        }

        //default scenario - add trailing slash
        return trailingslashit($link);
    }
}