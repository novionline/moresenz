<?php

namespace NoviOnline\ContentTranslator;

/**
 * Plugin Name:     Novi Content Translator
 * Plugin URI:      https://www.novionline.nl
 * Description:     Adds translation tooling for Polylang-managed content and block-based editor workflows.
 * Version:         1.0.0
 * Author:          Novi Online
 * Author URI:      https://www.novionline.nl
 * Text Domain:     novi-content-translator
 * Requires PHP:    7.4
 */

//bail if accessed directly
if (!defined('ABSPATH')) exit;

use NoviOnline\Core\Singleton;
use NoviOnline\ContentTranslator\ContentTranslatorComponent;
use NoviOnline\ContentTranslator\PolylangMachineTranslationDisablerComponent;
use NoviOnline\ContentTranslator\Cli\NctCliCommands;

//autoload classes
require_once(__DIR__ . '/autoload.php');

/**
 * Class NoviContentTranslator
 * @package NoviOnline\ContentTranslator
 */
if (!class_exists('\NoviOnline\ContentTranslator\NoviContentTranslator')) {
    class NoviContentTranslator extends Singleton
{
    /**
     * Define plugin version
     */
    const PLUGIN_VERSION = '1.0.0';

    /**
     * Define text domain for translations
     */
    const TEXT_DOMAIN = 'novi-content-translator';

    /**
     * NoviContentTranslator constructor
     */
    protected function __construct()
    {
        //check if Novi core plugin is active
        if (!class_exists('\NoviOnline\Core')) {
            add_action('admin_notices', [$this, 'showCoreRequiredNotice']);
            return;
        }

        //check if Polylang is active
        if (!function_exists('pll_languages_list')) {
            add_action('admin_notices', [$this, 'showPolylangRequiredNotice']);
            return;
        }

        //define relevant plugin paths (only if not already defined)
        if (!defined('NCT_PLUGIN_PATH')) {
            define('NCT_PLUGIN_PATH', __DIR__);
        }
        if (!defined('NCT_PLUGIN_URL')) {
            define('NCT_PLUGIN_URL', get_home_url() . '/wp-content/mu-plugins/novionline/novi-content-translator');
        }
        if (!defined('NCT_MANIFEST_PATH')) {
            define('NCT_MANIFEST_PATH', NCT_PLUGIN_PATH . '/dist/manifest.json');
        }

        //define DeepL API key (override in wp-config.php; do not hardcode secrets)
        if (!defined('NCT_DEEPL_API_KEY')) {
            define('NCT_DEEPL_API_KEY', '');
        }

        //init translations
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
        add_action('init', function () {
            load_muplugin_textdomain(self::TEXT_DOMAIN, 'novionline/novi-content-translator/languages');
        });

        //add translations to loco translate
        add_filter('loco_plugins_data', function (array $plugins) {
            $handle = 'novionline/novi-content-translator/novi-content-translator.php';
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
        PolylangMachineTranslationDisablerComponent::getInstance();
        ContentTranslatorComponent::getInstance();

        // WP-CLI commands (safe no-op outside CLI).
        if (defined('WP_CLI') && \WP_CLI) {
            NctCliCommands::register();
        }
    }

    /**
     * Show notice if Novi core plugin is not active
     * @return void
     */
    public function showCoreRequiredNotice(): void
    {
        ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('Novi Content Translator requires the Novi WordPress core plugin to be installed and activated.', self::TEXT_DOMAIN); ?></p>
        </div>
        <?php
    }

    /**
     * Show notice if Polylang is not active
     * @return void
     */
    public function showPolylangRequiredNotice(): void
    {
        ?>
        <div class="notice notice-error">
            <p><?php echo esc_html__('Novi Content Translator requires the Polylang Pro plugin to be installed and activated.', self::TEXT_DOMAIN); ?></p>
        </div>
        <?php
    }
}
}

//init plugin
if (!isset($GLOBALS['noviContentTranslatorInstance'])) {
    $GLOBALS['noviContentTranslatorInstance'] = NoviContentTranslator::getInstance();
}

