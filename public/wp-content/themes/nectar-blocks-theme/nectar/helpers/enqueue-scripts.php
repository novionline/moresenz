<?php

/**
 * Enqueue scripts
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 13.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register/Enqueue frontend JS.
 *
 * @since 1.0
 */
function nectar_register_js() {

    global $nectar_options;
    global $post;
    global $nectar_get_template_directory_uri;

    $nectar_using_VC_front_end_editor = (isset($_GET['vc_editable'])) ? sanitize_text_field($_GET['vc_editable']) : '';
    $nectar_using_VC_front_end_editor = ($nectar_using_VC_front_end_editor == 'true') ? true : false;

    $nectar_theme_version = nectar_get_theme_version();

    if ( ! is_admin() ) {

    $nectar_dev_mode = apply_filters('nectar_dev_mode', false);
    $src_dir = ( $nectar_dev_mode == true ) ? 'src' : 'build';

        // Priority scripts.
        wp_register_script( 'jquery-easing', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/jquery.easing.min.js', [ 'jquery' ], '1.3', true );

        // Third party scripts.
        wp_register_script( 'hoverintent', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/hoverintent.min.js', [ 'jquery' ], '1.9', true );
        wp_register_script( 'imagesLoaded', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/imagesLoaded.min.js', [ 'jquery' ], '4.1.4', true );
        wp_register_script( 'superfish', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/superfish.js', [ 'jquery' ], '1.5.8', true );
        wp_register_script( 'nectar-smooth-scroll', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/nectar-smooth-scroll.js', [ 'jquery' ], $nectar_theme_version, true );

        wp_register_script( 'select2', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/select2.min.js', [ 'jquery' ], '4.0.1', true );
        wp_register_script( 'swiper', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/swiper.js', [ 'jquery' ], '11.0.3', true );

        wp_deregister_script( 'anime' );
        wp_register_script( 'anime', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/anime.min.js', [ 'jquery' ], '4.5.1', true );
        wp_register_script( 'stickykit', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/third-party/stickkit.js', [ 'jquery' ], '1.0', true );

        // Page option conditional scripts.
        wp_register_script( 'nectar-single-product', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/nectar-single-product.js', [ 'jquery' ], $nectar_theme_version, true );
        wp_register_script( 'nectar-single-product-reviews', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/nectar-single-product-reviews.js', [ 'jquery' ], $nectar_theme_version, true );
        wp_register_script( 'nectar-product-filters-display', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/nectar-product-filters-display.js', [], $nectar_theme_version );

        // Main NectarBlocks script.
        wp_register_script( 'nectar-theme-frontend', $nectar_get_template_directory_uri . '/js/' . $src_dir . '/init.js', [ 'jquery', 'superfish' ], $nectar_theme_version, true );

        wp_enqueue_script( 'nectar-transit' );
        wp_enqueue_script( 'nectar-waypoints' );

        wp_enqueue_script( 'imagesLoaded' );
        wp_enqueue_script( 'hoverintent' );

        $post_content = ( isset( $post->post_content ) ) ? $post->post_content : '';
        $nectar_box_roll = ( isset( $post->ID ) ) ? get_post_meta( $post->ID, '_nectar_header_box_roll', true ) : '';
        $page_full_screen_rows = ( isset( $post->ID ) ) ? get_post_meta( $post->ID, '_nectar_full_screen_rows', true ) : '';

        if ( ! empty( $nectar_options['portfolio_sidebar_follow'] ) && $nectar_options['portfolio_sidebar_follow'] === '1' && is_singular( 'portfolio' ) ) {
            wp_enqueue_script( 'stickykit' );
        }

        wp_dequeue_script( 'anime' );
        wp_enqueue_script( 'anime' );

        /*********for archive pages based on theme options*/
        $posttype = isset($post) ? get_post_type( $post ) : '';
        $nectar_on_blog_archive_check = ( is_archive() || is_author() || is_category() || is_home() || is_tag() ) && ( ! is_singular() );

        // Sticky sidebar.
        if ( ! empty( $nectar_options['blog_enable_ss'] ) && $nectar_options['blog_enable_ss'] === '1' && $nectar_on_blog_archive_check ) {
            wp_enqueue_script( 'stickykit' );
        }

        // Single post sticky sidebar.
        $enable_ss = ( ! empty( $nectar_options['blog_enable_ss'] ) ) ? $nectar_options['blog_enable_ss'] : 'false';

        if ( ( $enable_ss == '1' && is_single() && $posttype === 'post' ) ||
                    NectarElAssets::locate(['[vc_widget_sidebar']) ||
                    NectarElAssets::locate( ['style="vertical_scrolling"']) ) {
            wp_enqueue_script( 'stickykit' );
        }

        // Main NectarBlocks Script.
        wp_enqueue_script( 'nectar-theme-frontend' );

        // Smooth Scrolling.
        if ( isset( $nectar_options['smooth-scroll'] ) && $nectar_options['smooth-scroll'] === '1' ) {
            wp_enqueue_script('nectar-smooth-scroll');
        }

    }

    // Disqus plugin.
    $disqus_comments = ( function_exists( 'dsq_is_installed' ) ) ? 'true' : 'false';

    wp_localize_script(
        'nectar-theme-frontend',
        'nectarLove',
        [
            'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
            'postID' => isset($post->ID) ? $post->ID : '',
            'rooturl' => esc_url( home_url() ),
            'disqusComments' => $disqus_comments,
            'loveNonce' => wp_create_nonce( 'nectar-love-nonce' ),
            'mapApiKey' => ( ! empty( $nectar_options['google-maps-api-key'] ) ) ? $nectar_options['google-maps-api-key'] : '',
        ]
    );

    $woo_toggle_sidebar = true;
    if( has_filter('nectar_woocommerce_sidebar_toggles') ) {
        $woo_toggle_sidebar = apply_filters('nectar_woocommerce_sidebar_toggles', $woo_toggle_sidebar);
    }

    $ajax_search = ( ! empty( $nectar_options['header-disable-ajax-search'] ) && $nectar_options['header-disable-ajax-search'] === '1' ) ? 'no' : 'yes';
    $header_search = ( ! empty( $nectar_options['header-disable-search'] ) && $nectar_options['header-disable-search'] === '1' ) ? 'false' : 'true';
    $nectar_theme_skin = NectarThemeManager::$skin;
    $header_format = ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default';
    $header_entrance = 'false';

    if( isset($post->ID) ) {

        $entrance_animation = get_post_meta($post->ID, '_nectar_blocks_header_animation', true);

        if( '1' === $entrance_animation ) {
            $header_entrance = 'true';
        }

    }

    // Track if delayJs is enabled
    $delay_js = 'false';
    if (  isset( $nectar_options['delay-js-execution'] ) && $nectar_options['delay-js-execution'] === '1' ) {
        $delay_js_devices = ( isset( $nectar_options['delay-js-execution-devices'] ) ) ? $nectar_options['delay-js-execution-devices'] : 'mobile';

        if( 'all' === $delay_js_devices || wp_is_mobile() ) {
            $delay_js = '1';
        }
    }

    $ajax_add_to_cart = ( isset( $nectar_options['ajax-add-to-cart'] ) ) ? esc_html($nectar_options['ajax-add-to-cart']) : '0';
    $editing_subscription = isset($_GET['switch-subscription']) ? true : false;
    if ( $editing_subscription ) {
        $ajax_add_to_cart = '0';
    }

    $using_smooth_scroll = ( isset( $nectar_options['smooth-scroll'] ) && '1' === $nectar_options['smooth-scroll'] ) ? 'true' : 'false';

    wp_localize_script(
        'nectar-theme-frontend',
        'nectarOptions',
        [
            'delay_js' => $delay_js,
            'smooth_scroll' => $using_smooth_scroll,
            'smooth_scroll_strength' => ( isset( $nectar_options['smooth-scroll-strength'] ) ) ? esc_html($nectar_options['smooth-scroll-strength']) : '0.85',
            'quick_search' => ( $ajax_search === 'yes' && $header_search !== 'false' && $nectar_theme_skin === 'material' ) ? 'true' : 'false',
            'react_compat' => apply_filters('nectar_react_compatibility', 'disabled'), // deprecated -- removed from use in 16.0
            'header_entrance' => $header_entrance,
            'simplify_ocm_mobile' => ( isset( $nectar_options['header-slide-out-from-right-simplify-mobile'] ) ) ? esc_html($nectar_options['header-slide-out-from-right-simplify-mobile']) : 'false',
            'mobile_header_format' => ( isset( $nectar_options['mobile-menu-layout'] ) ) ? esc_html($nectar_options['mobile-menu-layout']) : 'default',
            'ocm_btn_position' => ( isset( $nectar_options['ocm_btn_position'] ) ) ? esc_html($nectar_options['ocm_btn_position']) : 'default',
            'left_header_dropdown_func' => ( isset( $nectar_options['left-header-dropdown-func'] ) ) ? esc_html($nectar_options['left-header-dropdown-func']) : 'default',
            'ajax_add_to_cart' => $ajax_add_to_cart,
            'ocm_remove_ext_menu_items' => ( isset( $nectar_options['header-slide-out-widget-area-image-display'] ) ) ? esc_html($nectar_options['header-slide-out-widget-area-image-display']) : 'default',
            'woo_product_filter_toggle' => ( isset( $nectar_options['product_filter_area'] ) ) ? esc_html($nectar_options['product_filter_area']) : '0',
            'woo_sidebar_toggles' => ( false === $woo_toggle_sidebar ) ? 'false' : 'true',
            'woo_sticky_sidebar' => ( isset( $nectar_options['main_shop_layout_sticky_sidebar'] ) ) ? esc_html($nectar_options['main_shop_layout_sticky_sidebar']) : '0',
            'woo_minimal_product_hover' => ( isset( $nectar_options['product_minimal_hover_layout'] ) ) ? esc_html($nectar_options['product_minimal_hover_layout']) : 'default',
            'woo_minimal_product_effect' => ( isset( $nectar_options['product_minimal_hover_effect'] ) ) ? esc_html($nectar_options['product_minimal_hover_effect']) : 'default',
            'woo_related_upsell_carousel' => ( isset( $nectar_options['single_product_related_upsell_carousel'] ) && '1' === $nectar_options['single_product_related_upsell_carousel'] ) ? 'true' : 'false',
            'woo_product_variable_select' => ( isset( $nectar_options['product_variable_select_style'] ) ) ? esc_html($nectar_options['product_variable_select_style']) : 'default',
        ]
    );

    wp_localize_script(
        'nectar-theme-frontend',
        'nectar_front_i18n',
        [
            'menu' => esc_html__('Menu', 'nectar-blocks-theme'),
            'next' => esc_html__('Next', 'nectar-blocks-theme'),
            'previous' => esc_html__('Previous', 'nectar-blocks-theme'),
            'close' => esc_html__('Close', 'nectar-blocks-theme'),
        ]
    );

}

add_action( 'wp_enqueue_scripts', 'nectar_register_js' );

/**
 * Enqueue page specific JS.
 *
 * @since 1.0
 */
function nectar_page_specific_js() {

    global $post;
    global $nectar_options;
    global $nectar_get_template_directory_uri;

    if ( ! is_object( $post ) ) {
        $post = (object) [
            'post_content' => ' ',
            'ID' => ' ',
        ];
    }
    $template_name = get_post_meta( $post->ID, '_wp_page_template', true );

    if( class_exists( 'WooCommerce' ) ) {

        // Archives.
        if( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) {

            if( true === NectarThemeManager::$woo_product_filters ) {
                wp_enqueue_script( 'nectar-product-filters-display' );
                wp_localize_script(
                    'nectar-product-filters-display',
                    'nectarProductFilterOptions',
                    [
                        'startingState' => ( isset( $nectar_options['product_filter_area_starting_state'] ) && 'closed' !== $nectar_options['product_filter_area_starting_state'] ) ? 'open' : 'closed'
                    ]
                );
            }

            if( isset( $nectar_options['main_shop_layout_sticky_sidebar'] ) && '1' === $nectar_options['main_shop_layout_sticky_sidebar'] ) {
                wp_enqueue_script( 'stickykit' );
            }

        }

        // Single Product.
        if( is_product() ) {

            $product_gallery_style = (isset($nectar_options['single_product_gallery_type'])) ? $nectar_options['single_product_gallery_type'] : 'default';

            if( isset( $nectar_options['single_product_related_upsell_carousel'] ) &&
                '1' === $nectar_options['single_product_related_upsell_carousel'] ) {
                wp_enqueue_script( 'swiper' );
            }
            if( in_array($product_gallery_style, ['ios_slider', 'left_thumb_sticky']) ) {
                wp_enqueue_script( 'swiper' );
            }
            if( in_array($product_gallery_style, [ 'left_thumb_sticky', 'two_column_images' ]) ) {
                wp_enqueue_script( 'stickykit' );
            }

            wp_enqueue_script('nectar-single-product');

            if( isset( $nectar_options['product_reviews_style'] ) &&
            'off_canvas' === $nectar_options['product_reviews_style'] ) {
                wp_enqueue_script('nectar-single-product-reviews');
            }

        }

    }

    // Nectar slider.
    if ( NectarElAssets::locate(['[nectar_slider']) || NectarElAssets::locate(['type="nectarslider_style"']) ) {
        wp_enqueue_script( 'nectar-slider' );
    }

    // Touch swipe.
    wp_enqueue_script( 'touchswipe' );

    // Fancy select.
    $fancy_rcs = ( ! empty( $nectar_options['form-fancy-select'] ) ) ? $nectar_options['form-fancy-select'] : 'default';
    if ( $fancy_rcs === '1' ) {
        wp_enqueue_script( 'select2' );
    }

    // comments
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }

}

add_action( 'wp_enqueue_scripts', 'nectar_page_specific_js' );

if( ! function_exists('nectar_defer_parsing_of_jquery') ) {
    function nectar_defer_parsing_of_jquery( $wp_scripts ) {

        $wp_scripts->add_data( 'jquery', 'group', 1 );
        $wp_scripts->add_data( 'jquery-core', 'group', 1 );
        $wp_scripts->add_data( 'jquery-migrate', 'group', 1 );
        $wp_scripts->add_data( 'jquery-blockui', 'group', 1 );
    }
}

global $nectar_options;

if( isset($nectar_options['defer-javascript']) &&
        ! empty($nectar_options['defer-javascript']) &&
        '1' === $nectar_options['defer-javascript'] ) {

    $nectar_using_VC_front_end_editor = (isset($_GET['vc_editable'])) ? sanitize_text_field($_GET['vc_editable']) : '';
    $nectar_using_VC_front_end_editor = ($nectar_using_VC_front_end_editor == 'true') ? true : false;

    if( false === $nectar_using_VC_front_end_editor && ! is_admin() ) {
      add_action( 'wp_default_scripts', 'nectar_defer_parsing_of_jquery', 20 );
    }
}

