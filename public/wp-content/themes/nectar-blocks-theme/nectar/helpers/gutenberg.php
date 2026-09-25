<?php

/**
 * Gutenberg helpers
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 9.0.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', 'nectar_gutenberg_editor_support' );
add_action( 'enqueue_block_assets', 'nectar_block_editor_assets' );

function nectar_block_editor_assets() {

    if ( ! is_admin() ) {
        return;
    }

    $nectar_options = get_nectar_theme_options();
    // Styles.
    $nectar_theme_version = nectar_get_theme_version();
    wp_enqueue_style( 'nectar-block-editor-styles', get_template_directory_uri() . '/css/build/style-editor.css', [], $nectar_theme_version );

    // Container sizing. `ext_responsive_padding` may be a legacy scalar or a
    // responsive object { desktop?, tablet?, mobile? } — extract desktop.
    $ext_padding_raw = isset($nectar_options['ext_responsive_padding']) ? $nectar_options['ext_responsive_padding'] : '60';
    if ( is_array( $ext_padding_raw ) ) {
        $ext_padding = isset( $ext_padding_raw['desktop'] ) && '' !== $ext_padding_raw['desktop']
            ? $ext_padding_raw['desktop']
            : '60';
    } else {
        $ext_padding = $ext_padding_raw;
    }
    $max_container_w = isset($nectar_options['max_container_width']) ? $nectar_options['max_container_width'] : '1400';

    // editor-styles-wrapper is separate from body is v2 and moving to body in future gutenberg versions.
    $editor_vars = 'html body, html body .editor-styles-wrapper, html body.editor-styles-wrapper {
		--wp--style--root--padding-left: ' . esc_attr($ext_padding) . 'px;
      	--wp--style--root--padding-right: ' . esc_attr($ext_padding) . 'px;
		--wp--style--global--content-size: ' . esc_attr($max_container_w) . 'px;
		--wp--style--global--wide-size: ' . ( intval($max_container_w) + 300 ) . 'px;
	}';

    // BLog post editing reduced width.
    $blog_hide_sidebar = ( isset( $nectar_options['blog_hide_sidebar'] ) && ! empty($nectar_options['blog_hide_sidebar']) ) ? $nectar_options['blog_hide_sidebar'] : false;
    if( '1' === $blog_hide_sidebar && isset( $nectar_options['blog_width'] ) && ! empty($nectar_options['blog_width']) ) {
        $blog_width = ( 'default' === $nectar_options['blog_width'] ) ? '1000px' : $nectar_options['blog_width'];

        // avoiding applying these to the template library
        $editor_vars .= '.post-type-post .is-root-container.is-desktop-preview,
		.post-type-post .edit-post-visual-editor__post-title-wrapper,
		.post-type-post .editor-visual-editor__post-title-wrapper {
			--wp--style--root--padding-left: ' . esc_attr($ext_padding) . 'px;
			--wp--style--root--padding-right: ' . esc_attr($ext_padding) . 'px;
			--wp--style--global--content-size: ' . esc_attr($blog_width) . ';
			--wp--style--global--wide-size: ' . ( intval($blog_width) + 300 ) . ';
		}';
    }

    // Colors.
    $overall_bg_color = isset($nectar_options['overall-bg-color']) && ! empty($nectar_options['overall-bg-color']) ? $nectar_options['overall-bg-color'] : '#ffffff';
    $overall_font_color = isset($nectar_options['overall-font-color']) && ! empty($nectar_options['overall-font-color']) ? $nectar_options['overall-font-color'] : '#000000';
    $editor_vars .= 'html body, html body .editor-styles-wrapper, html body.editor-styles-wrapper {
		--nectar-overall-bg-color: ' . esc_attr($overall_bg_color) . ';
		--nectar-overall-font-color: ' . esc_attr($overall_font_color) . ';
	}';

    wp_add_inline_style( 'nectar-block-editor-styles', $editor_vars );
}

/**
 * Declare Gutenberg support.
 *
 * @since 10.0
 */
function nectar_gutenberg_editor_support() {

    // Removes WP templates functionality to allow Nectarblocks to
    // utilize theme.json as a hybrid theme correctly.
    remove_theme_support('block-templates');

    add_theme_support(
        'gutenberg',
        [ 'wide-images' => true ]
    );

}

/**
 * WooCommerce Theme Builder template redirect.
 *
 * When a Theme Builder template is assigned to a WooCommerce page and its
 * display conditions pass, the Render class adds an action to the matching
 * hook (e.g. nectar_template_wc__checkout). This filter detects the current
 * WooCommerce page type, checks whether a Theme Builder template is hooked,
 * and redirects to the wrapper template that calls do_action().
 *
 * @since 3.0
 */
add_filter( 'template_include', 'nectar_wc_theme_builder_redirect', 99 );
add_filter( 'body_class', 'nectar_wc_theme_builder_body_class' );
add_filter( 'nectar_global_section_attrs', 'nectar_wc_template_section_attrs', 10, 2 );

/**
 * Resolves the current WooCommerce page to its Theme Builder hook name.
 *
 * @since 3.0
 * @return string|null Hook name or null if not a WooCommerce page.
 */
function nectar_resolve_wc_template_hook() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return null;
    }

    if ( is_product() )       return 'nectar_template_wc__single_product';
    if ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) return 'nectar_template_wc__archive_product';
    if ( is_cart() )          return 'nectar_template_wc__cart';
    if ( is_order_received_page() ) return 'nectar_template_wc__order_confirmation';
    if ( is_checkout() )      return 'nectar_template_wc__checkout';
    if ( is_account_page() )  return 'nectar_template_wc__my_account';

    return null;
}

/**
 * Redirects WooCommerce pages to the Theme Builder wrapper when a template is active.
 *
 * @since 3.0
 */
function nectar_wc_theme_builder_redirect( $template ) {
    $hook = nectar_resolve_wc_template_hook();

    if ( $hook && has_action( $hook ) ) {
        global $nectar_wc_active_hook;
        $nectar_wc_active_hook = $hook;
        return NECTAR_THEME_DIRECTORY . '/nectar/templates/fse-wrapper.php';
    }

    return $template;
}

/**
 * Adds a body class when a WooCommerce Theme Builder template is active.
 *
 * @since 3.0
 */
function nectar_wc_theme_builder_body_class( $classes ) {
    global $nectar_wc_active_hook;

    if ( ! empty( $nectar_wc_active_hook ) ) {
        $classes[] = 'nectar-block-template';
    }

    return $classes;
}

/**
 * Strips container classes from the global section wrapper for WooCommerce
 * templates, since the wrapper template already provides the container structure.
 *
 * @since 3.0
 */
function nectar_wc_template_section_attrs( $attrs, $location ) {
    if ( strpos( $location, 'nectar_template_wc__' ) === 0 ) {
        $attrs['class'] = $location . ' nectar-global-section';
    }
    return $attrs;
}
