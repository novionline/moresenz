<?php
/**
 * Plugin Name: Cookie Confirm CMP
 * Plugin URI: https://cookieconfirm.com/
 * Description: Plugin to enqueue a Cookie Confirm script and inject default Google Consent Mode settings.
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Version: 1.2
 * Author: Cookie Confirm
 * Text Domain: cookie-confirm-cmp
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

if (!defined('COOKIE_CONFIRM_FILE')) {
    define('COOKIE_CONFIRM_FILE', plugin_dir_path(__FILE__));
}

if (!defined('COOKIE_CONFIRM_URL')) {
    define('COOKIE_CONFIRM_URL', plugin_dir_url(__FILE__));
}

if (!defined('COOKIE_CONFIRM_BASENAME')) {
    define('COOKIE_CONFIRM_BASENAME', plugin_basename(__FILE__));
}

if (!defined('COOKIE_CONFIRM_NAME')) {
    define('COOKIE_CONFIRM_NAME', 'cookie_confirm');
}

if (!defined('COOKIE_CONFIRM_SLUG')) {
    define('COOKIE_CONFIRM_SLUG', 'cookie-confirm-cmp');
}

// Load Composer autoloader
$autoloadPath = COOKIE_CONFIRM_FILE . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('Cookie Confirm: Composer autoload not found.', 'cookie-confirm-cmp') . '</p></div>';
    });
    return;
}

if (!defined('COOKIE_CONFIRM_VERSION')) {
    define('COOKIE_CONFIRM_VERSION', '1.2');

    new \CookieConfirm\CookieConfirm();
}
