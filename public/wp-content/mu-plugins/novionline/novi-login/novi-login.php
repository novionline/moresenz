<?php

/**
 * Plugin Name:     Novi Login
 * Plugin URI:      https://novionline.nl
 * Description:     Custom two-column Novi login screen with static HTML/CSS and client logo integration.
 * Version:         1.0.0
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Text Domain:     novi-login
 * Requires PHP:    8.1
 */

//bail if accessed directly
if (!defined('ABSPATH')) exit;

//bail if core plugin is not available
if (!class_exists('NoviOnline\Core')) {
    return;
}

//define plugin constants
if (!defined('NOVI_LOGIN_PLUGIN_FILE')) {
    define('NOVI_LOGIN_PLUGIN_FILE', __FILE__);
}

if (!defined('NOVI_LOGIN_PLUGIN_PATH')) {
    define('NOVI_LOGIN_PLUGIN_PATH', plugin_dir_path(NOVI_LOGIN_PLUGIN_FILE));
}

if (!defined('NOVI_LOGIN_PLUGIN_URL')) {
    define('NOVI_LOGIN_PLUGIN_URL', plugins_url('', NOVI_LOGIN_PLUGIN_FILE));
}

if (!defined('NOVI_LOGIN_PARTIAL_PATH')) {
    define('NOVI_LOGIN_PARTIAL_PATH', NOVI_LOGIN_PLUGIN_PATH . 'partials/');
}

//autoload classes from this plugin if needed
$autoload = NOVI_LOGIN_PLUGIN_PATH . 'autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use NoviOnline\Login\NoviLogin;

//init plugin
add_action('init', function () {
    if (!function_exists('wp_get_current_user')) {
        return;
    }

    global $noviLoginInstance;
    if (!$noviLoginInstance) {
        $noviLoginInstance = NoviLogin::getInstance();
    }
}, 0);

