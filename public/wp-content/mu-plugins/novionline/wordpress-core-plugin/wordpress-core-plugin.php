<?php

namespace NoviOnline;

/**
 * Plugin Name:     WordPress core plugin
 * Plugin URI:      https://novionline.nl
 * Description:     Utility plugin for Novi Online WordPress plugins and themes 🔥
 * Version:         0.6
 * Author:          Novi Online
 * Author URI:      https://novionline.nl
 * Text Domain:     wordpress-core-plugin
 * Requires PHP:    8.4
 */

//bail if accessed directly
if (!defined('ABSPATH')) exit;

use NoviOnline\Core\AdminBarLightComponent;
use NoviOnline\Core\AdminColorComponent;
use NoviOnline\Core\AcfComponent;
use NoviOnline\Core\Enqueue;
use NoviOnline\Core\PreviewNotificationComponent;
use NoviOnline\Core\Singleton;

//autoload classes
require_once(__DIR__ . '/autoload.php');

/**
 * Class Core
 * @package NoviOnline
 */
class Core extends Singleton
{
    /**
     * Define plugin version
     */
    const PLUGIN_VERSION = 0.6;

    /**
     * Define text domain for translations
     */
    const TEXT_DOMAIN = 'wordpress-core-plugin';

    /**
     * Core constructor
     */
    protected function __construct()
    {
        //define relevant plugin paths
        define('WCP_PLUGIN_PATH', __DIR__);
        define('WCP_PLUGIN_URL', get_home_url() . '/wp-content/mu-plugins/novionline/wordpress-core-plugin');
        define('WCP_PARTIAL_PATH', WCP_PLUGIN_PATH . '/partials/');
        define('WCP_MANIFEST_PATH', WCP_PLUGIN_PATH . '/dist/manifest.json');

        //get manifest JSON URL for icon sprite
        $iconSpritePath = Enqueue::getWebpackAssetUrlByKey(WCP_MANIFEST_PATH, 'icons-svg');
        if ($iconSpritePath) define('WCP_ICON_PATH', $iconSpritePath . '#');

        //init classes
        self::initTranslations();

        //init components
        self::initComponents();
    }

    /**
     * Init plugin translations
     * @return void
     */
    public static function initTranslations(): void
    {
        //init po/mo translations
        add_action('plugins_loaded', function () {
            load_muplugin_textdomain(self::TEXT_DOMAIN, 'novionline/wordpress-core-plugin/languages');
        });

        //add translations to loco translate
        add_filter('loco_plugins_data', function (array $plugins) {
            $handle = 'novionline/wordpress-core-plugin/wordpress-core-plugin.php';
            $data = get_plugin_data(trailingslashit(WPMU_PLUGIN_DIR) . $handle);
            $data['basedir'] = WPMU_PLUGIN_DIR;
            $plugins[$handle] = $data;
            return $plugins;
        }, 10, 1);
    }

    /**
     * Init components
     * @return void
     */
    public static function initComponents(): void
    {
        PreviewNotificationComponent::getInstance();
        AcfComponent::getInstance();

        //apply Novi purple admin theme color overrides
        $adminColorEnabled = apply_filters('novi_admin_color_feature_enabled', true);
        if ($adminColorEnabled) AdminColorComponent::getInstance();

        //init admin bar light when theme is about to load
        add_action('after_setup_theme', function () {
            $adminBarLightEnabled = apply_filters('novi_admin_bar_light_feature_enabled', true);
            if ($adminBarLightEnabled) AdminBarLightComponent::getInstance();
        });
    }
}

//init plugin
global $corePluginInstance;
if (!$corePluginInstance) $corePluginInstance = Core::getInstance();