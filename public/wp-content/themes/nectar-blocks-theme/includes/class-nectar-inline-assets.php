<?php

/**
 * Inline Asset Helper
 *
 * Reads theme CSS/JS files and attaches them as inline
 * styles or scripts to an existing enqueued handle,
 * eliminating extra HTTP requests.
 *
 * @package Nectar Blocks Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NectarInlineAssets {
    /**
     * Resolved src directory ('src' or 'build').
     *
     * @var string
     */
    private static $src_dir;

    /**
     * Get the current src directory, resolving once.
     *
     * @return string
     */
    private static function src_dir() {
        if ( null === self::$src_dir ) {
            $dev = apply_filters( 'nectar_dev_mode', false );
            self::$src_dir = $dev ? 'src' : 'build';
        }
        return self::$src_dir;
    }

    /**
     * Inline a CSS file as wp_add_inline_style on $handle.
     *
     * @param string $handle  Enqueued style handle to attach to.
     * @param string $path    File path relative to the theme directory (e.g. 'css/src/foo.css').
     * @return bool  Whether the inline style was added.
     */
    public static function style( $handle, $path ) {
        $file = get_template_directory() . '/' . $path;
        $css = @file_get_contents( $file );
        if ( $css ) {
            wp_add_inline_style( $handle, $css );
            return true;
        }
        return false;
    }

    /**
     * Inline a CSS file, auto-resolving the src/build directory.
     *
     * @param string $handle    Enqueued style handle.
     * @param string $sub_path  Path after the src/build dir (e.g. 'third-party/fluentforms.css').
     * @param string $prefix    Directory prefix before src/build (default 'css').
     * @return bool
     */
    public static function css( $handle, $sub_path, $prefix = 'css' ) {
        $path = $prefix . '/' . self::src_dir() . '/' . $sub_path;
        return self::style( $handle, $path );
    }

    /**
     * Read a file's contents without attaching to any handle.
     * Useful for collecting multiple files into a single manual output.
     *
     * @param string $prefix    Directory prefix before src/build (e.g. 'css', 'js').
     * @param string $sub_path  Path after the src/build dir.
     * @return string  File contents or empty string on failure.
     */
    public static function read( $prefix, $sub_path ) {
        $file = get_template_directory() . '/' . $prefix . '/' . self::src_dir() . '/' . $sub_path;
        $contents = @file_get_contents( $file );
        return $contents ? $contents : '';
    }

    /**
     * Output a CSS file as a <link> tag directly.
     * Bypasses wp_enqueue_style to ensure it renders
     * exactly where called (e.g. in wp_footer).
     *
     * @param string $id        Unique id for the link tag.
     * @param string $sub_path  Path after the src/build dir.
     * @param string $prefix    Directory prefix before src/build (default 'css').
     */
    public static function link( $id, $sub_path, $prefix = 'css' ) {
        $url = get_template_directory_uri() . '/' . $prefix . '/' . self::src_dir() . '/' . $sub_path;
        $ver = nectar_get_theme_version();
        echo '<link rel="stylesheet" id="' . esc_attr( $id ) . '-css" href="' . esc_url( $url ) . '?ver=' . esc_attr( $ver ) . '" media="all" />' . "\n";
    }

    /**
     * Inline a JS file as wp_add_inline_script on $handle.
     *
     * @param string $handle    Enqueued script handle to attach to.
     * @param string $sub_path  Path after the src/build dir (e.g. 'nectar-floating-labels.js').
     * @param string $position  'before' or 'after' (default 'after').
     * @param string $prefix    Directory prefix before src/build (default 'js').
     * @return bool
     */
    public static function js( $handle, $sub_path, $position = 'after', $prefix = 'js' ) {
        $file = get_template_directory() . '/' . $prefix . '/' . self::src_dir() . '/' . $sub_path;
        $js = @file_get_contents( $file );
        if ( $js ) {
            wp_add_inline_script( $handle, $js, $position );
            return true;
        }
        return false;
    }
}
