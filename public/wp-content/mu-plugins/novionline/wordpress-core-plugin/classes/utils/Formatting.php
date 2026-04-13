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
}
