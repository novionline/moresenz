<?php

namespace NoviOnline;

//bail if core plugin is not available
if (!class_exists('NoviOnline\Core')) {
    die('Please install the Novi Online core plugin to use this child theme..');
}

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Singleton;
use NoviOnline\AdminScripts;

//autoload PHP classes
require_once('autoload.php');

/**
 * Init child theme CSS and fonts
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('nectarblocks-child-style', get_stylesheet_directory_uri() . '/style.css', '', '1.0.0');
}, 100);

/**
 * Class Theme
 * @package NoviOnline
 */
class Theme extends Singleton {

    //define theme text domain
    const TEXT_DOMAIN = 'novionline';

    /**
     * Theme constructor.
     */
    protected function __construct() {

        //define manifest path
        define('MANIFEST_PATH', get_stylesheet_directory() . '/dist/manifest.json');

        //define svg icon sprite path
        $iconPath = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, (self::TEXT_DOMAIN . '-icons-svg'));
        if (!$iconPath) $iconPath = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, (self::TEXT_DOMAIN . '-icons.svg'));
        define('ICON_PATH', $iconPath ? ($iconPath . '#') : '#');

        //init (theme) translations + EVO classes
        add_action('after_setup_theme', function () {
            load_theme_textdomain(self::TEXT_DOMAIN, get_stylesheet_directory() . '/languages');
            self::initSettings();
            self::initBlocks();
            self::initImageSizes();
            self::initMenuLocations();
            self::initScripts();
            self::initComponents();
            self::initRestEndpoints();
            self::initPostTypes();
        });
    }

    /**
     * Init image sizes
     */
    public static function initImageSizes(): void {
        //silence is golden
    }

    /**
     * Init menu locations
     */
    public static function initMenuLocations(): void {
        //silence is golden
    }

    /**
     * Init post types
     */
    public static function initPostTypes(): void {
        PostPostType::getInstance();
    }

    /**
     * Init scripts
     */
    public static function initScripts(): void {
        AdminScripts::getInstance();
        FrontendScripts::getInstance();
    }

    /**
     * Init components
     */
    public static function initComponents(): void {
        BlockCustomizationComponent::getInstance();
        NectarBlocksComponent::getInstance();
        GlobalSectionComponent::getInstance();
        GlobalSectionConditionsComponent::getInstance();
        HeaderComponent::getInstance();
        SearchComponent::getInstance();
        OwnerRoleComponent::getInstance();

        //handle Polylang customization
        if (class_exists('\Polylang')) {
            PolylangComponent::getInstance();
        }

        //handle GravityForms customization
        if (class_exists('\GFAPI')) {
            GravityFormsComponent::getInstance();
            GravityFormsValidationComponent::getInstance();
        }        
    }

    /**
     * Init REST endpoints
     */
    public static function initRestEndpoints(): void {
        //silence is golden
    }

    /**
     * Init blocks
     */
    public static function initBlocks(): void {
        NoviMenuBlock::getInstance();
    }

    /**
     * Init settings pages
     */
    public static function initSettings(): void {
        //silence is golden
    }
}

//init theme
global $themeInstance;
if (!$themeInstance) $themeInstance = Theme::getInstance();