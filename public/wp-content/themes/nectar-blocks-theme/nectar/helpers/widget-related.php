<?php

/**
 * NectarBlocks widget areas and custom widgets
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register widget areas.
 *
 * @since 2.0
 */
function nectar_register_widget_areas() {

    register_sidebar(
        [
            'name' => 'Blog Sidebar',
            'id' => 'blog-sidebar',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );
    register_sidebar(
        [
            'name' => 'Page Sidebar',
            'id' => 'page-sidebar',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );
    register_sidebar(
        [
            'name' => 'WooCommerce Sidebar',
            'id' => 'woocommerce-sidebar',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );
    register_sidebar(
        [
            'name' => 'Extra Sidebar',
            'id' => 'nectar-extra-sidebar',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );

    register_sidebar(
        [
            'name' => 'Footer Area 1',
            'id' => 'footer-area-1',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );

    global $nectar_options;
    $footer_columns = ( ! empty( $nectar_options['footer_columns'] ) ) ? $nectar_options['footer_columns'] : '4';

    if ( $footer_columns === '2' || $footer_columns === '3' || $footer_columns === '4' || $footer_columns === '5' ) {
        register_sidebar(
            [
                'name' => 'Footer Area 2',
                'id' => 'footer-area-2',
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget' => '</div>',
                'before_title' => '<h4>',
                'after_title' => '</h4>',
            ]
        );
    }

    if ( $footer_columns === '3' || $footer_columns === '4' || $footer_columns === '5' ) {
        register_sidebar(
            [
                'name' => 'Footer Area 3',
                'id' => 'footer-area-3',
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget' => '</div>',
                'before_title' => '<h4>',
                'after_title' => '</h4>',
            ]
        );
    }
    if ( $footer_columns === '4' || $footer_columns === '5' ) {
        register_sidebar(
            [
                'name' => 'Footer Area 4',
                'id' => 'footer-area-4',
                'before_widget' => '<div id="%1$s" class="widget %2$s">',
                'after_widget' => '</div>',
                'before_title' => '<h4>',
                'after_title' => '</h4>',
            ]
        );
    }

    // Off canvas menu.
    register_sidebar(
        [
            'name' => 'Off Canvas Menu',
            'id' => 'slide-out-widget-area',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );

    register_sidebar(
        [
            'name' => 'Footer Copyright',
            'id' => 'footer-area-copyright',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4>',
            'after_title' => '</h4>',
        ]
    );

}

add_action( 'widgets_init', 'nectar_register_widget_areas' );

// Allow shortcodes in text widget.
add_filter( 'widget_text', 'do_shortcode' );

// Alter parentheses in widget counts
if ( ! function_exists( 'nectar_remove_categories_count' ) ) {
    function nectar_remove_categories_count( $variable ) {
        $variable = str_replace( '(', '<span class="post_count"> ', $variable );
        $variable = str_replace( ')', ' </span>', $variable );
        return $variable;
    }
}

add_filter( 'wp_list_categories', 'nectar_remove_categories_count' );
