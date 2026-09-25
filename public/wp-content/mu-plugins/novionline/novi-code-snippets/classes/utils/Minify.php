<?php

namespace NoviOnline\CodeSnippets;

use MatthiasMullie\Minify\CSS as MinifyCSS;
use MatthiasMullie\Minify\JS as MinifyJS;

/**
 * Class Minify
 * Wrapper around matthiasmullie/minify for CSS and JS minification.
 * Requires Composer: composer require matthiasmullie/minify
 * @package NoviOnline\CodeSnippets
 */
class Minify
{
    /**
     * Minify CSS using matthiasmullie/minify (throws on invalid CSS)
     * @param string $css
     * @return string
     * @throws \Throwable
     */
    public static function css(string $css): string
    {
        $minifier = new MinifyCSS();
        $minifier->add($css);
        return $minifier->minify();
    }

    /**
     * Minify JS using matthiasmullie/minify (throws on invalid JS)
     * @param string $js
     * @return string
     * @throws \Throwable
     */
    public static function js(string $js): string
    {
        $minifier = new MinifyJS();
        $minifier->add($js);
        return $minifier->minify();
    }
}
