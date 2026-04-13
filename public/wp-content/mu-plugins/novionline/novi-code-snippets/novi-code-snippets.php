<?php

/**
 * Plugin Name:     Novi Code Snippets
 * Plugin URI:      https://novionline.nl
 * Description:     Private CPT for CSS/JS code snippets; output in head (CSS) and footer (JS). Novi admins only.
 * Version:         1.0.0
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Text Domain:     novi-code-snippets
 * Requires PHP:    7.4
 */

//bail if accessed directly
if (!defined('ABSPATH')) exit;

//load Composer dependencies (matthiasmullie/minify for validation and minification)
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

//require core plugin and ACF so we can use Singleton and register ACF fields
if (!class_exists('NoviOnline\Core') || !function_exists('acf_add_local_field_group')) {
    return;
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/classes/NoviCodeSnippets.php';

add_action('init', function () {
    if (!function_exists('wp_get_current_user')) {
        return;
    }
    global $noviCodeSnippetsInstance;
    if (!$noviCodeSnippetsInstance) {
        $noviCodeSnippetsInstance = \NoviOnline\CodeSnippets\NoviCodeSnippets::getInstance();
    }
}, 0);
