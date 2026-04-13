<?php

/**
 * Nectar functions and definitions.
 *
 * @package Nectar Blocks
 * @since 1.0
 */

/**
 * Define Constants.
*/
require_once( 'nectar-vars.php' );
define( 'NB_THEME_VERSION', '2.5.4' );
define( 'NECTAR_THEME_DIRECTORY', get_template_directory() );
define( 'NECTAR_FRAMEWORK_DIRECTORY', get_template_directory_uri() . '/nectar/' );
define( 'NECTAR_THEME_NAME', 'nectar-blocks' );

if ( ! function_exists( 'get_nectar_theme_version' ) ) {
    function nectar_get_theme_version() {
        return NB_THEME_VERSION;
    }
}

/**
 * Load text domain.
 */
add_action( 'after_setup_theme', 'nectar_lang_setup' );

if ( ! function_exists( 'nectar_lang_setup' ) ) {
 function nectar_lang_setup() {
    load_theme_textdomain( 'nectar-blocks-theme', get_template_directory() . '/languages' );
 }
}

/**
 * General WordPress.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/wp-general.php';
require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-theme-manager.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/nectar-blocks-options.php';

/**
 * Get Nectar theme options.
 */
function get_nectar_theme_options() {
    return NectarBlocks_Options::get_instance()->get_nectar_theme_options();
}

$nectar_options = get_nectar_theme_options();
$nectar_get_template_directory_uri = get_template_directory_uri();

require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-dynamic-colors.php';
require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-dynamic-fonts.php';

/**
 * Load Kirki
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/nectar-blocks-kirki-compatibility.php';
require_once NECTAR_THEME_DIRECTORY . '/vendor/kirki-framework/kirki/kirki.php';

add_action( 'after_setup_theme', 'nectar_blocks_kirki_init', 10 );
function nectar_blocks_kirki_init() {
    $defaults_set = get_option( 'nectar_customizer_defaults_set', false );
    if ( is_customize_preview() || ! $defaults_set ) {
        require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-panel-section-helper.php';
        require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/nectar-blocks-customizer.php';
    }
}

/**
 * API
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/api/import-export-api.php';

/**
 * Updater
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/updater/NectarThemeUpdater.php';

/**
 * Register/Enqueue theme assets.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/icon-collections.php';
require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-element-assets.php';
require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-element-styles.php';
require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-lazy.php';
require_once NECTAR_THEME_DIRECTORY . '/includes/class-nectar-delay-js.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/enqueue-scripts.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/enqueue-styles.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/dynamic-styles.php';

/**
 * Theme hooks & actions.
 */
function nectar_hooks_init() {

    require_once NECTAR_THEME_DIRECTORY . '/nectar/hooks/hooks.php';
    require_once NECTAR_THEME_DIRECTORY . '/nectar/hooks/actions.php';

}

add_action( 'after_setup_theme', 'nectar_hooks_init', 10 );

/**
 * Post category meta.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/meta/category-meta.php';

/**
 * Media and theme image sizes.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/media.php';

/**
 * Navigation menus
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/assets/functions/wp-menu-custom-items/menu-item-custom-fields.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/nav-menus.php';

/**
 * Theme skin specific class and assets.
 */
$nectar_theme_skin = 'material';

add_filter( 'body_class', 'nectar_theme_skin_class' );

function nectar_theme_skin_class( $classes ) {
    global $nectar_theme_skin;
    $classes[] = $nectar_theme_skin;
    return $classes;
}

function nectar_theme_skin_css() {
    wp_enqueue_style( 'nectar-blocks-theme-skin' );
}

add_action( 'wp_enqueue_scripts', 'nectar_theme_skin_css' );

/**
 * Search related.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/search.php';

/**
 * Register Widget areas.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/widget-related.php';

/**
 * Header navigation helpers.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/header.php';

/**
 * Blog helpers.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/blog.php';

/**
 * Page helpers.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/page.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/footer.php';

/**
 * WordPress block editor helpers (Gutenberg).
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/gutenberg.php';

/**
 * Admin assets.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/admin-enqueue.php';

/**
 * Pagination Helpers.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/pagination.php';

/**
 * Page header.
 */
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/page-header.php';

/**
 * Third party.
 */
require_once NECTAR_THEME_DIRECTORY . '/includes/third-party-integrations/seo.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/wpml.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/woocommerce.php';

/**
 * v10.5 update assist.
 */
 require_once NECTAR_THEME_DIRECTORY . '/nectar/helpers/update-assist.php';
