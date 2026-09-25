<?php

/**
 * Plugin Name:     Nectar Blocks CSS Guard
 * Plugin URI:      https://novionline.nl
 * Description:     Keeps NectarBlocks blockIds stable and blocks live CSS meta writes that do not match published markup (e.g. pattern autosave).
 * Version:         1.0.0
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Text Domain:     nectar-blocks-css-guard
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

//bail if core plugin is not available
if (!class_exists('NoviOnline\Core')) {
    return;
}

if (!defined('NECTAR_BLOCKS_CSS_GUARD_PLUGIN_FILE')) {
    define('NECTAR_BLOCKS_CSS_GUARD_PLUGIN_FILE', __FILE__);
}

if (!defined('NECTAR_BLOCKS_CSS_GUARD_PLUGIN_PATH')) {
    define('NECTAR_BLOCKS_CSS_GUARD_PLUGIN_PATH', plugin_dir_path(NECTAR_BLOCKS_CSS_GUARD_PLUGIN_FILE));
}

if (!defined('NECTAR_BLOCKS_CSS_GUARD_PLUGIN_URL')) {
    define('NECTAR_BLOCKS_CSS_GUARD_PLUGIN_URL', plugins_url('', NECTAR_BLOCKS_CSS_GUARD_PLUGIN_FILE));
}

$autoload = NECTAR_BLOCKS_CSS_GUARD_PLUGIN_PATH . 'autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use NoviOnline\NectarBlocksCssGuard\NectarBlocksCssGuard;

add_action('plugins_loaded', function () {
    if (!class_exists(NectarBlocksCssGuard::class)) {
        return;
    }

    NectarBlocksCssGuard::getInstance();
}, 20);
