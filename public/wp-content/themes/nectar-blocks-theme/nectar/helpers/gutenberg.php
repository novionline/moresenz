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

    // Container sizing.
    $ext_padding = isset($nectar_options['ext_responsive_padding']) ? $nectar_options['ext_responsive_padding'] : '60';
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
		.post-type-post .edit-post-visual-editor__post-title-wrapper {
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
