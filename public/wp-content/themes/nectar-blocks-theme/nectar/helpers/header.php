<?php

/**
 * Header related helper functions
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 13.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter('nectar_activate_transparent_header', 'nectar_blocks_transparent_header_option', 10);
if( ! function_exists('nectar_blocks_transparent_header_option') ) {
    function nectar_blocks_transparent_header_option($active) {
        global $post;
        // Avoid using loop post data when rendering archive contexts.
        if ( is_archive() || is_home() ) {
            return $active;
        }
        if (! $post) {
            return false;
        }

        // Customizer transparent-header is the master switch. When it's off, the per-post
        // _nectar_blocks_transparent_header_effect should NOT force-activate transparency.
        // Contained-header is independent of the customizer toggle and stays exempt.
        if ( ! nectar_customizer_trans_header_enabled() && ! nectar_is_contained_header() ) {
            return $active;
        }

        $transparent_effect = get_post_meta( $post->ID, '_nectar_blocks_transparent_header_effect', true );

        if ( $transparent_effect === '1' ) {
            return true;
        }
        return $active;

    }
}
add_filter('nectar_transparent_header_coloring', 'nectar_blocks_transparent_header_color');

if( ! function_exists('nectar_blocks_transparent_header_color') ) {
    function nectar_blocks_transparent_header_color($color) {
        global $post;
        // Archive/search contexts should defer to the default color behavior.
        if ( is_archive() || is_home() ) {
            return $color;
        }
        if (! $post) {
            return false;
        }

        $transparent_color = get_post_meta( $post->ID, '_nectar_blocks_transparent_header_effect_color', true );
        if ( $transparent_color && in_array($transparent_color, ['light','dark']) ) {
            return $transparent_color;
        }
        return $color;
    }
}

if( ! function_exists('nectar_get_forced_transparent_header_color') ) {
    function nectar_get_forced_transparent_header_color() {

        global $woocommerce;
        global $post;

        if($woocommerce && is_shop() || $woocommerce && is_product_category() || $woocommerce && is_product_tag()) {
            $force_transparent_header_color = get_post_meta( wc_get_page_id('shop'), '_force_transparent_header_color', true );
        } else {
            $force_transparent_header_color = ( isset( $post->ID ) ) ? get_post_meta( $post->ID, '_force_transparent_header_color', true ) : '';
        }

        // Filter color.
        if( has_filter('nectar_transparent_header_coloring') ) {

            $supported_colors = ['light','dark'];
            $filtered_color = apply_filters('nectar_transparent_header_coloring', $force_transparent_header_color);

            if( in_array($filtered_color, $supported_colors) ) {
                return $filtered_color;
            }

        }

        return $force_transparent_header_color;

    }
}

/**
 * Return the variables needed for header/body
 *
 * @since 9.0.2
 */
function nectar_get_header_variables() {

    $nectar_options = get_nectar_theme_options();
    global $post;
    global $woocommerce;

    $nectar_using_VC_front_end_editor = (isset($_GET['vc_editable'])) ? sanitize_text_field($_GET['vc_editable']) : '';
    $nectar_using_VC_front_end_editor = ($nectar_using_VC_front_end_editor == 'true') ? true : false;

    $header_format = ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default';
    $centered_menu_bottom_bar_align = ( isset($nectar_options['centered-menu-bottom-bar-alignment']) && ! empty( $nectar_options['centered-menu-bottom-bar-alignment'] ) ) ? $nectar_options['centered-menu-bottom-bar-alignment'] : 'center';

    // Check if parallax nectar slider is being used (needed for raw shortcode outside page builder).
    $parallax_nectar_slider = using_nectar_slider();
    $force_effect = get_post_meta( $post->ID, '_force_transparent_header', true );

    // Header transparent option.
    $transparency_markup = null;
    $activate_transparency = null;
    $nectar_transparency_color_class = '';
    $nectar_transparency_color_forced = 'light';

    $using_page_header = nectar_using_page_header( $post->ID );
    $using_fw_slider = $parallax_nectar_slider;
    $using_fw_slider = ( ! empty( $nectar_options['transparent-header'] ) && $nectar_options['transparent-header'] == '1' || nectar_is_contained_header() ) ? $using_fw_slider : 0;
    if ( $force_effect === 'on' ) {
        $using_fw_slider = '1';
    }
    $disable_effect = get_post_meta( $post->ID, '_disable_transparent_header', true );

    $force_transparent_header_color = nectar_get_forced_transparent_header_color();

    $theme_skin = NectarThemeManager::$skin;

    $is_header_builder_mode = function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template();

    if ( $is_header_builder_mode ) {
        $header_format = 'default';
    }

    // Header builder mode: only require the meta option (page-level setting).
    // Non-header builder mode: require both theme option AND meta conditions.
    $theme_option_allows_transparency = ! empty( $nectar_options['transparent-header'] ) &&
        $nectar_options['transparent-header'] === '1' &&
        $header_format != 'left-header';

    $should_process_transparency = $is_header_builder_mode ||
        $theme_option_allows_transparency ||
        nectar_is_contained_header();

    if ( $should_process_transparency ) {

        $starting_color = ( empty( $nectar_options['header-starting-color'] ) ) ? '#ffffff' : $nectar_options['header-starting-color'];
        $activate_transparency = $using_page_header;
        $remove_border = ( ! empty( $nectar_options['header-remove-border'] ) && $nectar_options['header-remove-border'] === '1' || $theme_skin === 'material' ) ? 'true' : 'false';
        $transparent_header_shadow = ( ! empty( $nectar_options['transparent-header-shadow-helper'] ) && $nectar_options['transparent-header-shadow-helper'] === '1' ) ? 'true' : 'false';
        $nectar_transparency_color_class = ( $force_transparent_header_color === 'dark' ) ? ' dark-slide' : '';

        if ( $force_transparent_header_color === 'dark' ) {
            $nectar_transparency_color_forced = 'dark';
        }

        if( nectar_is_contained_header() ) {
            $activate_transparency = 'true';
            $transparent_header_shadow = 'false';
            $nectar_transparency_color_class = '';
            $remove_border = 'false';
        }

        $transparency_markup = null;

        if ($activate_transparency == 'true') {
            $transparency_markup = 'data-transparent-header="true" ';
            // Skip these attributes in header builder mode - they're handled by block editor.
            if ( ! $is_header_builder_mode ) {
                if ( $transparent_header_shadow == 'true' ) {
                    $transparency_markup .= 'data-transparent-shadow-helper="' . esc_attr($transparent_header_shadow) . '" ';
                }
                if ( $remove_border == 'true' ) {
                    $transparency_markup .= 'data-remove-border="' . esc_attr($remove_border) . '" ';
                }
            }
            $transparency_markup .= 'class="' . apply_filters("nectar_header_outer_classes", 'transparent') . esc_attr($nectar_transparency_color_class) . '"';
        }
    }

    // header vars
    $logo_class = ( ! empty( $nectar_options['use-logo'] ) && $nectar_options['use-logo'] === '1' ) ? '' : 'class="no-image"';
    $using_mobile_logo = ( ! empty( $nectar_options['use-logo'] ) && $nectar_options['use-logo'] === '1' && ! empty( $nectar_options['mobile-logo'] ) && ! empty( $nectar_options['mobile-logo']['url'] ) ) ? 'true' : 'false';
    $using_mobile_logo_s = ( ! empty( $nectar_options['use-logo'] ) && $nectar_options['use-logo'] === '1' && ! empty( $nectar_options['header-starting-mobile-only-logo'] ) && ! empty( $nectar_options['header-starting-mobile-only-logo']['url'] ) ) ? 'true' : 'false';
    $using_mobile_logo_sd = ( ! empty( $nectar_options['use-logo'] ) && $nectar_options['use-logo'] === '1' && ! empty( $nectar_options['header-starting-mobile-only-logo-dark'] ) && ! empty( $nectar_options['header-starting-mobile-only-logo-dark']['url'] ) ) ? 'true' : 'false';
    $side_widget_area = ( ! empty( $nectar_options['header-slide-out-widget-area'] ) && $header_format != 'left-header' ) ? $nectar_options['header-slide-out-widget-area'] : 'off';
    $side_widget_class = nectar_get_ocm_style_with_header_builder_fallback( NectarThemeManager::$ocm_style );
    $header_search = ( ! empty( $nectar_options['header-disable-search'] ) && $nectar_options['header-disable-search'] === '1' ) ? 'false' : 'true';
    $user_account_btn = ( ! empty( $nectar_options['header-account-button'] ) && $nectar_options['header-account-button'] === '1' ) ? 'true' : 'false';
    $user_account_btn_url = ( ! empty( $nectar_options['header-account-button-url'] ) ) ? $nectar_options['header-account-button-url'] : '';
    $mobile_fixed = ( ! empty( $nectar_options['header-mobile-fixed'] ) ) ? $nectar_options['header-mobile-fixed'] : 'false';
    $mobile_breakpoint = ( ! empty( $nectar_options['header-menu-mobile-breakpoint'] ) ) ? $nectar_options['header-menu-mobile-breakpoint'] : 1000;
    $full_width_header = ( ! empty( $nectar_options['header-fullwidth'] ) && $nectar_options['header-fullwidth'] === '1' ) ? 'true' : 'false';
    $header_color_scheme = ( ! empty( $nectar_options['header-color'] ) ) ? $nectar_options['header-color'] : 'light';
    $user_set_bg = ( ! empty( $nectar_options['header-background-color'] ) && $header_color_scheme === 'custom' ) ? $nectar_options['header-background-color'] : '#ffffff';
    $trans_header = ( ! empty( $nectar_options['transparent-header'] ) && $nectar_options['transparent-header'] === '1' ) ? $nectar_options['transparent-header'] : 'false';
    if ( $header_format === 'left-header' ) {
        $trans_header = 'false';
    }
    $bg_header = ( ! empty( $post->ID ) && $post->ID != 0 ) ? $using_page_header : 0;
    $bg_header = ( $bg_header == 1 ) ? 'true' : 'false';
    $header_box_shadow = ( ! empty( $nectar_options['header-box-shadow'] ) ) ? $nectar_options['header-box-shadow'] : 'small';
    $header_remove_stickiness = ( ! empty( $nectar_options['header-remove-fixed'] ) ) ? $nectar_options['header-remove-fixed'] : '0';
    if( $nectar_using_VC_front_end_editor ) {
        $header_remove_stickiness = '1';
    }

    $condense_header_on_scroll = ( ! empty( $nectar_options['condense-header-on-scroll'] ) && $header_format === 'centered-menu-bottom-bar' && $header_remove_stickiness !== '1' && $nectar_options['condense-header-on-scroll'] === '1' ) ? 'true' : 'false';
    $perm_trans = ( ! empty( $nectar_options['header-permanent-transparent'] ) && $trans_header != 'false' && $bg_header == 'true' && $header_format !== 'centered-menu-bottom-bar' ) ? $nectar_options['header-permanent-transparent'] : 'false';
    $header_link_hover_effect = ( ! empty( $nectar_options['header-hover-effect'] ) ) ? $nectar_options['header-hover-effect'] : 'default';
    $hide_header_until_needed = ( ! empty( $nectar_options['header-hide-until-needed'] ) && $header_format !== 'centered-menu-bottom-bar' ) ? $nectar_options['header-hide-until-needed'] : '0';

    if ( $header_format === 'centered-menu-bottom-bar' ) {
        $hide_header_until_needed = '0';
    }
    if ( $header_format === 'left-header' ) {
        $hide_header_until_needed = '0';
        $header_remove_stickiness = '0';
    }
    if ( $header_remove_stickiness === '1' ) {
        $hide_header_until_needed = '1';
    }
    $header_resize = ( ! empty( $nectar_options['header-resize-on-scroll'] ) && $header_format !== 'centered-menu-bottom-bar' ) ? $nectar_options['header-resize-on-scroll'] : '0';
    $dropdown_style = 'minimal';
    $page_transition_effect = ( ! empty( $nectar_options['transition-effect'] ) ) ? $nectar_options['transition-effect'] : 'standard';
    $megamenuwidth = ( ! empty( $nectar_options['header-megamenu-width'] ) && $header_format != 'left-header' ) ? $nectar_options['header-megamenu-width'] : 'contained';
    $body_border = ( ! empty( $nectar_options['body-border'] ) ) ? $nectar_options['body-border'] : 'off';

    if ( $hide_header_until_needed === '1' || $body_border === '1' || $header_format === 'left-header' || $header_remove_stickiness === '1' ) {
        $header_resize = '0';
    }

    $lightbox_script = ( ! empty( $nectar_options['lightbox_script'] ) ) ? $nectar_options['lightbox_script'] : 'magnific';
    if ( $lightbox_script === 'pretty_photo' ) {
        $lightbox_script = 'magnific';
    }

    $button_styling = ( ! empty( $nectar_options['button-styling'] ) ) ? $nectar_options['button-styling'] : 'default';
    //$header_button_styling = ( isset($nectar_options['header-button-styling']) && ! empty( $nectar_options['header-button-styling'] ) ) ? $nectar_options['header-button-styling'] : 'default';
    $header_button_styling = 'default';
    $form_style = ( ! empty( $nectar_options['form-style'] ) ) ? $nectar_options['form-style'] : 'default';
    $fancy_rcs = ( ! empty( $nectar_options['form-fancy-select'] ) ) ? $nectar_options['form-fancy-select'] : 'default';

    $has_main_menu = ( has_nav_menu( 'top_nav' ) ) ? 'true' : 'false';
    $animate_in_effect = ( ! empty( $nectar_options['header-animate-in-effect'] ) ) ? $nectar_options['header-animate-in-effect'] : 'none';

    if ( $header_color_scheme === 'dark' ) {
        $user_set_bg = '#1f1f1f';
    }

    $user_set_side_widget_area = $side_widget_area;

    if ( $has_main_menu === 'true' || $header_format === 'centered-logo-between-menu-alt' ) {
        $side_widget_area = '1';
    }

    if ( $header_format === 'centered-menu-under-logo' ) {
        if ( $side_widget_class === 'slide-out-from-right-hover' && $user_set_side_widget_area === '1' ) {
            $side_widget_class = 'slide-out-from-right';
        }
        $full_width_header = 'false';
    }
    if ( $side_widget_class === 'slide-out-from-right-hover' && $user_set_side_widget_area === '1' ) {
        $full_width_header = 'true';
    }

    $prepend_top_nav_mobile = ( ! empty( $nectar_options['header-slide-out-widget-area-top-nav-in-mobile'] ) && $user_set_side_widget_area === '1' ) ? $nectar_options['header-slide-out-widget-area-top-nav-in-mobile'] : 'false';
    $smooth_scrolling = '0';
    $form_submit_style = ( ! empty( $nectar_options['form-submit-btn-style'] ) ) ? $nectar_options['form-submit-btn-style'] : 'default';
    $n_remove_mobile_parallax = ( ! empty( $nectar_options['disable-mobile-parallax'] ) && $nectar_options['disable-mobile-parallax'] === '1' ) ? true : false;
    $n_remove_mobile_video_bgs = ( ! empty( $nectar_options['disable-mobile-video-bgs'] ) && $nectar_options['disable-mobile-video-bgs'] === '1' ) ? true : false;
    $n_mobile_animations = ( ! empty( $nectar_options['column_animation_mobile'] ) && $nectar_options['column_animation_mobile'] === 'enable' ) ? '1' : '0';
    $using_secondary = ( ! empty( $nectar_options['header_layout'] ) && $header_format != 'left-header' ) ? $nectar_options['header_layout'] : ' ';
    $header_text_widget = ( isset($nectar_options['header-text-widget']) && ! empty( $nectar_options['header-text-widget'] )) ? $nectar_options['header-text-widget'] : '';

    $ocm_menu_btn_bg_color = 'false';

    if( isset($nectar_options['header-slide-out-widget-area-menu-btn-bg-color']) &&
  ! empty( $nectar_options['header-slide-out-widget-area-menu-btn-bg-color'] ) ) {

        // Ascend full width does not support custom OCM coloring.
        $ocm_menu_btn_color_non_compatible = ( 'ascend' === $theme_skin && 'true' === $full_width_header ) ? true : false;

        if( false === $ocm_menu_btn_color_non_compatible ) {
            $ocm_menu_btn_bg_color = 'true';
        }

  }

    // using pr
    $using_pr_menu = 'false';
    if ( $header_format === 'menu-left-aligned' ||
            $header_format === 'centered-menu' ||
            $header_format === 'centered-logo-between-menu' ||
          $header_format === 'centered-logo-between-menu-alt' ) {
                if ( has_nav_menu( 'top_nav_pull_right' ) ) {
                    $using_pr_menu = 'true';
                }
    }

    $using_header_buttons = nectar_header_button_check();
    $header_transparency_bool = ( ! empty( $nectar_options['transparent-header'] ) && $nectar_options['transparent-header'] === '1' ) ? true : false;

    $nectar_header_options = [
        'options' => $nectar_options,
        'theme_skin' => $theme_skin,
        'header_format' => $header_format,
        'centered_menu_bottom_bar_align' => $centered_menu_bottom_bar_align,
        'disable_effect' => $disable_effect,
        'force_effect' => $force_effect,
        'using_fw_slider' => $using_fw_slider,
        'force_transparent_header_color' => $force_transparent_header_color,
        'parallax_nectar_slider' => $parallax_nectar_slider,
        'nectar_transparency_color_class' => $nectar_transparency_color_class,
        'using_page_header' => $using_page_header,
        'activate_transparency' => $activate_transparency,
        'header_transparency_bool' => $header_transparency_bool,
        'dropdown_style' => $dropdown_style,
        'n_remove_mobile_video_bgs' => $n_remove_mobile_video_bgs,
        'n_remove_mobile_parallax' => $n_remove_mobile_parallax,
        'n_mobile_animations' => $n_mobile_animations,
        'form_submit_style' => $form_submit_style,
        'smooth_scrolling' => $smooth_scrolling,
        'prepend_top_nav_mobile' => $prepend_top_nav_mobile,
        'full_width_header' => $full_width_header,
        'side_widget_class' => $side_widget_class,
        'side_widget_area' => $side_widget_area,
        'ocm_menu_btn_color' => $ocm_menu_btn_bg_color,
        'user_set_side_widget_area' => $user_set_side_widget_area,
        'user_set_bg' => $user_set_bg,
        'animate_in_effect' => $animate_in_effect,
        'has_main_menu' => $has_main_menu,
        'fancy_rcs' => $fancy_rcs,
        'form_style' => $form_style,
        'button_styling' => $button_styling,
        'header_button_styling' => $header_button_styling,
        'lightbox_script' => $lightbox_script,
        'header_resize' => $header_resize,
        'body_border' => $body_border,
        'megamenuwidth' => $megamenuwidth,
        'page_transition_effect' => $page_transition_effect,
        'dropdown_style' => $dropdown_style,
        'hide_header_until_needed' => $hide_header_until_needed,
        'header_remove_stickiness' => $header_remove_stickiness,
        'header_link_hover_effect' => $header_link_hover_effect,
        'perm_trans' => $perm_trans,
        'condense_header_on_scroll' => $condense_header_on_scroll,
        'header_remove_stickiness' => $header_remove_stickiness,
        'header_box_shadow' => $header_box_shadow,
        'bg_header' => $bg_header,
        'trans_header' => $trans_header,
        'header_color_scheme' => $header_color_scheme,
        'mobile_breakpoint' => $mobile_breakpoint,
        'mobile_fixed' => $mobile_fixed,
        'user_account_btn_url' => $user_account_btn_url,
        'user_account_btn' => $user_account_btn,
        'header_search' => $header_search,
        'using_mobile_logo' => $using_mobile_logo,
        'using_mobile_logo_starting' => $using_mobile_logo_s,
        'using_mobile_logo_starting_dark' => $using_mobile_logo_sd,
        'logo_class' => $logo_class,
        'transparency_markup' => $transparency_markup,
        'nectar_transparency_color_forced' => $nectar_transparency_color_forced,
        'using_pr_menu' => $using_pr_menu,
        'using_header_buttons' => $using_header_buttons,
        'using_secondary' => $using_secondary,
        'header_text_widget' => $header_text_widget,
    ];

    return $nectar_header_options;

}

add_filter('nectar_header_outer_classes', 'nectar_header_outer_classes_mod');

if( ! function_exists('nectar_header_outer_classes_mod') ) {
    function nectar_header_outer_classes_mod($classes) {
        if ( nectar_is_contained_header() ) {
            $classes .= ' force-contained-rows';
        }

        return $classes;
    }
}

/**
 * Output the NectarBlocks specific body attributes
 *
 * @since 9.02
 */
function nectar_body_attributes() {

    global $woocommerce;
    global $nectar_options;

    $nectar_header_options = nectar_get_header_variables();
    extract( $nectar_header_options );

    if ( $side_widget_area === '1' ) {
        echo 'data-slide-out-widget-area="true" ';
    } else {
        echo 'data-slide-out-widget-area="false" ';
    }

    echo 'data-user-set-ocm="' . esc_attr( $user_set_side_widget_area ) . '" ';
    echo 'data-slide-out-widget-area-style="' . esc_attr( $side_widget_class ) . '" ';

    echo 'data-header-format="' . esc_attr( $header_format ) . '" ';
    echo 'data-header-breakpoint="' . esc_attr( $mobile_breakpoint ) . '" ';
    echo 'data-dropdown-style="' . esc_attr( $dropdown_style ) . '" ';
    echo 'data-anchor-js-scroll="' . apply_filters('nectar_animated_anchors', 'true') . '" ';
    echo 'data-form-b-style="' . esc_attr( $form_submit_style ) . '" ';
    echo 'data-form-select-js="' . esc_attr( $fancy_rcs ) . '" ';
    echo 'data-form-style="' . esc_attr( $form_style ) . '" ';
    echo 'data-hhun="' . esc_attr( $hide_header_until_needed ) . '" ';
    if ( $woocommerce && ! empty( $nectar_options['enable-cart'] ) && $nectar_options['enable-cart'] === '1' ) {
        echo 'data-cart="true" ';
    } else {
        echo 'data-cart="false" ';
    }
    echo 'data-button-style="' . esc_attr( $button_styling ) . '" ';
    echo 'data-header-search="' . esc_attr( $header_search ) . '" ';
    echo 'data-user-account-button="' . esc_attr( $user_account_btn ) . '" ';
    if ( nectar_is_contained_header() ) {
        echo 'data-contained-header="true" ';
    }

    // Modern grid system.
    if( function_exists('nectar_use_flexbox_grid') && true === nectar_use_flexbox_grid() ) {
        /* NectarBlocks provides a modern flexbox grid system as of v11 as long
        as the NectarBlocks core and NectarBlocks page builder plugins are up to date. */
        $nectar_column_gap = ( isset( $nectar_options['column-spacing'] ) && ! empty( $nectar_options['column-spacing'] ) ) ? $nectar_options['column-spacing'] : 'default';
        echo 'data-col-gap="' . esc_attr($nectar_column_gap) . '" ';
    }

    echo 'data-full-width-header="' . esc_attr( $full_width_header ) . '" ';

    if ( ! nectar_is_perma_trans_header_forced() ) {
        echo 'data-bg-header="' . esc_attr( $bg_header ) . '" ';
    }

    echo 'data-responsive="1" ';
    echo 'data-ext-responsive="true" ';

    // `ext_responsive_padding` may be a legacy scalar or a responsive object
    // { desktop?, tablet?, mobile? } — emit desktop as the data-attribute value.
    $ext_pad_setting = isset( $nectar_options['ext_responsive_padding'] ) ? $nectar_options['ext_responsive_padding'] : '';
    if ( is_array( $ext_pad_setting ) ) {
        $ext_pad_value = isset( $ext_pad_setting['desktop'] ) ? (string) $ext_pad_setting['desktop'] : '';
    } else {
        $ext_pad_value = (string) $ext_pad_setting;
    }
    if ( '' !== $ext_pad_value && '90' !== $ext_pad_value ) {
        echo 'data-ext-padding="' . esc_attr( $ext_pad_value ) . '" ';
    } else {
        echo 'data-ext-padding="90" ';
    }

    echo 'data-permanent-transparent="' . esc_attr( $perm_trans ) . '" ';
    echo 'data-force-header-trans-color="' . esc_attr( $nectar_transparency_color_forced ) . '" ';
    echo 'data-header-resize="' . esc_attr( $header_resize ) . '" ';

    if ( ! empty( $nectar_options['header-color'] ) ) {
        echo 'data-header-color="' . esc_attr( $nectar_options['header-color'] ) . '" ';
    } else {
        echo 'data-header-color="light" ';
    }

    if ( $header_transparency_bool == false ) {
        echo 'data-transparent-header="false" ';
    }

}

/**
 * Output minimal header navigation attributes for header builder mode.
 * Only outputs transparency-related attributes since everything else is handled by the block editor.
 *
 * @since 14.0
 */
function nectar_header_nav_attributes_builder() {
    $nectar_header_options = nectar_get_header_variables();
    extract( $nectar_header_options );

    /**
     * Filter the class list applied to `#nectar-nav` in header builder mode.
     * Generic hook — any plugin/theme override can contribute classes.
     *
     * Note on the post id: `get_the_ID()` returns 0 here because the header
     * renders before the main loop sets up the global `$post`. Use the
     * queried object id, which is populated as soon as `parse_query` runs.
     *
     * @param string[] $classes  List of class names (will be space-joined).
     * @param int      $post_id  Queried post id, or 0 when not on a singular.
     */
    $classes = apply_filters( 'nectar_header_nav_classes', [], get_queried_object_id() );
    $extra_classes = '';
    if ( is_array( $classes ) && ! empty( $classes ) ) {
        $sanitized = array_filter( array_map( 'sanitize_html_class', $classes ) );
        if ( ! empty( $sanitized ) ) {
            $extra_classes = implode( ' ', $sanitized );
        }
    }

    // Transparency-related attributes.
    if ( $disable_effect === 'on' ) {
        echo 'data-transparency-option="0" ';
    } else {
        echo 'data-transparency-option="' . esc_attr( $using_fw_slider ) . '" ';
    }

    // Filter-contributed classes (e.g. `is-variant-<key>`) must share the SAME
    // `class="..."` attribute as the transparency markup. Emitting them as a
    // separate attribute produces two `class=` attrs on #nectar-nav; the browser
    // keeps only the first and drops `transparent`, so transparency is missing on
    // first paint until JS re-adds it on scroll. Merge into the single attribute.
    if ( $transparency_markup ) {
        if ( '' !== $extra_classes ) {
            $transparency_markup = preg_replace(
                '/\bclass="/',
                'class="' . esc_attr( $extra_classes ) . ' ',
                $transparency_markup,
                1
            );
        }
        echo $transparency_markup;
    } elseif ( '' !== $extra_classes ) {
        echo 'class="' . esc_attr( $extra_classes ) . '" ';
    }

    // Mobile sticky header.
    echo 'data-mobile-fixed="' . esc_attr( $mobile_fixed ) . '" ';

    // Marker so globally-cached legacy CSS that styles the shared `#nectar-nav`
    // wrapper directly (e.g. the header background-color) can scope itself out of
    // builder-managed headers via `:not([data-header-builder])`.
    echo 'data-header-builder="true" ';
}

/**
 * Output the NectarBlocks header navigation attributes
 *
 * @since 9.0.2
 */
function nectar_header_nav_attributes() {

    // Use minimal attributes for header builder mode.
    if ( function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template() ) {
        nectar_header_nav_attributes_builder();
        return;
    }

    global $woocommerce;
    global $nectar_options;

    $nectar_header_options = nectar_get_header_variables();
    extract( $nectar_header_options );

    if( in_array(   $header_format, ['centered-logo-between-menu-alt'] ) ) {
        $has_main_menu = 'true';
    }

    $using_logo = ( isset($nectar_options['use-logo']) && ! empty($nectar_options['use-logo']) ) ? $nectar_options['use-logo'] : '0';

    echo 'data-using-logo="' . esc_attr( $using_logo ) . '" ';

    // Img logo.
    if( '1' === $using_logo ) {

        if ( ! empty( $nectar_options['logo-height'] ) ) {
            echo 'data-logo-height="' . esc_attr( $nectar_options['logo-height'] ) . '" ';
        } else {
            echo 'data-logo-height="30" ';
        }

    }
    // Font logo.
    else {

        $font_logo_height = '22';

        // Custom size from typography logo line height option.
        if( isset($nectar_options['logo_font_family']['line-height']) &&
            ! empty($nectar_options['logo_font_family']['line-height']) ) {
            $font_logo_height = intval(substr($nectar_options['logo_font_family']['line-height'], 0, -2));
        }
        // Custom size from typography logo font size option.
        else if( isset($nectar_options['logo_font_family']['font-size']) &&
                 ! empty($nectar_options['logo_font_family']['font-size']) ) {
            $font_logo_height = intval(substr($nectar_options['logo_font_family']['font-size'], 0, -2));
        }

        echo 'data-logo-height="' . esc_attr($font_logo_height) . '" ';

    }

    if ( ! empty( $nectar_options['mobile-logo-height'] ) ) {
        echo 'data-m-logo-height="' . esc_attr( $nectar_options['mobile-logo-height'] ) . '" ';
    } else {
        echo 'data-m-logo-height="24" ';
    }

    echo 'data-has-menu="' . esc_attr( $has_main_menu ) . '" ';
    echo 'data-has-buttons="' . esc_attr( $using_header_buttons ) . '" ';
    if ( $using_pr_menu == 'true' ) {
        echo 'data-using-pr-menu="' . esc_attr( $using_pr_menu ) . '" ';
    }
    echo 'data-mobile-fixed="' . esc_attr( $mobile_fixed ) . '" ';
    if ( $prepend_top_nav_mobile == '1' ) {
        echo 'data-ptnm="' . esc_attr( $prepend_top_nav_mobile ) . '" ';
    }
    echo 'data-lhe="' . esc_attr( $header_link_hover_effect ) . '" ';
    echo 'data-user-set-bg="' . esc_attr( $user_set_bg ) . '" ';
    echo 'data-format="' . esc_attr( $header_format ) . '" ';
    if( 'centered-menu-bottom-bar' === $header_format ) {
        echo 'data-menu-bottom-bar-align="' . esc_attr( $centered_menu_bottom_bar_align ) . '" ';
    }
    echo 'data-permanent-transparent="' . esc_attr( $perm_trans ) . '" ';
    echo 'data-rm-fixed="' . esc_attr( $header_remove_stickiness ) . '" ';
    echo 'data-header-resize="' . esc_attr( $header_resize ) . '" ';
    echo 'data-megamenu-rt="1" ';

    if ( $woocommerce && ! empty( $nectar_options['enable-cart'] ) && $nectar_options['enable-cart'] == '1' ) {
        echo 'data-cart="true" ';
    } else {
        echo 'data-cart="false" ';
    }

    if ( $disable_effect === 'on' ) {
        echo 'data-transparency-option="0" ';
    } else {
        echo 'data-transparency-option="' . esc_attr( $using_fw_slider ) . '" ';
    }

    echo 'data-box-shadow="' . esc_attr( $header_box_shadow ) . '" ';

    if ( ! empty( $nectar_options['header-resize-on-scroll-shrink-num'] ) ) {
        echo 'data-shrink-num="' . esc_attr( $nectar_options['header-resize-on-scroll-shrink-num'] ) . '" ';
    } else {
        echo 'data-shrink-num="6" ';
    }

    if ( $using_secondary === 'header_with_secondary' ) {
        echo 'data-using-secondary="1" ';
    }

    if ( ! empty( $nectar_options['header-padding'] ) ) {
        echo 'data-padding="' . esc_attr( $nectar_options['header-padding'] ) . '" ';
    } else {
        echo 'data-padding="28" ';
    }

    echo 'data-full-width="' . esc_attr( $full_width_header ) . '"';
    if ( $condense_header_on_scroll == 'true' ) {
        echo ' data-condense="' . esc_attr( $condense_header_on_scroll ) . '"';
    }
    echo ' ' . $transparency_markup;

}

if ( ! function_exists( 'nectar_get_mobile_header_height' ) ) {
    function nectar_get_mobile_header_height() {

        $nectar_options = get_nectar_theme_options();

        // Using image based logo.
        if( ! empty( $nectar_options['use-logo'] ) ) {
            $mobile_logo_height = ( ! empty($nectar_options['mobile-logo-height'])) ? intval($nectar_options['mobile-logo-height']) : 24;
            $mobile_padding_mod = ( $mobile_logo_height < 38 ) ? 20 : 0;
            $mobile_logo_height += $mobile_padding_mod;
        }
        // Using text logo.
        else {
            // Custom size from typography logo line height option.
            if( ! empty($nectar_options['logo_font_family']['line-height']) ) {
                $mobile_logo_height = intval(substr($nectar_options['logo_font_family']['line-height'], 0, -2));
            }
            // Custom size from typography logo font size option.
            else if( ! empty($nectar_options['logo_font_family']['font-size']) ) {
                $mobile_logo_height = intval(substr($nectar_options['logo_font_family']['font-size'], 0, -2));
            }
            // Default size.
            else {
                $mobile_logo_height = 22;
            }

            // Clamp.
            if( $mobile_logo_height > 24 ) {
                $mobile_logo_height = 24;
            }

            // Add text logo margin.
            $mobile_logo_height += 20;

        }

        $mobile_padding = ( NectarThemeManager::$skin === 'material' ) ? 24 : 25;

        return $mobile_logo_height + $mobile_padding;
    }

}

if ( ! function_exists( 'nectar_is_contained_header' ) ) {
    function nectar_is_contained_header( $has_header_builder = null ) {

        // $has_header_builder defaults to the per-request signal (render-time correct).
        // Cached-CSS callers (custom.php) MUST pass the page-agnostic unconditional state,
        // or a regen that runs on a builder page omits contained-header CSS from the
        // globally-shared file. Keep the param.
        if ( null === $has_header_builder ) {
            $has_header_builder = function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template();
        }
        if ( $has_header_builder ) {
            return false;
        }

        $nectar_options = get_nectar_theme_options();

        $using_secondary = ( isset($nectar_options['header_layout']) ) ? $nectar_options['header_layout'] : 'default';
        $header_format = ( isset($nectar_options['header_format']) ) ? $nectar_options['header_format'] : 'default';
        $header_size = (isset($nectar_options['header-size'] ) ) ? $nectar_options['header-size'] : 'default';

        // Options which disabled contained header.
        if( 'header_with_secondary' === $using_secondary ||
            'left-header' === $header_format ||
            'centered-menu-bottom-bar' === $header_format ||
            'contained' !== $header_size ) {
            return false;
        }

        return true;

    }
}

if ( ! function_exists( 'nectar_logo_dimensions' ) ) {
    function nectar_logo_dimensions($type, $src) {

        if( 'width' === $type ) {
            if( isset($src['width']) ) {
                return esc_attr($src['width']);
            }
        }

        else if( 'height' === $type ) {
            if( isset($src['height']) ) {
                return esc_attr($src['height']);
            }
        }

        return '';

    }
}

/**
 * Header navigation logo output
 *
 * @since 8.0
 */
if ( ! function_exists( 'nectar_logo_output' ) ) {

    function nectar_logo_output( $activate_transparency = false, $off_canvas_style = 'slide-out-from-right', $using_mobile_logo = 'false' ) {

        global $nectar_options;
        global $post;

        $force_transparent_header_color = nectar_get_forced_transparent_header_color();

        $nectar_logo_text = apply_filters('nectar_logo_text', get_bloginfo( 'name' ));

        if ( ! empty( $nectar_options['use-logo'] ) ) {

            $dark_default_class = ( empty( $nectar_options['header-starting-logo-dark']['id'] ) && empty( $nectar_options['header-starting-logo-dark']['url'] ) ) ? ' dark-version' : '';

            echo '<img class="stnd skip-lazy' . esc_attr( $dark_default_class ) . '" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['logo']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['logo']) ) . '" alt="' . esc_attr( $nectar_logo_text ) . '" src="' . esc_url( nectar_options_img( $nectar_options['logo'] ) ) . '" />';

             // Mobile only logo.
            if ( $using_mobile_logo === 'true' ) {
                 echo '<img class="mobile-only-logo skip-lazy" alt="' . esc_attr( $nectar_logo_text ) . '" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['mobile-logo']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['mobile-logo']) ) . '" src="' . esc_url( nectar_options_img( $nectar_options['mobile-logo'] ) ) . '" />';
            }

             // Starting logo.
            if ( $activate_transparency == 'true' || $off_canvas_style === 'fullscreen-alt' || $off_canvas_style === 'fullscreen-inline-images' || $force_transparent_header_color === 'dark' ) {

                // Starting mobile only.
                if( $nectar_options['use-logo'] === '1' && ! empty( $nectar_options['header-starting-mobile-only-logo'] ) && ! empty( $nectar_options['header-starting-mobile-only-logo']['url'] ) ) {
                    echo '<img class="starting-logo mobile-only-logo skip-lazy" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['header-starting-mobile-only-logo']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['header-starting-mobile-only-logo']) ) . '"  alt="' . esc_attr( $nectar_logo_text ) . '" src="' . esc_url( nectar_options_img( $nectar_options['header-starting-mobile-only-logo'] ) ) . '" />';
                }
                if( $nectar_options['use-logo'] === '1' && ! empty( $nectar_options['header-starting-mobile-only-logo-dark'] ) && ! empty( $nectar_options['header-starting-mobile-only-logo-dark']['url'] ) ) {
                    echo '<img class="starting-logo dark-version mobile-only-logo skip-lazy" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['header-starting-mobile-only-logo-dark']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['header-starting-mobile-only-logo-dark']) ) . '" alt="' . esc_attr( $nectar_logo_text ) . '" src="' . esc_url( nectar_options_img( $nectar_options['header-starting-mobile-only-logo-dark'] ) ) . '" />';
                }

                if ( ! empty( $nectar_options['header-starting-logo']['id'] ) || ! empty( $nectar_options['header-starting-logo']['url'] ) ) {
                    echo '<img class="starting-logo skip-lazy" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['header-starting-logo']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['header-starting-logo']) ) . '" alt="' . esc_attr( $nectar_logo_text ) . '" src="' . esc_url( nectar_options_img( $nectar_options['header-starting-logo'] ) ) . '" />';
                }

                if ( ! empty( $nectar_options['header-starting-logo-dark']['id'] ) || ! empty( $nectar_options['header-starting-logo-dark']['url'] ) ) {
                    echo '<img class="starting-logo dark-version skip-lazy" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['header-starting-logo-dark']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['header-starting-logo-dark']) ) . '" alt="' . esc_attr( $nectar_logo_text ) . '" src="' . esc_url( nectar_options_img( $nectar_options['header-starting-logo-dark'] ) ) . '" />';
                }
            }

        } else {
            echo apply_filters( 'nectar_logo_text_markup', esc_html( $nectar_logo_text ) );
        }
    }
}

if ( ! function_exists( 'nectar_logo_spacing' ) ) {
    function nectar_logo_spacing() {

        global $nectar_options;

        $logo_class = ( ! empty( $nectar_options['use-logo'] ) && $nectar_options['use-logo'] === '1' ) ? 'true' : 'false';

        echo '<div class="logo-spacing" data-using-image="' . esc_attr($logo_class) . '">';
        if ( ! empty( $nectar_options['use-logo'] ) ) {

             echo '<img class="hidden-logo" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="' . esc_attr( nectar_logo_dimensions('width', $nectar_options['logo']) ) . '" height="' . esc_attr( nectar_logo_dimensions('height', $nectar_options['logo']) ) . '" src="' . esc_url( nectar_options_img( $nectar_options['logo'] ) ) . '" />';

        } else {
            echo get_bloginfo( 'name' ); }

         echo '</div>';
    }
}

/**
 * Check whether JS is enabled ASAP
 *
 * @since 9.0
 */
add_action( 'wp_head', 'nectar_javascript_check' );
if ( ! function_exists( 'nectar_javascript_check' ) ) {
    function nectar_javascript_check() {
         echo '<script type="text/javascript"> var root = document.getElementsByTagName( "html" )[0]; root.setAttribute( "class", "js" ); </script>';
    }
}

/**
 * Determine if a Header Navigation template override should be considered active.
 *
 * In normal frontend renders, the Theme Builder registers the action hook on `wp`,
 * so `has_action('nectar_template__header_navigation')` is reliable.
 * When previewing a `nectar_templates` post directly, the Theme Builder render registration
 * is skipped in admin contexts, so we also detect that singular preview case.
 *
 * @since 2.6.0
 */
if ( ! function_exists( 'nectar_has_header_nav_template' ) ) {
    function nectar_has_header_nav_template(): bool {
        if ( has_action( 'nectar_template__header_navigation' ) ) {
            return true;
        }

        // Template post direct preview: treat header navigation template as active to avoid rendering the default header.
        if ( function_exists( 'is_singular' ) && is_singular( 'nectar_templates' ) ) {
            $post_id = function_exists( 'get_the_ID' ) ? get_the_ID() : 0;
            if ( $post_id && function_exists( 'get_post_meta' ) ) {
                $meta = get_post_meta( $post_id, '_nectar_template_part_options', true );
                if ( is_array( $meta ) && isset( $meta['templatePart'] ) && 'nectar_template__header_navigation' === $meta['templatePart'] ) {
                    return true;
                }
            }
        }

        return false;
    }
}

/**
 * Whether a set of Theme Builder display conditions resolves to "every request".
 *
 * Mirrors the include/exclude semantics of `Render::verify_conditional_display()`:
 * empty conditions display everywhere, an `everywhere` include displays everywhere,
 * while any specific include or any exclude narrows coverage. Conservative by design
 * — anything that *could* narrow coverage returns false so callers fall back to the
 * legacy (non-builder) path, which stays correct on the pages a conditional template
 * doesn't match.
 *
 * Note the exclude case is intentional, NOT a missed optimisation: an
 * "everywhere except post X" template still renders the LEGACY header on post X,
 * so the sole consumer (`nectar_has_unconditional_header_nav_template` → the
 * globally-cached dynamic-CSS gate) MUST return false here to keep the legacy CSS
 * in the cache for post X. Treating it as unconditional would skip that CSS and
 * break post X. The builder pages are kept clean by the `:not([data-header-builder])`
 * selector scoping, not by suppressing the cached CSS.
 *
 * @since 3.0.1
 */
if ( ! function_exists( 'nectar_template_conditions_apply_everywhere' ) ) {
    function nectar_template_conditions_apply_everywhere( $conditions ): bool {
        if ( empty( $conditions ) || ! is_array( $conditions ) ) {
            return true;
        }

        foreach ( $conditions as $condition ) {
            $value = is_array( $condition ) && isset( $condition['condition'] ) ? $condition['condition'] : '';
            if ( ! is_string( $value ) || '' === $value ) {
                // Skip empty/malformed rows, exactly as Render::verify_conditional_display
                // does (`if ( empty($conditional_value) ) continue;`). A condition set
                // that is entirely empty therefore resolves to "display everywhere" in
                // Render too, so treating it as unconditional here is consistent — and
                // it correctly handles a valid `everywhere` include left beside a blank
                // repeater row. (Returning false here would mis-flag that common case.)
                continue;
            }
            $include = is_array( $condition ) && isset( $condition['include'] ) ? $condition['include'] : true;
            if ( false === $include || 'everywhere' !== $value ) {
                return false;
            }
        }

        return true;
    }
}

/**
 * Published Header Navigation templates as [post_id => parsed meta], fetched once
 * per request and shared by the unconditional/page-aware detectors so they don't
 * each issue the same query. Confirms templatePart since the LIKE query can match
 * templates that merely mention the string.
 *
 * @since 3.0.1
 * @return array<int,array>
 */
if ( ! function_exists( 'nectar_get_header_nav_templates_meta' ) ) {
    function nectar_get_header_nav_templates_meta(): array {
        static $cache = null;
        if ( null !== $cache ) {
            return $cache;
        }

        // Return without memoizing if get_posts() isn't loaded yet, so the next call
        // retries rather than caching an empty result for the request.
        if ( ! function_exists( 'get_posts' ) ) {
            return [];
        }

        // NB: deliberately NOT guarding on post_type_exists('nectar_templates'). The
        // Customizer builds its panels (and runs this gating) in its constructor on
        // after_setup_theme — before init, where the CPT isn't registered yet. get_posts()
        // queries by post_type string regardless of registration, so it still finds the
        // templates; a post_type_exists() guard here would silently disable all gating.
        $cache = [];

        $template_ids = get_posts( [
            'post_type' => 'nectar_templates',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'suppress_filters' => true,
            'meta_query' => [
                [
                    'key' => '_nectar_template_part_options',
                    'value' => 'nectar_template__header_navigation',
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        if ( is_array( $template_ids ) ) {
            foreach ( $template_ids as $template_id ) {
                $meta = get_post_meta( $template_id, '_nectar_template_part_options', true );
                if ( is_array( $meta ) && isset( $meta['templatePart'] ) && 'nectar_template__header_navigation' === $meta['templatePart'] ) {
                    $cache[(int) $template_id] = $meta;
                }
            }
        }

        return $cache;
    }
}

/**
 * Whether a published Header Navigation template applies to *every* request.
 *
 * Condition-aware and context-independent: it inspects saved Theme Builder
 * conditions directly instead of the per-request `nectar_template__header_navigation`
 * action. Use it for globally-cached output (dynamic CSS) and Customizer gating,
 * where the per-request `nectar_has_header_nav_template()` signal is either wrong
 * (the cache captures one page's state) or unavailable (admin context). A
 * *conditional* header template returns false here so the legacy header CSS/options
 * stay intact for the pages it doesn't cover.
 *
 * @since 3.0.1
 */
if ( ! function_exists( 'nectar_has_unconditional_header_nav_template' ) ) {
    function nectar_has_unconditional_header_nav_template(): bool {
        static $result = null;
        if ( null !== $result ) {
            return $result;
        }

        $result = false;

        foreach ( nectar_get_header_nav_templates_meta() as $meta ) {
            $conditions = isset( $meta['conditions'] ) && is_array( $meta['conditions'] ) ? $meta['conditions'] : [];
            if ( nectar_template_conditions_apply_everywhere( $conditions ) ) {
                $result = true;
                break;
            }
        }

        return $result;
    }
}

/**
 * Resolve a frontend URL to the minimal page context needed to evaluate Theme
 * Builder display conditions from an admin/Customizer request, where the real
 * conditional tags (is_front_page() etc.) reflect the admin screen rather than
 * the previewed page.
 *
 * Best-effort: covers the singular / front-page / post-type conditions header
 * templates use in practice. Archive, taxonomy, search and role conditions
 * can't be derived from a URL alone and are treated as non-matching upstream.
 *
 * @since 3.0.1
 * @return array{is_front_page:bool, is_posts_page:bool, is_singular:bool, post_id:int, post_type:string}
 */
if ( ! function_exists( 'nectar_resolve_url_page_context' ) ) {
    function nectar_resolve_url_page_context( string $url ): array {
        $context = [
            'is_front_page' => false,
            'is_posts_page' => false,
            'is_singular' => false,
            'post_id' => 0,
            'post_type' => '',
        ];

        $front_page_id = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_on_front' ) : 0;
        $posts_page_id = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_for_posts' ) : 0;

        $apply_front_page = function() use ( &$context, $front_page_id ) {
            $context['is_front_page'] = true;
            if ( $front_page_id ) {
                $context['is_singular'] = true;
                $context['post_id'] = $front_page_id;
                $context['post_type'] = 'page';
            }
        };

        // Strip query/fragment without strtok(), which clobbers PHP's global
        // tokenizer pointer for any caller mid-chain up the stack.
        $clean = '' !== $url ? explode( '?', explode( '#', $url )[0] )[0] : '';

        if ( '' === $clean || untrailingslashit( $clean ) === untrailingslashit( home_url( '/' ) ) ) {
            $apply_front_page();
            return $context;
        }

        // Posts Page (blog index): url_to_postid() returns 0 for it since it's an
        // archive, so detect it by permalink — mirrors Render's is_home() branch.
        if ( $posts_page_id && function_exists( 'get_permalink' ) ) {
            $posts_url = get_permalink( $posts_page_id );
            if ( $posts_url && untrailingslashit( explode( '?', explode( '#', $posts_url )[0] )[0] ) === untrailingslashit( $clean ) ) {
                $context['is_posts_page'] = true;
                $context['post_id'] = $posts_page_id;
                // The Posts Page is a page-type post, so get_post_type() returns 'page'
                // on the blog index at runtime — set it so post_type__page matches as
                // Render::parse_conditional does. (is_singular stays false — is_home().)
                $context['post_type'] = 'page';
                return $context;
            }
        }

        $post_id = function_exists( 'url_to_postid' ) ? (int) url_to_postid( $url ) : 0;
        if ( $post_id > 0 ) {
            $context['is_singular'] = true;
            $context['post_id'] = $post_id;
            $context['post_type'] = (string) get_post_type( $post_id );
            if ( $front_page_id && $front_page_id === $post_id ) {
                $context['is_front_page'] = true;
            }
        }

        return $context;
    }
}

/**
 * Whether a single Theme Builder condition matches a resolved URL context.
 *
 * Mirrors the per-conditional branches of `Render::parse_conditional()` for the
 * URL-resolvable cases (everywhere, front/posts page, specific post, post type,
 * single). Conditions that can't be derived from a URL alone (archive, taxonomy,
 * search, role, logged-in) deliberately return false — erring toward *showing*
 * the legacy options. This is the intended direction: returning true instead
 * would gate the legacy options on every previewed URL for a template scoped to,
 * say, `is_archive` (the evaluator can't confirm the current URL is an archive),
 * re-introducing the over-gating this change exists to fix. The tradeoff is that
 * a template scoped only to an unresolvable condition won't gate its options when
 * previewing a page where the builder is genuinely active — accepted, since those
 * options are inert (not shown) there anyway.
 *
 * @since 3.0.1
 */
if ( ! function_exists( 'nectar_template_condition_matches_context' ) ) {
    function nectar_template_condition_matches_context( string $conditional, $condition, array $context ): bool {
        if ( 'everywhere' === $conditional ) {
            return true;
        }
        if ( 'is_front_page' === $conditional ) {
            return $context['is_front_page'];
        }
        if ( 'is_single' === $conditional ) {
            return $context['is_singular'] && '' !== $context['post_type'] && 'page' !== $context['post_type'];
        }
        if ( 'specific_post' === $conditional ) {
            $selected = 0;
            $post_data = null;
            if ( is_array( $condition ) ) {
                if ( isset( $condition['postData'] ) ) {
                    $post_data = $condition['postData'];
                } else if ( isset( $condition['post_data'] ) ) {
                    $post_data = $condition['post_data'];
                }
            }
            if ( is_array( $post_data ) && isset( $post_data['id'] ) ) {
                $selected = (int) $post_data['id'];
            } else if ( is_object( $post_data ) && isset( $post_data->id ) ) {
                $selected = (int) $post_data->id;
            }
            // Mirror Render::parse_conditional: matches the selected singular post,
            // or the selected page when it's assigned as the Posts Page (is_home()).
            return $selected > 0 && $context['post_id'] === $selected
                && ( $context['is_singular'] || $context['is_posts_page'] );
        }
        if ( 0 === strpos( $conditional, 'single__pt__' ) ) {
            // Mirror Render::parse_conditional, which uses is_single() — false for
            // pages — so a single__pt__page condition never renders on a page.
            $pt = str_replace( 'single__pt__', '', $conditional );
            return $context['is_singular'] && 'page' !== $context['post_type'] && $context['post_type'] === $pt;
        }
        if ( 0 === strpos( $conditional, 'post_type__' ) ) {
            return $context['post_type'] === str_replace( 'post_type__', '', $conditional );
        }

        return false;
    }
}

/**
 * Evaluate a template's conditions/operator against a resolved URL context.
 *
 * Mirrors `Render::verify_conditional_display()`: empty conditions match
 * everywhere, a matched exclude denies, otherwise the caller's operator is
 * applied (AND by default — matching the Render class, whose callers pass `'and'`
 * when the meta key is absent — OR only when `operator` is explicitly `'or'`).
 * Excludes contribute an allowing value to the include set (matching the plugin)
 * and only deny via the matched-exclude short-circuit.
 *
 * @since 3.0.1
 */
if ( ! function_exists( 'nectar_template_conditions_match_context' ) ) {
    function nectar_template_conditions_match_context( $conditions, string $operator, array $context ): bool {
        // Default coverage is "everywhere" (no/empty conditions), mirroring Render.
        $allow = true;

        if ( is_array( $conditions ) && ! empty( $conditions ) ) {
            $conditionals = [];
            $exclude_matched = false;

            foreach ( $conditions as $condition ) {
                $value = is_array( $condition ) && isset( $condition['condition'] ) ? $condition['condition'] : '';
                if ( ! is_string( $value ) || '' === $value ) {
                    continue;
                }
                $include = is_array( $condition ) && isset( $condition['include'] ) ? $condition['include'] : true;
                $match = nectar_template_condition_matches_context( $value, $condition, $context );

                if ( false === $include ) {
                    if ( $match ) {
                        $exclude_matched = true;
                    }
                    $conditionals[] = true;
                } else {
                    $conditionals[] = $match;
                }
            }

            if ( $exclude_matched ) {
                $allow = false;
            } else if ( ! empty( $conditionals ) ) {
                // empty $conditionals (all rows skipped) leaves $allow true — everywhere.
                $allow = in_array( true, $conditionals, true );
                if ( 'and' === $operator && in_array( false, $conditionals, true ) ) {
                    $allow = false;
                }
            }
        }

        // Intentionally NOT applying the salient_global_section_allow_display filter
        // here (unlike Render::verify_conditional_display). This evaluator only runs in
        // admin/Customizer context — never at frontend render time — so a context-aware
        // filter callback (e.g. one returning false during is_admin() to hide global
        // sections from admin previews) would wrongly force the gate off. The Customizer
        // gate is a best-effort mirror of the saved conditions, not the render filter.
        return $allow;
    }
}

/**
 * Whether a published Header Navigation template is active for a given frontend URL.
 *
 * Condition-aware and usable from admin/Customizer requests (it resolves the URL
 * rather than relying on the per-request `nectar_template__header_navigation`
 * action). Used to gate the Customizer header options to the page being previewed.
 *
 * @since 3.0.1
 */
if ( ! function_exists( 'nectar_header_nav_template_active_for_url' ) ) {
    function nectar_header_nav_template_active_for_url( string $url ): bool {
        static $memo = [];
        if ( array_key_exists( $url, $memo ) ) {
            return $memo[$url];
        }

        $active = false;
        $context = nectar_resolve_url_page_context( $url );

        foreach ( nectar_get_header_nav_templates_meta() as $meta ) {
            $conditions = isset( $meta['conditions'] ) && is_array( $meta['conditions'] ) ? $meta['conditions'] : [];
            $operator = isset( $meta['operator'] ) ? $meta['operator'] : 'and';
            if ( nectar_template_conditions_match_context( $conditions, $operator, $context ) ) {
                $active = true;
                break;
            }
        }

        $memo[$url] = $active;
        return $active;
    }
}

/**
 * The page URL currently being previewed in the Customizer.
 *
 * `customize_register` fires in two requests that must agree, or the preview will
 * deactivate controls the pane registered (and vice versa):
 *  - the pane (wp-admin/customize.php?url=...) where `$_REQUEST['url']` holds it;
 *  - the preview iframe render, where there is no `url` param and the current
 *    request *is* the previewed page.
 * It also runs before customize.php calls set_preview_url(), so get_preview_url()
 * isn't reliable yet. Falls back to the front page (the Customizer's default).
 *
 * Shared by the Customizer panels that gate header options so they resolve the
 * same previewed page.
 *
 * @since 3.0.1
 */
if ( ! function_exists( 'nectar_customizer_previewed_url' ) ) {
    function nectar_customizer_previewed_url(): string {
        // Only meaningful during an actual Customizer request. The customizer bootstrap
        // builds its panels from its constructor (not just customize_register), and that
        // file loads on any request type while theme-mod defaults need (re)seeding — so
        // without this guard a stray ?url= on an unrelated frontend/AJAX/REST request
        // would be misread as the previewed page. $GLOBALS['wp_customize'] is only set
        // on real Customizer (pane + preview) requests.
        if ( ! isset( $GLOBALS['wp_customize'] ) || ! is_object( $GLOBALS['wp_customize'] ) ) {
            return '';
        }

        if ( ! empty( $_REQUEST['url'] ) ) {
            return esc_url_raw( wp_unslash( $_REQUEST['url'] ) );
        }

        $is_preview_render = isset( $_GET['customize_changeset_uuid'] ) || isset( $_GET['customize_messenger_channel'] );
        if ( $is_preview_render && ! empty( $_SERVER['REQUEST_URI'] ) ) {
            // Use the canonical scheme/host/port from home_url(), not $_SERVER — behind a
            // reverse proxy or on a non-default port, $_SERVER['HTTP_HOST']/is_ssl() can
            // disagree with home_url(), and url_to_postid() (host-matched against
            // home_url()) would then return 0 for every page.
            $home = wp_parse_url( home_url() );
            $scheme = ( ! empty( $home['scheme'] ) ? $home['scheme'] : ( is_ssl() ? 'https' : 'http' ) ) . '://';
            $host = ! empty( $home['host'] ) ? $home['host'] : '';
            if ( ! empty( $home['port'] ) ) {
                $host .= ':' . $home['port'];
            }
            $url = remove_query_arg(
                [ 'customize_changeset_uuid', 'customize_messenger_channel', 'customize_autosaved', 'customize_theme', 'customize_preview_nonce', 'wp_customize' ],
                $scheme . $host . wp_unslash( $_SERVER['REQUEST_URI'] )
            );
            return esc_url_raw( $url );
        }

        if ( isset( $GLOBALS['wp_customize'] ) && is_object( $GLOBALS['wp_customize'] )
            && method_exists( $GLOBALS['wp_customize'], 'get_preview_url' ) ) {
            return (string) $GLOBALS['wp_customize']->get_preview_url();
        }

        return '';
    }
}

/**
 * Get the OCM style with header builder fallback applied.
 *
 * The "Simple Dropdown" OCM style is not supported when the Header Builder is active.
 * This utility ensures consistent fallback to "slide-out-from-right" across the theme.
 *
 * @since 2.6.0
 *
 * @param string $style The current OCM style. If empty, will be read from theme options.
 * @return string The OCM style with fallback applied if needed.
 */
if ( ! function_exists( 'nectar_ocm_customizer_var_overrides' ) ) {
    /**
     * Generate CSS variable overrides for OCM from customizer values.
     *
     * Outputs inline CSS that overrides the hardcoded defaults in
     * header-builder-vars.css with the customizer color values.
     *
     * @return string CSS rules or empty string.
     */
    function nectar_ocm_customizer_var_overrides(): string {
        if ( ! function_exists( 'get_nectar_theme_options' ) ) {
            return '';
        }

        $opts = get_nectar_theme_options();
        $map = [
            'header-slide-out-widget-area-background-color' => '--nectar-ocm-bg',
            'header-slide-out-widget-area-color' => '--nectar-ocm-text',
            'header-slide-out-widget-area-hover-color' => '--nectar-ocm-text-hover',
            'header-slide-out-widget-area-header-color' => '--nectar-ocm-heading',
            'header-slide-out-widget-area-close-button-bg' => '--nectar-ocm-close-bg',
            'header-slide-out-widget-area-close-button' => '--nectar-ocm-close-icon',
        ];

        $declarations = '';
        foreach ( $map as $option_key => $css_var ) {
            $val = $opts[$option_key] ?? '';
            if ( ! empty( $val ) ) {
                $declarations .= "{$css_var}: " . esc_attr( $val ) . '; ';
            }
        }

        if ( empty( $declarations ) ) {
            return '';
        }

        return '#slide-out-widget-area { ' . $declarations . '}';
    }
}

if ( ! function_exists( 'nectar_get_ocm_style_with_header_builder_fallback' ) ) {
    function nectar_get_ocm_style_with_header_builder_fallback( $style = '' ) {
        // If empty, get from options.
        if ( empty( $style ) ) {
            $nectar_options = get_nectar_theme_options();
            $style = ( ! empty( $nectar_options['header-slide-out-widget-area-style'] ) )
                ? $nectar_options['header-slide-out-widget-area-style']
                : 'slide-out-from-right';
        }

        // "Simple" style is not supported with header builder - fallback to slide-out-from-right.
        if ( nectar_has_header_nav_template() && $style === 'simple' ) {
            $style = 'slide-out-from-right';
        }

        return $style;
    }
}

/**
 * Remove Open Sans from loading twice
 *
 * @since 7.0
 */
if ( ! function_exists( 'nectar_remove_wp_open_sans' ) ) {
    function nectar_remove_wp_open_sans() {
        wp_deregister_style( 'open-sans' );
        wp_register_style( 'open-sans', false );
    }
}
add_action( 'wp_enqueue_scripts', 'nectar_remove_wp_open_sans' );

/**
 * Adds custom JS from redux to head
 *
 * @since 10.1
 */
if ( ! function_exists( 'nectar_add_custom_js_to_head' ) ) {

    function nectar_add_custom_js_to_head() {

        global $nectar_options;

        $nectar_redux_custom_js = '';

        // Check if empty
        if ( ! empty( $nectar_options['google-analytics'] ) ) {
            $nectar_redux_custom_js .= $nectar_options['google-analytics'];
        }

        if( ! empty( $nectar_redux_custom_js ) ) {
            echo nectar_remove_p_tags( $nectar_redux_custom_js ); // WPCS: XSS ok.
        }

    }

}

add_action( 'wp_head', 'nectar_add_custom_js_to_head' );

/**
 * Page transition markup.
 *
 * @since 10.0
 */
if ( ! function_exists( 'nectar_page_trans_markup' ) ) {

    function nectar_page_trans_markup() {

        global $nectar_options;

        $nectar_using_VC_front_end_editor = (isset($_GET['vc_editable'])) ? sanitize_text_field($_GET['vc_editable']) : '';
        $nectar_using_VC_front_end_editor = ($nectar_using_VC_front_end_editor == 'true') ? true : false;

        $ajax_page_loading = ( ! empty( $nectar_options['ajax-page-loading'] ) && $nectar_options['ajax-page-loading'] === '1' ) ? true : false;

        if ( $ajax_page_loading === false || $nectar_using_VC_front_end_editor ) {
            return;
        }

        $page_transition_effect = ( ! empty( $nectar_options['transition-effect'] ) ) ? $nectar_options['transition-effect'] : 'standard';

        $nectar_disable_fade_on_click = ( ! empty( $nectar_options['disable-transition-fade-on-click'] ) ) ? $nectar_options['disable-transition-fade-on-click'] : '0';
        $nectar_loading_image_animation_class = ( ! empty( $nectar_options['loading-image-animation'] ) && ! empty( $nectar_options['loading-image'] ) ) ? esc_html( $nectar_options['loading-image-animation'] ) : null;
        $nectar_disable_transition_on_mobile = ( ! empty( $nectar_options['disable-transition-on-mobile'] ) ) ? $nectar_options['disable-transition-on-mobile'] : '0';

        echo '<div id="ajax-loading-screen" data-disable-mobile="' . esc_attr( $nectar_disable_transition_on_mobile ) . '" data-disable-fade-on-click="' . esc_attr( $nectar_disable_fade_on_click ) . '" data-effect="' . esc_attr( $page_transition_effect ) . '" data-method="standard">';

        if ( $page_transition_effect === 'horizontal_swipe' || $page_transition_effect === 'horizontal_swipe_basic' ) {

                echo '<div class="reveal-1"></div>';
                echo '<div class="reveal-2"></div>';

        } elseif ( $page_transition_effect === 'center_mask_reveal' ) {

             echo '<span class="mask-top"></span>';
             echo '<span class="mask-right"></span>';
             echo '<span class="mask-bottom"></span>';
             echo '<span class="mask-left"></span>';

        } else {

             echo '<div class="loading-icon ' . $nectar_loading_image_animation_class . '">';

             $loading_icon = ( isset( $nectar_options['loading-icon'] ) ) ? $nectar_options['loading-icon'] : 'default';
             $loading_img = ( isset( $nectar_options['loading-image'] ) ) ? nectar_options_img( $nectar_options['loading-image'] ) : null;

            if ( empty( $loading_img ) ) {

                if ( $loading_icon === 'material' ) {

                    echo '<div class="material-icon">
						<svg class="nectar-material-spinner" width="60px" height="60px" viewBox="0 0 60 60">
							<circle stroke-linecap="round" cx="30" cy="30" r="26" fill="none" stroke-width="6"></circle>
				  		</svg>
					</div>';

                } else {

                    echo '<span class="default-skin-loading-icon"></span>';

                }
            } // empty loading img

                echo '</div>';

        } // not swipe or mask reveal

        echo '</div>';

    } // function end

}

global $nectar_options;

function nectar_page_transition_bg_fix() {
    $page_transition_bg = ( ! empty( $nectar_options['transition-bg-color'] ) ) ? $nectar_options['transition-bg-color'] : '#ffffff';
    $page_transition_bg_2 = ( ! empty( $nectar_options['transition-bg-color-2'] ) ) ? $nectar_options['transition-bg-color-2'] : $page_transition_bg;
    $page_transition_effect = ( ! empty( $nectar_options['transition-effect'] ) ) ? $nectar_options['transition-effect'] : 'standard';

    // set html bg color to match preloading screen to avoid white flash in chrome
    if ( $page_transition_effect === 'horizontal_swipe' ) {
        $css = 'html:not(.page-trans-loaded) { background-color: ' . $page_transition_bg_2 . '; }';
    } else {
        $css = 'html:not(.page-trans-loaded) { background-color: ' . $page_transition_bg . '; }';
    }

    wp_add_inline_style( 'main-styles', $css );

}

if ( ! empty( $nectar_options['ajax-page-loading'] ) && $nectar_options['ajax-page-loading'] === '1' ) {
    add_action( 'wp_enqueue_scripts', 'nectar_page_transition_bg_fix' );
}

/**
 * The list of social networks.
 *
 * @since 12.2.0
 */
if( ! function_exists('nectar_get_social_media_list') ) {

    function nectar_get_social_media_list() {

        $social_networks = [

            'twitter' => [
                'icon_class' => 'fa-twitter',
                'icon_code' => '\e60c',
                'icon_type' => 'font-awesome',
            ],
            'x-twitter' => [
                'icon_class' => 'icon-nectar-blocks-x-twitter',
                'icon_code' => '\e918',
                'icon_type' => 'nectar-blocks',
            ],
            'facebook' => [
                'icon_class' => 'fa-facebook',
                'icon_code' => '\e60d',
                'icon_type' => 'font-awesome',
            ],
            'vimeo' => [
                'icon_class' => 'fa-vimeo',
                'icon_code' => '\f27d',
                'icon_type' => 'font-awesome',
            ],
            'pinterest' => [
                'icon_class' => 'fa-pinterest',
                'icon_code' => '\e60b',
                'icon_type' => 'font-awesome',
            ],
            'linkedin' => [
                'icon_class' => 'fa-linkedin',
                'icon_code' => '\e605',
                'icon_type' => 'font-awesome',
            ],
            'youtube' => [
                'icon_class' => 'fa-youtube-play',
                'icon_code' => '\f16a',
                'icon_type' => 'font-awesome',
            ],
            'tumblr' => [
                'icon_class' => 'fa-tumblr',
                'icon_code' => '\f173',
                'icon_type' => 'font-awesome',
            ],
            'dribbble' => [
                'icon_class' => 'fa-dribbble',
                'icon_code' => '\f17d',
                'icon_type' => 'font-awesome',
            ],
            'rss' => [
                'icon_class' => 'fa-rss',
                'icon_code' => '\f09e',
                'icon_type' => 'font-awesome',
            ],
            'github' => [
                'icon_class' => 'fa-github-alt',
                'icon_code' => '\f113',
                'icon_type' => 'font-awesome',
            ],
            'google-plus' => [
                'icon_class' => 'fa-google',
                'icon_code' => '\f1a0',
                'icon_type' => 'font-awesome',
            ],
            'instagram' => [
                'icon_class' => 'fa-instagram',
                'icon_code' => '\f16d',
                'icon_type' => 'font-awesome',
            ],
            'stackexchange' => [
                'icon_class' => 'fa-stack-exchange',
                'icon_code' => '\f18d',
                'icon_type' => 'font-awesome',
            ],
            'soundcloud' => [
                'icon_class' => 'fa-soundcloud',
                'icon_code' => '\f1be',
                'icon_type' => 'font-awesome',
            ],
            'flickr' => [
                'icon_class' => 'fa-flickr',
                'icon_code' => '\f16e',
                'icon_type' => 'font-awesome',
            ],
            'spotify' => [
                'icon_class' => 'icon-nectar-blocks-spotify',
                'icon_code' => '\f1bc',
                'icon_type' => 'nectar-blocks',
            ],
            'vk' => [
                'icon_class' => 'fa-vk',
                'icon_code' => '\f189',
                'icon_type' => 'font-awesome',
            ],
            'vine' => [
                'icon_class' => 'fa-vine',
                'icon_code' => '\f1ca',
                'icon_type' => 'font-awesome',
            ],
            'behance' => [
                'icon_class' => 'fa-behance',
                'icon_code' => '\f1b4',
                'icon_type' => 'font-awesome',
            ],
            'houzz' => [
                'icon_class' => 'fa-houzz',
                'icon_code' => '\e904',
                'icon_type' => 'font-awesome',
            ],
            'yelp' => [
                'icon_class' => 'fa-yelp',
                'icon_code' => '\f1e9',
                'icon_type' => 'font-awesome',
            ],
            'snapchat' => [
                'icon_class' => 'fa-snapchat',
                'icon_code' => '\f2ab',
                'icon_type' => 'font-awesome',
            ],
            'mixcloud' => [
                'icon_class' => 'fa-mixcloud',
                'icon_code' => '\f289',
                'icon_type' => 'font-awesome',
            ],
            'bandcamp' => [
                'icon_class' => 'fa-bandcamp',
                'icon_code' => '\f2d5',
                'icon_type' => 'font-awesome',
            ],
            'tripadvisor' => [
                'icon_class' => 'fa-tripadvisor',
                'icon_code' => '\f262',
                'icon_type' => 'font-awesome',
            ],
            'telegram' => [
                'icon_class' => 'fa-telegram',
                'icon_code' => '\f2c6',
                'icon_type' => 'font-awesome',
            ],
            'slack' => [
                'icon_class' => 'fa-slack',
                'icon_code' => '\f198',
                'icon_type' => 'font-awesome',
            ],
            'medium' => [
                'icon_class' => 'icon-nectar-blocks-medium',
                'icon_code' => '\e914',
                'icon_type' => 'nectar-blocks',
            ],
            'artstation' => [
                'icon_class' => 'icon-nectar-blocks-artstation',
                'icon_code' => '\e90b',
                'icon_type' => 'nectar-blocks',
            ],
            'discord' => [
                'icon_class' => 'icon-nectar-blocks-discord',
                'icon_code' => '\e90c',
                'icon_type' => 'nectar-blocks',
            ],
            'whatsapp' => [
                'icon_class' => 'fa-whatsapp',
                'icon_code' => '\f232',
                'icon_type' => 'font-awesome',
            ],
            'messenger' => [
                'icon_class' => 'icon-nectar-blocks-facebook-messenger',
                'icon_code' => '\e90d',
                'icon_type' => 'nectar-blocks',
            ],
            'tiktok' => [
                'icon_class' => 'icon-nectar-blocks-tiktok',
                'icon_code' => '\e90f',
                'icon_type' => 'nectar-blocks',
            ],
            'twitch' => [
                'icon_class' => 'icon-nectar-blocks-twitch',
                'icon_code' => '\e905',
                'icon_type' => 'nectar-blocks',
            ],
            'applemusic' => [
                'icon_class' => 'icon-nectar-blocks-apple-music',
                'icon_code' => '\e903',
                'icon_type' => 'nectar-blocks',
            ],
            'patreon' => [
                'icon_class' => 'icon-nectar-blocks-patreon',
                'icon_code' => '\e912',
                'icon_type' => 'nectar-blocks',
            ],
            'xing' => [
                'icon_class' => 'fa-xing',
                'icon_code' => '\f168',
                'icon_type' => 'font-awesome',
            ],
            'mastodon' => [
                'icon_class' => 'icon-nectar-blocks-mastodon',
                'icon_code' => '\e917',
                'icon_type' => 'nectar-blocks',
            ],
            'threads' => [
                'icon_class' => 'icon-nectar-blocks-threads',
                'icon_code' => '\e913',
                'icon_type' => 'nectar-blocks',
            ],
            'trustpilot' => [
                'icon_class' => 'icon-nectar-blocks-trustpilot',
                'icon_code' => '\e916',
                'icon_type' => 'nectar-blocks',
            ],
            'phone' => [
                'icon_class' => 'fa-phone',
                'icon_code' => '\f095',
                'icon_type' => 'font-awesome',
            ],
            'email' => [
                'icon_class' => 'fa-envelope',
                'icon_code' => '\f0e0',
                'icon_type' => 'font-awesome',
            ]
        ];

        return $social_networks;

    }

}

/**
 * Outputs social icons in the header navigation.
 *
 * @since 6.0
 */
if ( ! function_exists( 'nectar_header_social_icons' ) ) {

    function nectar_header_social_icons( $location ) {

        global $nectar_options;

        $social_networks = nectar_get_social_media_list();

        if ( $location === 'secondary-nav' ) {
            echo '<ul id="social">';
        }

        foreach ( $social_networks as $network_name => $icon_arr ) {

            $leading_fa = ('font-awesome' === $icon_arr['icon_type']) ? 'fa ' : '';

            if ( $network_name === 'rss' ) {
                if ( ! empty( $nectar_options['use-' . $network_name . '-icon-header'] ) && $nectar_options['use-' . $network_name . '-icon-header'] === '1' ) {
                    $nectar_rss_url_link = ( ! empty( $nectar_options['rss-url'] ) ) ? $nectar_options['rss-url'] : get_bloginfo( 'rss_url' );

                    if( $location !== 'main-nav' ) { echo '<li>'; }
                    echo '<a target="_blank" rel="noopener" href="' . esc_url( $nectar_rss_url_link ) . '"><span class="screen-reader-text">RSS</span><i class="' . esc_attr($leading_fa) . esc_attr($icon_arr['icon_class']) . '" aria-hidden="true"></i> </a>';
                    if( $location !== 'main-nav' ) { echo '</li>'; }

                }
            }

            else {

                $target_attr = ($network_name != 'email' && $network_name != 'phone') ? 'target="_blank" rel="noopener"' : '';

                if ( ! empty( $nectar_options['use-' . $network_name . '-icon-header'] ) && $nectar_options['use-' . $network_name . '-icon-header'] === '1' ) {

                    if( $location !== 'main-nav' ) { echo '<li>'; }
                    if( isset($nectar_options[$network_name . '-url']) ) {
                        echo '<a ' . $target_attr . ' href="' . esc_url( $nectar_options[$network_name . '-url'] ) . '"><span class="screen-reader-text">' . esc_attr($network_name) . '</span><i class="' . esc_attr($leading_fa) . esc_attr($icon_arr['icon_class']) . '" aria-hidden="true"></i> </a>';
                    } else {
                        echo '<a ' . $target_attr . ' href="#"><span class="screen-reader-text">' . esc_attr($network_name) . '</span><i class="' . esc_attr($leading_fa) . esc_attr($icon_arr['icon_class']) . '" aria-hidden="true"></i> </a>';
                    }
                    if( $location !== 'main-nav' ) { echo '</li>'; }

                }

            }

        } // end loop.

        if ( $location === 'secondary-nav' ) {
            echo '</ul>';
        }

    }
}

/**
 * Off canvas menu social icons.
 *
 * @since 1.0
 */
if ( ! function_exists( 'nectar_ocm_add_social' ) ) {
    function nectar_ocm_add_social() {

        global $nectar_options;

        $social_link_arr = [
            'twitter-url',
            'x-twitter-url',
            'facebook-url',
            'vimeo-url',
            'pinterest-url',
            'linkedin-url',
            'youtube-url',
            'tumblr-url',
            'dribbble-url',
            'rss-url',
            'github-url',
            'behance-url',
            'google-plus-url',
            'instagram-url',
            'stackexchange-url',
            'soundcloud-url',
            'flickr-url',
            'spotify-url',
            'vk-url',
            'vine-url',
            'houzz-url',
            'yelp-url',
            'bandcamp-url',
            'tripadvisor-url',
            'mixcloud-url',
            'snapchat-url',
            'telegram-url',
            'slack-url',
            'medium-url',
            'artstation-url',
            'discord-url',
            'mastodon-url',
            'threads-url',
            'trustpilot-url',
            'whatsapp-url',
            'messenger-url',
            'tiktok-url',
            'twitch-url',
            'applemusic-url',
            'patreon-url',
            'xing-url',
            'phone-url',
            'email-url'
        ];
        $social_icon_arr = [
            'fa fa-twitter',
            'icon-nectar-blocks-x-twitter',
            'fa fa-facebook',
            'fa fa-vimeo',
            'fa fa-pinterest',
            'fa fa-linkedin',
            'fa fa-youtube-play',
            'fa fa-tumblr',
            'fa fa-dribbble',
            'fa fa-rss',
            'fa fa-github-alt',
            'fa fa-behance',
            'fa fa-google',
            'fa fa-instagram',
            'fa fa-stack-exchange',
            'fa fa-soundcloud',
            'fa fa-flickr',
            'icon-nectar-blocks-spotify',
            'fa fa-vk',
            'fa-vine',
            'fa fa-houzz',
            'fa-yelp',
            'fa-bandcamp',
            'fa-tripadvisor',
            'fa-mixcloud',
            'fa fa-snapchat',
            'fa fa-telegram',
            'fa fa-slack',
            'fa fa-medium',
            'icon-nectar-blocks-artstation',
            'icon-nectar-blocks-discord',
            'icon-nectar-blocks-mastodon',
            'icon-nectar-blocks-threads',
            'icon-nectar-blocks-trustpilot',
            'fa fa-whatsapp',
            'icon-nectar-blocks-facebook-messenger',
            'icon-nectar-blocks-tiktok',
            'icon-nectar-blocks-twitch',
            'icon-nectar-blocks-apple-music',
            'icon-nectar-blocks-patreon',
            'fa fa-xing',
            'fa fa-phone',
            'fa fa-envelope' ];

        echo '<ul class="off-canvas-social-links">';

        for ( $i = 0; $i < count( $social_link_arr ); $i++ ) {

            if ( ! empty( $nectar_options[$social_link_arr[$i]] ) && strlen( $nectar_options[$social_link_arr[$i]] ) > 1 ) {
                echo '<li><a target="_blank" rel="noopener" href="' . esc_url( $nectar_options[$social_link_arr[$i]] ) . '"><i class="' . esc_attr( $social_icon_arr[$i] ) . '"></i></a></li>';
            }
        }

        echo '</ul>';

    }

}

if( ! function_exists('nectar_ocm_button_markup') ) {
    function nectar_ocm_button_markup() {

        global $nectar_options;

        $theme_skin = NectarThemeManager::$skin;

        // Custom OCM coloring.
        $ocm_menu_btn_bg_color = 'false';
        $full_width_header = ( ! empty( $nectar_options['header-fullwidth'] ) && $nectar_options['header-fullwidth'] === '1' ) ? 'true' : 'false';

        if( isset($nectar_options['header-slide-out-widget-area-menu-btn-bg-color']) &&
            ! empty( $nectar_options['header-slide-out-widget-area-menu-btn-bg-color'] ) ) {

                //// Ascend full width does not support custom OCM coloring.
                $ocm_menu_btn_color_non_compatible = ( 'ascend' === $theme_skin && 'true' === $full_width_header ) ? true : false;

                if( false === $ocm_menu_btn_color_non_compatible ) {
                    $ocm_menu_btn_bg_color = 'true';
                }

        }

        $menu_label = '<span class="screen-reader-text">' . esc_html__('Menu', 'nectar-blocks-theme') . '</span>';
        $menu_label_class = '';

        if( ! empty( $nectar_options['header-menu-label'] ) && $nectar_options['header-menu-label'] === '1' ) {
            $menu_label = '<i class="label">' . esc_html__('Menu', 'nectar-blocks-theme') . '</i>';
            $menu_label_class = ' using-label';
        }

        echo '<li class="slide-out-widget-area-toggle" data-icon-animation="simple-transform" data-custom-color="' . esc_attr($ocm_menu_btn_bg_color) . '">';
            echo '<div> <a href="#slide-out-widget-area" aria-label="' . esc_attr__('Navigation Menu', 'nectar-blocks-theme') . '" aria-expanded="false" role="button" class="closed' . $menu_label_class . '"> ' . $menu_label . '<span aria-hidden="true"> <i class="lines-button x2"> <i class="lines"></i> </i> </span> </a> </div>';
        echo '</li>';
    }
}

/**
 * Output Button links in navigation.
 *
 * @since 9.0
 */
if ( ! function_exists( 'nectar_header_button_items' ) ) {

    function nectar_header_button_items() {
        global $nectar_options;
        global $woocommerce;

        $side_widget_class = nectar_get_ocm_style_with_header_builder_fallback( NectarThemeManager::$ocm_style );
        $header_search = ( ! empty( $nectar_options['header-disable-search'] ) && $nectar_options['header-disable-search'] === '1' ) ? 'false' : 'true';
        $user_account_btn = ( ! empty( $nectar_options['header-account-button'] ) && $nectar_options['header-account-button'] === '1' ) ? 'true' : 'false';
        $user_account_btn_url = ( ! empty( $nectar_options['header-account-button-url'] ) ) ? $nectar_options['header-account-button-url'] : '';
        $header_format = ( function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template() ) ? 'default' : (
            ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default'
        );
        $full_width_header = ( ! empty( $nectar_options['header-fullwidth'] ) && $nectar_options['header-fullwidth'] === '1' ) ? 'true' : 'false';
        $side_widget_area = ( ! empty( $nectar_options['header-slide-out-widget-area'] ) && $header_format != 'left-header' ) ? $nectar_options['header-slide-out-widget-area'] : 'off';

        $user_set_side_widget_area = $side_widget_area;

        // Determine is the header is full width.
        //// Slide out from right hover forces full width.
        if ( $header_format === 'centered-menu-under-logo' ) {
            if ( $side_widget_class === 'slide-out-from-right-hover' && $user_set_side_widget_area === '1' ) {
                $side_widget_class = 'slide-out-from-right';
            }
            $full_width_header = 'false';
        }
        if ( $side_widget_class === 'slide-out-from-right-hover' && $user_set_side_widget_area === '1' ) {
            $full_width_header = 'true';
        }

        // Determine the current theme skin.
        $theme_skin = NectarThemeManager::$skin;

        $menu_label = '<span class="screen-reader-text">' . esc_html__('Menu', 'nectar-blocks-theme') . '</span>';
        $menu_label_class = '';

        if( ! empty( $nectar_options['header-menu-label'] ) && $nectar_options['header-menu-label'] === '1' ) {
            $menu_label = '<i class="label">' . esc_html__('Menu', 'nectar-blocks-theme') . '</i>';
            $menu_label_class = ' using-label';
        }

        $side_widget_area = ( ! empty( $nectar_options['header-slide-out-widget-area'] ) && $header_format !== 'left-header' ) ? $nectar_options['header-slide-out-widget-area'] : 'off';
        $side_widget_area_pos = ( isset( $nectar_options['ocm_btn_position'] ) ) ? esc_html($nectar_options['ocm_btn_position']) : 'default';

        do_action('nectar_before_header_button_list_items');

        if ( $header_search != 'false' ) {
            echo '<li id="search-btn"><div><a href="#search-outer" role="button"><span class="icon-nectar-blocks-search" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('search', 'nectar-blocks-theme') . '</span></a></div> </li>';
        }

        if ( $user_account_btn != 'false' && class_exists( 'WooCommerce' ) && function_exists('wc_get_page_id') ) {
            echo '<li id="nectar-user-account"><div><a href="' . get_permalink( wc_get_page_id( 'myaccount' ) ) . '"><span class="icon-nectar-blocks-m-user" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__('account', 'nectar-blocks-theme') . '</span></a></div> </li>';
        }

        if ( ! empty( $nectar_options['enable-cart'] ) && $nectar_options['enable-cart'] == '1' ) {
            if ( $woocommerce ) {
                echo '<li class="nectar-woo-cart">' . nectar_header_cart_output() . '</li>';
            }
        }

        if ( $side_widget_area === '1' && $side_widget_class !== 'simple' ) {

      if( $side_widget_area_pos != 'left' || $header_format == 'centered-logo-between-menu' || $header_format == 'centered-menu-under-logo') {
        nectar_ocm_button_markup();
      }

        }

    }
}

/**
 * Check if any header buttons are in use.
 *
 * @since 9.0
 */
if ( ! function_exists( 'nectar_header_button_check' ) ) {
    function nectar_header_button_check() {

        global $nectar_options;
        global $woocommerce;

        $header_format = ( function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template() ) ? 'default' : (
            ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default'
        );
        $using_header_cart = ( $woocommerce && ! empty( $nectar_options['enable-cart'] ) && $nectar_options['enable-cart'] === '1' ) ? true : false;
        $user_account_btn = ( ! empty( $nectar_options['header-account-button'] ) && $nectar_options['header-account-button'] === '1' ) ? true : false;
        $header_search = ( ! empty( $nectar_options['header-disable-search'] ) && $nectar_options['header-disable-search'] === '1' ) ? false : true;
        $side_widget_area = ( ! empty( $nectar_options['header-slide-out-widget-area'] ) && $header_format !== 'left-header' && $nectar_options['header-slide-out-widget-area'] === '1' ) ? true : false;
        $side_widget_area_pos = ( isset( $nectar_options['ocm_btn_position'] ) ) ? esc_html($nectar_options['ocm_btn_position']) : 'default';

        if( $side_widget_area_pos == 'left' ) {
            $side_widget_area = false;
        }

        $header_buttons_active = ( $using_header_cart || $user_account_btn || $header_search || $side_widget_area ) ? 'yes' : 'no';

        return $header_buttons_active;
    }
}

if( ! function_exists( 'nectar_meta_viewport' ) ) {
    function nectar_meta_viewport() {

        global $nectar_options;

        if ( isset( $nectar_options['meta_viewport'] ) && 'scalable' === $nectar_options['meta_viewport'] ) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5" />';
        }
        else {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0" />';
        }
    }
}