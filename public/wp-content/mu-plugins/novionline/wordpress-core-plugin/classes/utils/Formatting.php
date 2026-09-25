<?php

namespace NoviOnline\Core;

/**
 * Class Navigation
 * @package NoviOnline\Core
 */
class Formatting
{

    /**
     * Slugify a string
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        return $text;
    }

    /**
     * Format string to html
     * This function is an equivalent of the_content
     * @param  string $str
     * @return string
     */
    public static function toHtml($str)
    {
        // replace common plain text characters
        $str = wptexturize($str);

        // convert smilies
        $str = convert_smilies($str);

        // Converts lone & characters into `&#038;` (a.k.a. `&amp;`)
        $str = convert_chars($str);

        // Replaces double line-breaks with paragraph elements.
        $str = wpautop($str);

        // Don't auto-p wrap shortcodes that stand alone
        $str = shortcode_unautop($str);

        // convert shortcodes
        $str = do_shortcode($str);

        // convert blocks
        $str = do_blocks($str);

        // prepend attachment
        $str = prepend_attachment($str);

        // balance tags
        $str = force_balance_tags($str);

        // convert ]]> to html entity
        $str = str_replace(']]>', ']]&gt;', $str);

        // remove empty paragraphs
        $str = preg_replace('/\<p\>[\s]*\<\/p\>/', '', $str);

        return $str;
    }

    /**
     * Format string to html without paragraphs
     * This function is an equivalent of the_content
     * @param  string $str
     * @return string
     */
    public static function toHtmlWithoutP($str) {
        // replace common plain text characters
        $str = wptexturize($str);

        // convert smilies
        $str = convert_smilies($str);

        // Converts lone & characters into `&#038;` (a.k.a. `&amp;`)
        $str = convert_chars($str);

        // Don't auto-p wrap shortcodes that stand alone
        $str = shortcode_unautop($str);

        // convert shortcodes
        $str = do_shortcode($str);

        // convert blocks
        $str = do_blocks($str);

        // prepend attachment
        $str = prepend_attachment($str);

        // balance tags
        $str = force_balance_tags($str);

        // convert ]]> to html entity
        $str = str_replace(']]>', ']]&gt;', $str);

        // remove empty paragraphes
        $str = preg_replace('/\<p\>[\s]*\<\/p\>/', '', $str);

        return $str;
    }

    /**
     * Get the site's combined date and time format from WordPress settings
     * @return string
     */
    public static function getDateTimeFormat(): string
    {
        return trim(get_option('date_format') . ' ' . get_option('time_format'));
    }

    /**
     * Parse a local datetime string in the WordPress timezone
     * @param string $value
     * @return int|null
     */
    public static function parseLocalDateTime(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        if ($datetime === false) {
            $datetime = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $timezone);
        }
        if ($datetime === false) {
            return null;
        }

        return $datetime->getTimestamp();
    }

    /**
     * Format a timestamp or datetime string using WordPress date/time settings and timezone
     * @param int|string $value
     * @param string|null $format
     * @return string
     */
    public static function i18nDate(int|string $value, ?string $format = null): string
    {
        if ($format === null) {
            $format = self::getDateTimeFormat();
        }

        if (is_int($value)) {
            $timestamp = $value;
        } elseif (is_numeric($value)) {
            $timestamp = (int)$value;
        } else {
            $timestamp = self::parseLocalDateTime((string)$value);
        }

        if ($timestamp === null) {
            return is_string($value) ? $value : '';
        }

        return wp_date($format, $timestamp);
    }

    /**
     * Format a timestamp as date and time with a connector word between both parts
     * @param int|string $value
     * @param string $timeConnector
     * @return string
     */
    public static function i18nDateTime(int|string $value, string $timeConnector = 'at'): string
    {
        $dateFormat = get_option('date_format');
        $timeFormat = get_option('time_format');

        if (is_int($value)) {
            $timestamp = $value;
        } elseif (is_numeric($value)) {
            $timestamp = (int)$value;
        } else {
            $timestamp = self::parseLocalDateTime((string)$value);
        }

        if ($timestamp === null) {
            return is_string($value) ? $value : '';
        }

        if ($timeFormat === '' || !self::timestampHasTime($timestamp)) {
            return wp_date($dateFormat, $timestamp);
        }

        return wp_date($dateFormat, $timestamp)
            . ' ' . $timeConnector . ' '
            . wp_date($timeFormat, $timestamp);
    }

    /**
     * Check whether a timestamp has a meaningful time component
     * @param int $timestamp
     * @return bool
     */
    private static function timestampHasTime(int $timestamp): bool
    {
        $datetime = (new \DateTimeImmutable('@' . $timestamp))->setTimezone(wp_timezone());

        return $datetime->format('His') !== '000000';
    }
}
