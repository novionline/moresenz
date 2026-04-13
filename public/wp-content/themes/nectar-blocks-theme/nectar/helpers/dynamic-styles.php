<?php

/**
 * Dynamic CSS related helper functions
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 12.0.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if the first/large element on the page is a full width row to handle the container padding
 *
 * @since 15.5
 */
if (! function_exists('nectar_top_bottom_padding_calc')) {

    function nectar_top_bottom_padding_calc() {

        $padding_css = '';

        // First shortcode is fullwidth.
        if ( nectar_using_top_level_row() || nectar_using_before_content_global_section() ) {
            $padding_css = 'html body[data-header-resize="1"] .container-wrap,
      html body[data-header-format="left-header"][data-header-resize="0"] .container-wrap,
      html body[data-header-resize="0"] .container-wrap,
      body[data-header-format="left-header"][data-header-resize="0"] .container-wrap {
        padding-top: 0;
      }
      .main-content > #breadcrumbs.yoast {
        padding: 20px 0;
      }';

        }

        if( nectar_using_before_content_global_section() ) {

            if( function_exists('is_cart') && is_cart() ||
                function_exists('is_checkout') && is_checkout() ) {
                $padding_css .= '.main-content > .row > .woocommerce {
          padding-top: 40px;
         }';
            }

        }

        // After content global seciton.
        if (has_action('nectar_hook_global_section_after_content')) {
            $padding_css .= 'body[data-bg-header] .container-wrap {
        padding-bottom: 0;
      }
      #pagination {
        margin-bottom: 40px;
      }';

            // WooCommerce.
            if( function_exists('is_cart') && is_cart() ||
                function_exists('is_checkout') && is_checkout() ) {
                $padding_css .= '.main-content > .row > .woocommerce {
           padding-bottom: 40px;
        }';
            }
        }
        if (has_action('nectar_before_blog_loop_end')) {
            $padding_css .= 'body .post-area #pagination {
        margin-top: 0;
      }';
        }

        if (has_action('nectar_hook_before_content_global_section') &&
            function_exists('is_account_page') && is_account_page() ) {
            $padding_css .= '#primary.content-area {
         padding-top: 40px;
      }';
        }

        if( ! empty($padding_css) ) {
            wp_add_inline_style( 'main-styles', $padding_css );
        }

    }

}

add_action( 'wp_enqueue_scripts', 'nectar_top_bottom_padding_calc' );

/**
 * Check if the global section "nectar_hook_before_content_global_section" is active/on the page
 *
 * @since 15.5
 */

if (! function_exists('nectar_using_before_content_global_section')) {

    function nectar_using_before_content_global_section() {

        $using_global_hook_before_content = false;

        if (has_action('nectar_hook_before_content_global_section')) {

            if( function_exists('is_product') && is_product() ) {
                return false;
            }

            if( is_page() ||
                is_single() ||
                function_exists('is_account_page') && is_account_page() ||
                function_exists('is_cart') && is_cart() ||
                function_exists('is_checkout') && is_checkout()) {
                $using_global_hook_before_content = true;
            }

        }

        return $using_global_hook_before_content;

    }

}

/**
 * Helper to output font properties for each font field.
 *
 * @param  string $typography_item Typography array key selector.
 * @param  string $line_height Calculated line height (can differ for each field).
 * @param  array  $nectar_options Array of theme options.
 * @since 10.5
 */
if( ! function_exists('nectar_output_font_props') ) {

    function nectar_output_font_props($typography_item, $line_height, $nectar_options, $font_size = 'output') {

        // Handle the use of !important when needed.
        $important_size_weight = '';
        $important_transform = '';

        if( $typography_item === 'label_font' ||
        $typography_item === 'portfolio_filters_font' ||
        $typography_item === 'portfolio_caption_font' ||
        $typography_item === 'nectar_dropcap_font' ||
        $typography_item === 'nectar_sidebar_footer_headers_font' ||
        $typography_item === 'nectar_woo_shop_product_title_font' ||
        $typography_item === 'nectar_woo_shop_product_secondary_font' ) {
            $important_size_weight = '!important';
        }

        if( $typography_item === 'sidebar_footer_h_font' ||
        $typography_item === 'nectar_sidebar_footer_headers_font' ||
        $typography_item === 'nectar_woo_shop_product_secondary_font' ) {
            $important_transform = '!important';
        }

        $styles = explode('-', $nectar_options[$typography_item . '_style']);

        if( $nectar_options[$typography_item] != '-' ) {
            $font_family = (1 === preg_match('~[0-9]~', $nectar_options[$typography_item])) ? '"' . $nectar_options[$typography_item] . '"' : $nectar_options[$typography_item];
        }

        // Font Family.
        if( $nectar_options[$typography_item] != '-' ) {

            // Handle fonts with quotes.

            if( strrpos($font_family, '"') ) {
                echo 'font-family: ' . htmlspecialchars($font_family, ENT_NOQUOTES) . '; ';
            } else {
                echo 'font-family: ' . esc_attr($font_family) . '; ';
            }

        }
        // Text Transform.
        if( $nectar_options[$typography_item . '_transform'] != '-' ) {
            echo 'text-transform: ' . esc_attr($nectar_options[$typography_item . '_transform']) . $important_transform . '; ';
        }
        // Letter Spacing.
        if( $nectar_options[$typography_item . '_spacing'] != '-' ) {
      $ls_units = ( isset($nectar_options[$typography_item . '_spacing_units']) && in_array($nectar_options[$typography_item . '_spacing_units'], ['px','em']) ) ? $nectar_options[$typography_item . '_spacing_units'] : 'px';
            echo 'letter-spacing: ' . esc_attr(floatval($nectar_options[$typography_item . '_spacing'])) . $ls_units . '; ';
        }
        // Font Size.
        if( $nectar_options[$typography_item . '_size'] != '-' && $font_size !== 'bypass' ) {
            echo 'font-size:' . esc_attr($nectar_options[$typography_item . '_size']) . $important_size_weight . '; ';
        }

        // User Set Line Height.
        if( $nectar_options[$typography_item . '_line_height'] != '-' && $line_height !== 'bypass' ) {
            echo 'line-height:' . esc_attr($nectar_options[$typography_item . '_line_height']) . '; ';
        }
        // Auto Line Height.
        else if( ! empty($line_height) && $line_height !== 'bypass' ) {
            echo 'line-height:' . esc_attr($line_height) . '; ';
        }

        if( ! empty($styles[0]) && $styles[0] == 'regular' ) {
            $styles[0] = '400';
        }

        // Font Weight/Style.
        if( ! empty($styles[0]) && strpos($styles[0], 'italic') === false ) {
            echo 'font-weight:' . esc_attr($styles[0]) . $important_size_weight . '; ';
        }
        else if(! empty( $styles[0]) && strpos($styles[0], '0italic') == true ) {

            $the_weight = explode("i", $styles[0]);

            echo 'font-weight:' . esc_attr($the_weight[0]) . '; ';
            echo 'font-style: italic; ';
        }
        else if( ! empty($styles[0]) ) {
            if(strpos($styles[0], 'italic') !== false) {
                echo 'font-weight: 400; ';
                echo 'font-style: italic; ';
            }
        }
        if( ! empty($styles[1]) ) {
            echo 'font-style:' . esc_attr($styles[1]);
        }

    }

}

/**
 * Helper to calculate the line height for each font field.
 *
 * @param  string $typography_item Typography array key selector.
 * @param  string $line_height
 * @param  array  $nectar_options Array of theme options.
 * @since 10.5
 */
 if( ! function_exists('nectar_font_line_height') ) {

    function nectar_font_line_height($typography_item, $line_height, $nectar_options) {

        // User Set Line Height.
        if( $nectar_options[$typography_item . '_line_height'] != '-' ) {
            $the_line_height = $nectar_options[$typography_item . '_line_height'];
        }
        // Auto Line Height.
        else if( ! empty($line_height) ) {
            $the_line_height = $line_height;
        }
        else {
            $the_line_height = null;
        }

        return $the_line_height;

    }

}

/**
 * CSS Cubic Bezier Easings
 *
 * @since 14.1
 */
if( ! function_exists('nectar_cubic_bezier_easings') ) {

    function nectar_cubic_bezier_easings() {

        return [
            'linear' => '0,0,1,1',
            'swing' => '0.25,0.1,0.25,1',
            'easeInSine' => '0.12, 0, 0.39, 0',
            'easeOutSine' => '0.61, 1, 0.88, 1',
            'easeInOutSine' => '0.37, 0, 0.63, 1',
            'easeInQuad' => '0.11, 0, 0.5, 0',
            'easeOutQuad' => '0.5, 1, 0.89, 1',
            'easeInOutQuad' => '0.45, 0, 0.55, 1',
            'easeInCubic' => '0.32, 0, 0.67, 0',
            'easeOutCubic' => '0.33, 1, 0.68, 1',
            'easeInOutCubic' => '0.65, 0, 0.35, 1',
            'easeInQuart' => '0.5, 0, 0.75, 0',
            'easeOutQuart' => '0.25, 1, 0.5, 1',
            'easeInOutQuart' => '0.76, 0, 0.24, 1',
            'easeInQuint' => '0.64, 0, 0.78, 0',
            'easeOutQuint' => '0.22, 1, 0.36, 1',
            'easeInOutQuint' => '0.83, 0, 0.17, 1',
            'easeInExpo' => '0.8, 0, 0.2, 0',
            'easeOutExpo' => '0.19, 1, 0.22, 1',
            'easeInOutExpo' => '0.87, 0, 0.13, 1',
            'easeInCirc' => '0.6, 0, 0.98, 0',
            'easeOutCirc' => '0, 0.55, 0.45, 1',
            'easeInOutCirc' => '0.85, 0, 0.15, 1',
            'easeInBack' => '0.6, -0.28, 0.735, 0.045',
            'easeOutBack' => '0.175, 0.885, 0.32, 1.275',
            'easeInOutBack' => '0.68, -0.55, 0.265, 1.55',
            'easeInBounce' => '0.01, 0, 0.99, 0',
            'easeOutBounce' => '0.7, 0, 0.7, 1',
            'easeInOutBounce' => '0.65, 0, 0.35, 1',
            'easeInElastic' => '0.04, 0, 0.99, 0',
            'easeOutElastic' => '0.04, 0, 0.99, 0',
            'easeInOutElastic' => '0.04, 0, 0.99, 0',
        ];
    }
}

/**
 * Quick minification helper function
 *
 * @since 4.0
 */

function nectar_quick_minify( $css ) {

    $css = preg_replace( '/\s+/', ' ', $css );

    $css = preg_replace( '/\/\*[^\!](.*?)\*\//', '', $css );

    $css = preg_replace( '/(,|:|;|\{|}) /', '$1', $css );

    $css = preg_replace( '/ (,|;|\{|})/', '$1', $css );

    $css = preg_replace( '/(:| )0\.([0-9]+)(%|em|ex|px|in|cm|mm|pt|pc)/i', '${1}.${2}${3}', $css );

    return trim( $css );

}

/**
 * Writes the dynamic CSS into a file after saving theme options from customizer.
 *
 * @since 9.0.2
 */

add_action( 'wp_enqueue_scripts', 'nectar_dynamic_customizer_css', 9 );
function nectar_dynamic_customizer_css() {

    $customize_preview = isset($_GET['customize_messenger_channel']) ? true : false;
    if( ! $customize_preview && 'true' === get_transient('nectar_dynamic_css_needs_updating') ) {

      // Write theme css.
      nectar_generate_options_css();

      // Write menu css.
      if( class_exists('Nectar_WP_Menu_Style_Manager') ) {
        Nectar_WP_Menu_Style_Manager::get_instance()->write_css();
      }

      // Allowing the dynamic css to generate for the next 2
      // page loads to ensure it's up to date after saving.
      if( 'true' === get_transient('nectar_dynamic_css_regenerated') ) {
        delete_transient('nectar_dynamic_css_needs_updating');
        delete_transient('nectar_dynamic_css_regenerated');
      } else {
        set_transient('nectar_dynamic_css_regenerated', 'true', DAY_IN_SECONDS);
      }

    }

  }

/**
 * Gets the color related dynamic css
 *
 * @since 9.0.2
 */
if (! function_exists('nectar_colors_css_output')) {
    function nectar_colors_css_output() {
        // get_template_part('css/colors');
        // Handled through kirki
    }
}

/**
 * Gets the theme option related dynamic css
 *
 * @since 9.0.2
 */
if (! function_exists('nectar_custom_css_output')) {
    function nectar_custom_css_output() {
        get_template_part('css/custom');
    }
}

/**
 * Gets the font related dynamic css
 *
 * @since 9.0.2
 */
if (! function_exists('nectar_fonts_output')) {
    function nectar_fonts_output() {
        // Temp until we can get the fonts working with kirki
        // get_template_part('css/fonts');
    }
}

/**
 * Writes the dynamic CSS into a file
 * @since 6.0
 * @version 1.0
 * @hooked redux/options/nectar_redux/saved
 */
function nectar_generate_options_css() {

    $nectar_options = get_nectar_theme_options();

    if( true === nectar_dynamic_css_dir_writable() ) {

        $css_dir = get_template_directory() . '/css/';
        ob_start();

        // Include css.
        nectar_custom_css_output();
        Nectar_Dynamic_Fonts()->output_CSS();
        Nectar_Dynamic_Colors()->output_CSS();

        $css = ob_get_clean();
        $css = nectar_quick_minify($css);

        // Write css to file.
        global $wp_filesystem;

        if ( empty($wp_filesystem) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        WP_Filesystem();

        $file_chmod = ( defined('FS_CHMOD_FILE') ) ? FS_CHMOD_FILE : false;

        if ( is_multisite() ) {
            if( ! $wp_filesystem->put_contents($css_dir . 'nectar-blocks-dynamic-styles-multi-id-' . get_current_blog_id() . '.css', $css, $file_chmod)) {
                // Filesystem can not write.
                update_option('nectar_dynamic_css_success', 'false');
            } else {
                update_option('nectar_dynamic_css_success', 'true');
            }
        } else {
            if( ! $wp_filesystem->put_contents($css_dir . 'nectar-blocks-dynamic-styles.css', $css, $file_chmod)) {
                // Filesystem can not write.
                update_option('nectar_dynamic_css_success', 'false');
            } else {
                update_option('nectar_dynamic_css_success', 'true');
            }
        }

        // Update version number for cache busting.
        $random_number = rand( 0, 99999 );
        update_option('nectar_dynamic_css_version', $random_number);

    } // endif CSS dir is writable.
    else {
        // Filesystem can not write.
        update_option('nectar_dynamic_css_success', 'false');
    }

}

/**
 * Enqueues dynamic theme option CSS in head using wp_add_inline_style.
 *
 * @since 10.1
 */
function nectar_enqueue_dynamic_css_non_external() {

    global $nectar_options;

    ob_start();

    // Include css.
    nectar_custom_css_output();
    Nectar_Dynamic_Fonts()->output_CSS();
    Nectar_Dynamic_Colors()->output_CSS();

    $nectar_dynamic_css = ob_get_contents();
    ob_end_clean();

    $nectar_dynamic_css = nectar_quick_minify($nectar_dynamic_css);

    // Theme options custom css.
    $nectar_theme_option_css = ( ! empty($nectar_options["custom-css"]) ) ? $nectar_options["custom-css"] : false;

    // Handle page specific dynamic.
    $nectar_page_specific_dynamic_css = nectar_page_specific_dynamic();

    // Attach styles to current skin stylesheet.
    wp_add_inline_style( 'nectar-blocks-theme-skin', $nectar_dynamic_css );
    wp_add_inline_style( 'nectar-blocks-theme-skin', $nectar_page_specific_dynamic_css );

    if( false !== $nectar_theme_option_css ) {
        wp_add_inline_style( 'nectar-blocks-theme-skin', $nectar_theme_option_css );
    }

}

/**
 * Enqueue the dynamic CSS via stylesheet.
 * @since 6.0
 * @version 10.1
 */
function nectar_enqueue_dynamic_css() {

    global $nectar_options;

    $nectar_theme_version = nectar_get_theme_version();
    $dynamic_css_version_num = ( ! get_option('nectar_dynamic_css_version') ) ? $nectar_theme_version : get_option('nectar_dynamic_css_version');

    if( is_multisite() && file_exists( NECTAR_THEME_DIRECTORY . '/css/nectar-blocks-dynamic-styles-multi-id-' . get_current_blog_id() . '.css' ) ) {
        wp_register_style('dynamic-css', get_template_directory_uri() . '/css/nectar-blocks-dynamic-styles-multi-id-' . get_current_blog_id() . '.css', '', $dynamic_css_version_num);
    } else {
        wp_register_style('dynamic-css', get_template_directory_uri() . '/css/nectar-blocks-dynamic-styles.css', '', $dynamic_css_version_num);
    }

    wp_enqueue_style('dynamic-css');

    // Handle page specific dynamic
    $nectar_page_specific_dynamic_css = nectar_page_specific_dynamic();
    wp_add_inline_style( 'dynamic-css', $nectar_page_specific_dynamic_css );

    // Theme options custom css.
    $nectar_theme_option_css = ( ! empty($nectar_options["custom-css"]) ) ? $nectar_options["custom-css"] : false;
    if( false !== $nectar_theme_option_css ) {
        wp_add_inline_style( 'dynamic-css', $nectar_theme_option_css );
    }

}

// Enqueue dynamic css.
if( true === nectar_dynamic_css_external_bool() && ! is_customize_preview() ) {
    add_action( 'wp_enqueue_scripts', 'nectar_enqueue_dynamic_css', 20 );
}
// Inline styles.
else {
    add_action( 'wp_enqueue_scripts', 'nectar_enqueue_dynamic_css_non_external' );
}

/**
 * Determine whether or not external dynamic css functionality can be used.
 * @since 10.5
 */
function nectar_dynamic_css_external_bool() {

    $nectar_options = get_nectar_theme_options();

    // Prevent external dynamic CSS theme option.
    $nectar_inline_dynamic_css = ( ! empty($nectar_options["force-dynamic-css-inline"]) && $nectar_options["force-dynamic-css-inline"] === '1' ) ? true : false;
    if( $nectar_inline_dynamic_css ) {
        return false;
    }

    // Ensure that there are no problems with the dynamic css.
    $nectar_external_dynamic_success = get_option('nectar_dynamic_css_success');
    if( ! $nectar_external_dynamic_success || 'false' === $nectar_external_dynamic_success ) {
        return false;
    }

    // Multisite enqueue dynamic css.
    if( is_multisite() && file_exists( NECTAR_THEME_DIRECTORY . '/css/nectar-blocks-dynamic-styles-multi-id-' . get_current_blog_id() . '.css' ) ) {
        return true;
    }
    // Non multisite enqueue dynamic css.
    else if( ! is_multisite() && file_exists( NECTAR_THEME_DIRECTORY . '/css/nectar-blocks-dynamic-styles.css' ) ) {
        return true;
    }

    return false;

}

/**
 * Determine whether or not css dir is writable.
 * @since 10.5
 */
function nectar_dynamic_css_dir_writable() {

    global $wp_filesystem;

    if ( empty($wp_filesystem) || ! function_exists( 'request_filesystem_credentials' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    $path = NECTAR_THEME_DIRECTORY . '/css/';

    // Does the fs have direct access?
    if( get_filesystem_method([], $path) === "direct" ) {
        return true;
    }

    // Also check for stored credentials.
    if ( ! function_exists( 'submit_button' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/template.php' );
    }

    ob_start();
    $fs_stored_credentials = request_filesystem_credentials('', '', false, false, null);
    ob_end_clean();

    if ( $fs_stored_credentials && WP_Filesystem( $fs_stored_credentials ) ) {
        return true;
    }

    return false;

}

/**
 * Checks if users has updated the theme.
 *
 * Automatically regenerates the external dynamic css upon updating theme.
 * Refreshes the TGM plugin notice.
 *
 * @since 10.5
 */
add_action( 'shutdown', 'nectar_update_external_dynamic_css' );

function nectar_update_external_dynamic_css() {

    global $nectar_options;

    $nectar_current_version = nectar_get_theme_version();
    $nectar_stored_version = ( ! get_option('nectar_stored_version') ) ? 0 : sanitize_text_field(get_option('nectar_stored_version'));

    // If the version has switched, rgenerate dynamic css. Verify if admin since requesting fs creds.
    if( $nectar_current_version != $nectar_stored_version && current_user_can('switch_themes') ) {
        update_option('nectar_stored_version', $nectar_current_version);
        nectar_generate_options_css();
    }

}

/**
 * Regenerates dynamic styles when new plugins are activated.
 */
if ( ! function_exists('nectar_on_new_plugin_activation') ) {
    function nectar_on_new_plugin_activation($plugin, $network_activation) {
        set_transient( 'nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);
    }
}

add_action('activated_plugin', 'nectar_on_new_plugin_activation', 10, 2);

/**
 * Regenerates dynamic styles when theme is changed.
 */
if ( ! function_exists('nectar_on_new_theme_activation') ) {
  function nectar_on_new_theme_activation($old_name, $old_theme) {
      set_transient( 'nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);
  }
}
add_action('after_switch_theme', 'nectar_on_new_theme_activation', 10, 2);

/**
 * Automatically syncs child theme options with parent theme options.
 * @see https://core.trac.wordpress.org/ticket/27177
 */
if( ! function_exists('nectar_sync_child_theme_options') ) {
  function nectar_sync_child_theme_options() {
    if ( is_child_theme() ) {

      $child_mods = get_theme_mods();
      $mods = get_option( 'theme_mods_' . get_option( 'template' ) );

      /*
        The child mods will likely never be 0 since other mods are added by WP before this fires,
        such as nav locations, custom CSS etc. A threshold of 10 should suffice to verify that our mods
        are not set yet.
      */
      if ( false !== $mods && count($child_mods) < 10 ) {
        foreach ( (array) $mods as $mod => $value ) {

          if ( 'sidebars_widgets' !== $mod ) {
            set_theme_mod( $mod, $value );
          }
        }
        set_transient( 'nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);

      } // parent mods exist
    } // is child theme
  }
}
add_action('after_switch_theme', 'nectar_sync_child_theme_options');

/**
 * Generates all dynamic CSS that can change based on the
 * page rather than global theme option settings alone.
 *
 * @since 6.0
 * @version 1.0
 */
if (! function_exists('nectar_page_specific_dynamic')) {

    function nectar_page_specific_dynamic() {

         ob_start();

         global $post;
         global $nectar_options;

         $theme_skin = NectarThemeManager::$skin;

         // PAGE HEADER
         $blog_header_type = (! empty($nectar_options['blog_header_type'])) ? $nectar_options['blog_header_type'] : 'default_minimal';
         $page_header_fullscreen = get_post_meta($post->ID, '_nectar_header_fullscreen', true);
         $page_header_box_roll = get_post_meta($post->ID, '_nectar_header_box_roll', true);
         $mobile_logo_height = (! empty($nectar_options['use-logo']) && ! empty($nectar_options['mobile-logo-height'])) ? intval($nectar_options['mobile-logo-height']) : 24;

                 $global_post_hide_title_vis = false;

                  // Global disable post titles.
                    if ( is_page() && class_exists('Nectar\Global_Settings\Nectar_Plugin_Options') ) {
            $nb_plugin_options = \Nectar\Global_Settings\Nectar_Plugin_Options::get_options();
                        $global_post_hide_title_vis = $nb_plugin_options['shouldHideTitleDefault'];
                    }

        //// Determine if header transparent effect is active.
        if( ! empty($nectar_options['transparent-header']) &&
            $nectar_options['transparent-header'] == '1' || nectar_is_contained_header()) {
            $activate_transparency = nectar_using_page_header($post->ID);
        } else {
            $activate_transparency = false;
        }
        $trans_header = (! empty($nectar_options['transparent-header']) && $nectar_options['transparent-header'] == '1' ) ? $nectar_options['transparent-header'] : 'false';
        if( nectar_is_contained_header() ) {
            $trans_header = true;
        }
        $perm_transparency = (! empty($nectar_options['header-permanent-transparent']) && $trans_header != 'false' && $activate_transparency == 'true') ? $nectar_options['header-permanent-transparent'] : false;

         //// Coloring.
         global $woocommerce;
         if( $woocommerce && version_compare( $woocommerce->version, "3.0", ">=" ) ) {

             if(is_shop() || is_product_category() || is_product_tag()) {
                 $font_color = get_post_meta(wc_get_page_id('shop'), '_nectar_header_font_color', true);
             } else {
                 $font_color = get_post_meta($post->ID, '_nectar_header_font_color', true);
             }

         }
         else {
             $font_color = get_post_meta($post->ID, '_nectar_header_font_color', true);
         }

         // header space growth for nectar_hook_before_secondary_header asap
         if( has_action('nectar_hook_before_secondary_header') && ! nectar_is_contained_header() ) {
            echo '
      :root {
        --before_secondary_header_height: 0px;
      }
      #nectar-nav-spacer {
        margin-bottom: var(--before_secondary_header_height);
      }';
        }

         //// Default minimal blog header.
         $default_minimal_text_color = (! empty($nectar_options['default_minimal_text_color'])) ? $nectar_options['default_minimal_text_color'] : false;
         if( 'default_minimal' === $blog_header_type &&
              is_singular('post') &&
              false !== $default_minimal_text_color &&
              empty($font_color) ) {
             $font_color = $default_minimal_text_color;
         }

         $blog_post_type_list = ['post'];
        if( has_filter('nectar_metabox_post_types_post_header') ) {
          $blog_post_type_list = apply_filters('nectar_metabox_post_types_post_header', $blog_post_type_list);
        }
        $on_blog_post_type = (isset($post->post_type) && in_array($post->post_type, $blog_post_type_list) && is_single()) ? true : false;

        // When filter is enabled to use post header on CPT, there needs to be container-wrap padding for content below.
        if ( in_array( $blog_header_type, ['default_minimal','fullscreen', 'default']) ) {
            if ( (isset($post->post_type) && $post->post_type !== 'post' && in_array($post->post_type, $blog_post_type_list) && is_single()) ) {
                echo 'body.single[data-bg-header="true"] .container-wrap {
          padding-top: var(--nectar-blog-section-spacing)!important;
        }';
            }
        }

        // Meta border/spacing when specific meta items are removed
        if ( in_array( $blog_header_type, ['default_minimal','fullscreen', 'default']) && $on_blog_post_type ) {

            $rm_sp_date = (! empty($nectar_options['blog_remove_single_date'])) ? $nectar_options['blog_remove_single_date'] : '0';
            $rm_sp_author = (! empty($nectar_options['blog_remove_single_author'])) ? $nectar_options['blog_remove_single_author'] : '0';
            $rm_sp_comment_num = (! empty($nectar_options['blog_remove_single_comment_number'])) ? $nectar_options['blog_remove_single_comment_number'] : '0';
            $rm_sp_est_reading = (! empty($nectar_options['blog_remove_single_reading_dur'])) ? $nectar_options['blog_remove_single_reading_dur'] : '0';

            if( $rm_sp_est_reading !== '1' &&
                ($rm_sp_comment_num !== '1' || $rm_sp_author !== '1' || $rm_sp_date !== '1') ) {

                    if ( $blog_header_type === 'default_minimal' ) {
                        echo '#nectar-content-wrap .blog-title #single-below-header > span {
              padding: 0 20px 0 20px;
            }';
                    }

            }
        }

         if( in_array( $blog_header_type, ['default_minimal','fullscreen']) ) {

            if( is_singular('post') || $on_blog_post_type) {

                echo '
        #page-header-bg[data-post-hs="default_minimal"] .inner-wrap{
          text-align:center
        }
        #page-header-bg[data-post-hs="default_minimal"] .inner-wrap >a,
        .material #page-header-bg.fullscreen-header .inner-wrap >a{
          color:#fff;
          font-weight: 600;
          border: var(--nectar-border-thickness) solid rgba(255,255,255,0.4);
          padding:4px 10px;
          margin:5px 6px 0px 5px;
          display:inline-block;
          transition:all 0.2s ease;
          -webkit-transition:all 0.2s ease;
          font-size:14px;
          line-height:18px
        }
        body.material #page-header-bg.fullscreen-header .inner-wrap >a{
        margin-bottom: 15px;
        }

        body.material #page-header-bg.fullscreen-header .inner-wrap >a {
          border: none;
          padding: 6px 10px
        }
        body[data-button-style^="rounded"] #page-header-bg[data-post-hs="default_minimal"] .inner-wrap >a,
        body[data-button-style^="rounded"].material #page-header-bg.fullscreen-header .inner-wrap >a {
          border-radius:100px
        }

        body.single [data-post-hs="default_minimal"] #single-below-header span,
        body.single .heading-title[data-header-style="default_minimal"] #single-below-header span {
          line-height: 14px;
        }

        #page-header-bg[data-post-hs="default_minimal"] #single-below-header{
          text-align:center;
          position:relative;
          z-index:100
        }
        #page-header-bg[data-post-hs="default_minimal"] #single-below-header span{
          float:none;
          display:inline-block
        }
        #page-header-bg[data-post-hs="default_minimal"] .inner-wrap >a:hover,
        #page-header-bg[data-post-hs="default_minimal"] .inner-wrap >a:focus{
          border-color:transparent
        }
        #page-header-bg.fullscreen-header .avatar,
        #page-header-bg[data-post-hs="default_minimal"] .avatar{
          border-radius:100%
        }
        #page-header-bg.fullscreen-header .meta-author span,
        #page-header-bg[data-post-hs="default_minimal"] .meta-author span{
          display:block
        }
        #page-header-bg.fullscreen-header .meta-author img{
          margin-bottom:0;
          height:50px;
          width:auto
        }
        #page-header-bg[data-post-hs="default_minimal"] .meta-author img{
          margin-bottom:0;
          height:40px;
          width:auto
        }
        #page-header-bg[data-post-hs="default_minimal"] .author-section{
          position:absolute;
          bottom:30px
        }
        #page-header-bg.fullscreen-header .meta-author,
        #page-header-bg[data-post-hs="default_minimal"] .meta-author{
          font-size:18px
        }
        #page-header-bg.fullscreen-header .author-section .meta-date,
        #page-header-bg[data-post-hs="default_minimal"] .author-section .meta-date{
          font-size:12px;
          color:rgba(255,255,255,0.8)
        }
        #page-header-bg.fullscreen-header .author-section .meta-date i{
          font-size:12px
        }
        #page-header-bg[data-post-hs="default_minimal"] .author-section .meta-date i{
          font-size:11px;
          line-height:14px
        }
        #page-header-bg[data-post-hs="default_minimal"] .author-section .avatar-post-info{
          position:relative;
          top:-5px
        }
        #page-header-bg.fullscreen-header .author-section a,
        #page-header-bg[data-post-hs="default_minimal"] .author-section a{
          display:block;
          margin-bottom:-2px
        }
        #page-header-bg[data-post-hs="default_minimal"] .author-section a{
          font-size:14px;
          line-height:14px
        }
        #page-header-bg.fullscreen-header .author-section a:hover,
        #page-header-bg[data-post-hs="default_minimal"] .author-section a:hover{
          color:rgba(255,255,255,0.85)!important
        }
        #page-header-bg.fullscreen-header .author-section,
        #page-header-bg[data-post-hs="default_minimal"] .author-section{
          width:100%;
          z-index:10;
          text-align:center
        }
        #page-header-bg.fullscreen-header .author-section {
          margin-top: 25px;
        }
        #page-header-bg.fullscreen-header .author-section span,
        #page-header-bg[data-post-hs="default_minimal"] .author-section span{
          padding-left:0;
          line-height:20px;
          font-size:20px
        }
        #page-header-bg.fullscreen-header .author-section .avatar-post-info,
        #page-header-bg[data-post-hs="default_minimal"] .author-section .avatar-post-info{
          margin-left:10px
        }
        #page-header-bg.fullscreen-header .author-section .avatar-post-info,
        #page-header-bg.fullscreen-header .author-section .meta-author,
        #page-header-bg[data-post-hs="default_minimal"] .author-section .avatar-post-info,
        #page-header-bg[data-post-hs="default_minimal"] .author-section .meta-author{
          text-align:left;
          display:inline-block;
          top:9px
        }



        @media only screen and (min-width : 690px) and (max-width: 1024px) {

        body.single-post #page-header-bg[data-post-hs="default_minimal"] {
          padding-top: 10%;
          padding-bottom: 10%;
        }

        }


        @media only screen and (max-width : 690px) {

          #nectar-content-wrap #page-header-bg[data-post-hs="default_minimal"] #single-below-header span:not(.rich-snippet-hidden),
          #nectar-content-wrap .row.heading-title[data-header-style="default_minimal"] .col.section-title span.meta-category  {
          display: inline-block;
          }
          .container-wrap[data-remove-post-comment-number="0"][data-remove-post-author="0"][data-remove-post-date="0"] .heading-title[data-header-style="default_minimal"] #single-below-header > span,
          #page-header-bg[data-post-hs="default_minimal"] .span_6[data-remove-post-comment-number="0"][data-remove-post-author="0"][data-remove-post-date="0"] #single-below-header > span {
          padding: 0 8px;
          }
          .container-wrap[data-remove-post-comment-number="0"][data-remove-post-author="0"][data-remove-post-date="0"] .heading-title[data-header-style="default_minimal"] #single-below-header span,
          #page-header-bg[data-post-hs="default_minimal"] .span_6[data-remove-post-comment-number="0"][data-remove-post-author="0"][data-remove-post-date="0"] #single-below-header span {
          font-size: 13px;
          line-height: 10px;
          }

          .material #page-header-bg.fullscreen-header .author-section {
            margin-top: 5px;
          }
          #page-header-bg.fullscreen-header .author-section {
            bottom: 20px;
          }

          #page-header-bg.fullscreen-header .author-section .meta-date:not(.updated) {
            margin-top: -4px;
            display: block;
          }

          #page-header-bg.fullscreen-header .author-section .avatar-post-info {
            margin: 10px 0 0 0;
          }

        }';
            }

         }

    //// Featured media under heading.
    if ( is_single() && 'image_under' === $blog_header_type || is_singular('nectar_portfolio') ) {

      $aspect_ratio = ( isset($nectar_options['blog_header_aspect_ratio']) ) ? $nectar_options['blog_header_aspect_ratio'] : '56.25';
      $image_under_align = ( isset($nectar_options['blog_header_image_under_align']) ) ? $nectar_options['blog_header_image_under_align'] : 'left';
      $image_under_author_style = ( isset($nectar_options['blog_header_image_under_author_style']) ) ? $nectar_options['blog_header_image_under_author_style'] : 'default';
      $image_under_roundness = ( isset($nectar_options['blog_header_image_under_border_radius']) ) ? $nectar_options['blog_header_image_under_border_radius'] : '0';
      $category_display = ( isset($nectar_options['blog_header_category_display']) ) ? $nectar_options['blog_header_category_display'] : 'default';

      if ( is_singular('nectar_portfolio') ) {
        $aspect_ratio = ( isset($nectar_options['portfolio_header_aspect_ratio']) ) ? $nectar_options['portfolio_header_aspect_ratio'] : '56.25';
        $image_under_roundness = ( isset($nectar_options['portfolio_header_image_under_border_radius']) ) ? $nectar_options['portfolio_header_image_under_border_radius'] : '0';
        $category_display = 'default';
      }

      if ( $category_display === 'none' ) {
        echo '.featured-media-under-header h1 {
           margin: 0 0 max(min(0.25em,25px),15px) 0;
        }';
      } else {
        echo '.featured-media-under-header h1 {
          margin: max(min(0.35em,35px),20px) 0 max(min(0.25em,25px),15px) 0;
        }';
      }

      echo '
      .single.single-post .container-wrap {
        padding-top: 0;
      }

      .main-content .featured-media-under-header {
        padding-top: 65px;
    margin-bottom: 65px;
      }
      .featured-media-under-header__featured-media:not([data-has-img="false"]) {
        margin-top: 65px;
      }

    @media only screen and (min-width : 1px) and (max-width: 1024px) {
    .main-content .featured-media-under-header {
      padding-top: 40px;
      margin-bottom: 40px;
    }
    .featured-media-under-header__featured-media:not([data-has-img="false"]) {
      margin-top: 40px;
    }
    }

      .featured-media-under-header__featured-media:not([data-format="video"]):not([data-format="audio"]):not([data-has-img="false"]) {
        overflow: hidden;
        position: relative;
        padding-bottom: ' . esc_attr($aspect_ratio) . '%;
    }
    .featured-media-under-header__meta-wrap {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    }
    .featured-media-under-header__meta-wrap .nectar-font-label {
    line-height: 1.2;
    color: inherit;
    }
    .featured-media-under-header__meta-wrap .meta-author {
    display: inline-flex;
    align-items: center;
    }
    .featured-media-under-header__meta-wrap .meta-author img {
    margin-right: 8px;
    width: 28px;
    border-radius: 100px;
    }
      .featured-media-under-header__featured-media .post-featured-img {
        display: block;
        line-height: 0;
        top: auto;
        bottom: 0;
      }
    .featured-media-under-header__featured-media[data-n-parallax-bg="true"] .post-featured-img {
    height: calc(100% + 75px);
    }
    .featured-media-under-header__featured-media .post-featured-img img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    }
      @media only screen and (max-width: 690px) {
        .featured-media-under-header__featured-media[data-n-parallax-bg="true"] .post-featured-img {
          height: calc(100% + 45px);
        }
        .featured-media-under-header__meta-wrap {
          font-size: 14px;
        }
      }
      .featured-media-under-header__featured-media[data-align="center"] .post-featured-img img {
    object-position: center;
      }
      .featured-media-under-header__featured-media[data-align="bottom"] .post-featured-img img {
        object-position: bottom;
      }

      .featured-media-under-header__cat-wrap .meta-category a {
        line-height: 1;
        padding: 7px 15px;
        margin-right: 15px;
    display: inline-block;
      }
      .featured-media-under-header__cat-wrap .meta-category a:not(:hover) {
        background-color: rgba(0,0,0,0.05);
      }
      .featured-media-under-header__cat-wrap .meta-category a:hover {
        color: #fff;
      }

      .featured-media-under-header__meta-wrap a,
      .featured-media-under-header__cat-wrap a {
        color: inherit;
      }

      .featured-media-under-header__meta-wrap > span:not(:first-child):not(.rich-snippet-hidden):before {
          content: "·";
          padding: 0 0.5em;
      }
    .featured-media-under-header__excerpt {
    margin: 0 0 20px 0;
    }

      @media only screen and (min-width: 691px) {
        [data-animate="fade_in"] .featured-media-under-header__cat-wrap,
        [data-animate="fade_in"].featured-media-under-header .entry-title,
        [data-animate="fade_in"] .featured-media-under-header__meta-wrap,
        [data-animate="fade_in"] .featured-media-under-header__featured-media,
    [data-animate="fade_in"] .featured-media-under-header__excerpt,
        [data-animate="fade_in"].featured-media-under-header + .nectar-blocks__post-section .post-area {
          opacity: 0;
          transform: translateY(50px);
          animation: nectar_featured_media_load 1s cubic-bezier(0.25,1,0.5,1) forwards;
        }
        [data-animate="fade_in"] .featured-media-under-header__cat-wrap { animation-delay: 0.1s; }
        [data-animate="fade_in"].featured-media-under-header .entry-title { animation-delay: 0.2s; }
    [data-animate="fade_in"] .featured-media-under-header__excerpt { animation-delay: 0.3s; }
        [data-animate="fade_in"] .featured-media-under-header__meta-wrap { animation-delay: 0.3s; }
        [data-animate="fade_in"] .featured-media-under-header__featured-media { animation-delay: 0.4s; }
        [data-animate="fade_in"].featured-media-under-header + .nectar-blocks__post-section .post-area { animation-delay: 0.5s; }
      }
      @keyframes nectar_featured_media_load {
        0% {
          transform: translateY(50px);
          opacity: 0;
        }
        100% {
          transform: translateY(0px);
          opacity: 1;
        }
      }
    ';

      // Roundness.
      if( $image_under_roundness !== '0' ) {
        echo '.featured-media-under-header__featured-media, .blog_next_prev_buttons[data-post-header-style="image_under"]:not(.full-width-content) .parallax-layer-wrap {
      border-radius: ' . esc_attr($image_under_roundness) . 'px;
    }';
      }

      // Align.
      if( $image_under_align === 'center' ) {
        echo '.featured-media-under-header__content {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      max-width: 1000px;
      margin: 0 auto;
      }
      @media only screen and (min-width: 691px) {
      .featured-media-under-header__excerpt {
        max-width: max(75%, 800px);
      }
      }';
      }

      // Author layouts.
      if( 'large' === $image_under_author_style ) {
        echo '
    .featured-media-under-header__meta-wrap .meta-author img {
      margin-right: 15px;
      width: 50px;
    }
    @media only screen and (max-width: 690px) {
      width: 40px;
    }
    .featured-media-under-header__meta-wrap .meta-author > span {
      text-align: left;
      line-height: 1.5;
    }
    .featured-media-under-header__meta-wrap .meta-author > span span {
      display: block;
    }
    .featured-media-under-header__meta-wrap  .meta-author .meta-secondary {
      display: flex;
      align-items: center;
      margin-top: 3px;
      gap: 7px;
    }
    .featured-media-under-header__meta-wrap .meta-date,
    .featured-media-under-header__meta-wrap .meta-reading-time {
      font-size: max(0.85em, 14px);
    }';
      }

      // Social.
      $blog_social_style = ( get_option( 'salient_social_button_style' ) ) ? get_option( 'salient_social_button_style' ) : 'fixed';

      if( function_exists('nectar_social_sharing_output') && 'default' === $blog_social_style ) {
        echo '

    .single .post-content {
      display: flex;
      justify-content: center;
    }

    @media only screen and (min-width: 1025px) {

      html body {
        overflow: visible;
      }

      .single .post .content-inner {
        padding-bottom: 0;
      }
      .single .post .content-inner .wpb_row:not(.full-width-content):last-child {
        margin-bottom: 0;
      }

      body:not([data-header-format="left-header"]) .post-area.span_12 .nectar-social.vertical {
        margin-left: -80px;
      }
      body[data-header-format="left-header"] .post-area.span_12 .post-content {
        padding-right: 80px;
      }

      .nectar-social.vertical .nectar-social-inner {
        position: sticky;
        top: var(--nectar-sticky-top-distance);
        margin-right: 40px;
      }
      body:not(.ascend) #author-bio {
        margin-top: 60px;
      }
    }

    @media only screen and (max-width: 1024px) {
      .nectar-social.vertical .nectar-social-inner {
        display: flex;
        margin-bottom: 20px;
      }
      .nectar-social.vertical .nectar-social-inner a {
        margin-right: 15px;
      }
      .single .post-content {
        flex-direction: column-reverse;
      }
    }


    .ascend .featured-media-under-header + .row {
      margin-bottom: 60px;
    }

    .nectar-social.vertical .nectar-social-inner a {
      height: 46px;
      width: 46px;
      line-height: 46px;
      text-align: center;
      margin-bottom: 15px;
      display: block;
      color: inherit;
      position: relative;
      transition: color 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
      border-radius: 100px;
      border: 1px solid rgba(0,0,0,0.1);
    }
    .nectar-social.vertical .nectar-social-inner a:hover {
      border: 1px solid rgba(0,0,0,0);
      color: #fff;
    }

    .nectar-social.vertical .nectar-social-inner a i {
      font-size: 16px;
      height: auto;
      color: inherit;
    }';
      }

     }

         //// Page header fullscreen
    $active_fullscreen_header = false;

        if( 'on' === $page_header_fullscreen ||
            'on' === $page_header_box_roll ||
             (is_single() && 'fullscreen' === $blog_header_type) ) {

            $active_fullscreen_header = true;

            echo '#page-header-bg.fullscreen-header,
      #page-header-wrap.fullscreen-header{
        width:100%;
        position:relative;
        transition:none;
        -webkit-transition:none;
        z-index:2
      }

      #page-header-wrap.fullscreen-header{
        background-color:#2b2b2b
      }
      #page-header-bg.fullscreen-header .span_6{
        opacity:1
      }
      #page-header-bg.fullscreen-header[data-alignment-v="middle"] .span_6{
        top:50%!important
      }

      .default-blog-title.fullscreen-header{
        position:relative
      }

      @media only screen and (min-width : 1px) and (max-width: 1024px) {
        #page-header-bg[data-parallax="1"][data-alignment-v="middle"].fullscreen-header .span_6 {
          -webkit-transform: translateY(-50%)!important;
          transform: translateY(-50%)!important;
        }

        #page-header-bg[data-parallax="1"][data-alignment-v="middle"].fullscreen-header .nectar-particles .span_6 {
          -webkit-transform: none!important;
          transform: none!important;
        }

        #page-header-bg.fullscreen-header .row {
          top: 0!important;
        }
       }';

             if( 'material' === $theme_skin ) {
                echo '
        body.material #page-header-bg.fullscreen-header .inner-wrap >a:hover {
          box-shadow: 0 10px 24px rgba(0,0,0,0.15);
        }
        #page-header-bg.fullscreen-header .author-section .meta-category {
          display: block;
          }
          #page-header-bg.fullscreen-header .author-section .meta-category a,
          #page-header-bg.fullscreen-header .author-section,
          #page-header-bg.fullscreen-header .meta-author img {
          display: inline-block
          }
          #page-header-bg h1 {
          padding-top: 5px;
          padding-bottom: 5px
          }
          .single-post #page-header-bg.fullscreen-header h1 {
          margin: 0 auto;
          }
          #page-header-bg.fullscreen-header .author-section {
          width: auto
          }
          #page-header-bg.fullscreen-header .author-section .avatar-post-info,
          #page-header-bg.fullscreen-header .author-section .meta-author {
          text-align: center
          }
          #page-header-bg.fullscreen-header .author-section .avatar-post-info {
          margin-top: 13px;
          margin-left: 0
          }
          #page-header-bg.fullscreen-header .author-section .meta-author {
          top: 0
          }
          #page-header-bg.fullscreen-header .author-section {
          margin-top: 25px
          }
          #page-header-bg.fullscreen-header .author-section .meta-author {
          display: block;
          float: none
          }
          .single-post #page-header-bg.fullscreen-header,
          .single-post #single-below-header.fullscreen-header {
             background-color:#f6f6f6
        }
        .single-post #single-below-header.fullscreen-header {
            border-top:1px solid #DDD;
            border-bottom:none!important
        }
        ';
             }
        }

         //// Overlay transparency.
         $overlay_opacity = get_post_meta($post->ID, '_nectar_header_bg_overlay_opacity', true);

         if($overlay_opacity && 'default' !== $overlay_opacity) {
             echo '.page-header-overlay-color[data-overlay-opacity="' . esc_attr($overlay_opacity) . '"]:after { opacity: ' . esc_attr($overlay_opacity) . '; }';
         }

         //// Auto page header.
         $header_auto_title = apply_filters('nectar_blocks_auto_title', true);
         $post_title_hidden = get_post_meta( $post->ID, '_nectar_blocks_hide_post_title', true );

         if ( '1' === $post_title_hidden || true === $global_post_hide_title_vis ) {
            $header_auto_title = false;
         }

         $page_header_title = get_post_meta($post->ID, '_nectar_header_title', true);

         if( $header_auto_title && is_page() && empty($page_header_title) ) {

             $auto_header_font_color = ( isset($nectar_options['header-auto-title-text-color']) && ! empty($nectar_options['header-auto-title-text-color'])) ? esc_html($nectar_options['header-auto-title-text-color']) : false;

             if( empty($font_color) ) {
                 if ( ! defined('NECTAR_BLOCKS_ROOT_DIR_PATH') ) {
                    $font_color = (! empty($nectar_options['overall-font-color'])) ? $nectar_options['overall-font-color'] : '#333333';
                 }

                // Auto page header font color.
                if( $auto_header_font_color ) {
                    $font_color = $auto_header_font_color;
                }

             }
         }

         if( ! empty($font_color) && ! is_search() && ! is_category() && ! is_author() && ! is_date() ) {

             echo '#page-header-bg h1,
       #page-header-bg .subheader,
       #page-header-bg #portfolio-nav a i,
       body .section-title #portfolio-nav a:hover i,
       .page-header-no-bg h1,
       .page-header-no-bg span,
       #page-header-bg #portfolio-nav a i,
       #page-header-bg span,
       #page-header-bg #single-below-header a:hover,
       #page-header-bg #single-below-header a:focus,
       #page-header-bg.fullscreen-header .author-section a {
         color: ' . esc_attr($font_color) . '!important;
       } ';

             $font_color_no_hash = substr($font_color, 1);
             $colorR = hexdec( substr( $font_color_no_hash, 0, 2 ) );
             $colorG = hexdec( substr( $font_color_no_hash, 2, 2 ) );
             $colorB = hexdec( substr( $font_color_no_hash, 4, 2 ) );

             echo 'body #page-header-bg .pinterest-share i,
       body #page-header-bg .facebook-share i,
       body #page-header-bg .linkedin-share i,
       body #page-header-bg .twitter-share i,
       body #page-header-bg .google-plus-share i,
        body #page-header-bg .icon-salient-heart,
       body #page-header-bg .icon-salient-heart-2 {
         color: ' . esc_attr($font_color) . ';
       }
       #page-header-bg[data-post-hs="default_minimal"] .inner-wrap > a:not(:hover) {
         color: ' . esc_attr($font_color) . ';
         border-color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',0.4);
       }
       .single #page-header-bg #single-below-header > span {
         border-color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',0.4);
       }
       ';

             echo 'body .section-title #portfolio-nav a:hover i {
         opacity: 0.75;
       }';

             echo '.single #page-header-bg .blog-title #single-meta .nectar-social.hover > div a,
       .single #page-header-bg .blog-title #single-meta > div a,
       .single #page-header-bg .blog-title #single-meta ul .n-shortcode a,
       #page-header-bg .blog-title #single-meta .nectar-social.hover .share-btn {
         border-color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',0.4);
       }';

             echo '.single #page-header-bg .blog-title #single-meta .nectar-social.hover > div a:hover,
       #page-header-bg .blog-title #single-meta .nectar-social.hover .share-btn:hover,
       .single #page-header-bg .blog-title #single-meta div > a:hover,
       .single #page-header-bg .blog-title #single-meta ul .n-shortcode a:hover,
       .single #page-header-bg .blog-title #single-meta ul li:not(.meta-share-count):hover > a{
         border-color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',1);
       }';

             echo '.single #page-header-bg #single-meta div span,
       .single #page-header-bg #single-meta > div a,
       .single #page-header-bg #single-meta > div i {
         color: ' . esc_attr($font_color) . '!important;
       }';

             echo '.single #page-header-bg #single-meta ul .meta-share-count .nectar-social a i {
         color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',0.7)!important;
       }';

             echo '.single #page-header-bg #single-meta ul .meta-share-count .nectar-social a:hover i {
         color: rgba(' . $colorR . ',' . $colorG . ',' . $colorB . ',1)!important;
       }';
        }

        //// Header Navigation entrance animation.
        $header_nav_entrance_animation = ( isset($post->ID) ) ? get_post_meta($post->ID, '_nectar_blocks_header_animation', true) : false;
        $header_nav_entrance_animation_effect = ( isset($post->ID) ) ? get_post_meta($post->ID, '_nectar_blocks_header_animation_effect', true) : false;
        $header_nav_entrance_animation_delay = ( isset($post->ID) ) ? get_post_meta($post->ID, '_nectar_blocks_header_animation_delay', true) : 0;

        if( is_page() &&
            '1' === $header_nav_entrance_animation &&
            'fade' === $header_nav_entrance_animation_effect
        ) {
            echo '
      @keyframes header_nav_entrance_animation {
        0% { opacity: 0.01; }
        100% { opacity: 1; }
      }

      @media only screen and (min-width: 691px) {
        #nectar-nav {
          opacity: 0.01;
        }

        #nectar-nav.entrance-animation {
          animation: header_nav_entrance_animation 1.5s ease forwards;
          animation-delay: ' . floatval($header_nav_entrance_animation_delay) . 's;
        }
      }
      ';
        }
        else if(
            is_page() &&
            '1' === $header_nav_entrance_animation &&
            'slide' === $header_nav_entrance_animation_effect
        ) {
            echo '
      @keyframes header_nav_entrance_animation {
        0% { opacity: 0.01; }
        100% { opacity: 1; }
      }

          @keyframes header_nav_entrance_animation_2 {
        0% { transform: translateY(-100%); }
        100% { transform: translateY(0); }
      }

      @media only screen and (min-width: 691px) {
        #nectar-nav {
          opacity: 0.01;
        }

        #nectar-nav.entrance-animation {
          animation: header_nav_entrance_animation 1.5s cubic-bezier(0.25,1,0.5,1) forwards;
          animation-delay: ' . floatval($header_nav_entrance_animation_delay) . 's;
        }

        #nectar-nav.entrance-animation #top,
        #nectar-nav.entrance-animation #header-secondary-outer {
          animation: header_nav_entrance_animation_2 1.5s cubic-bezier(0.25,1,0.5,1) forwards;
          animation-delay: ' . floatval($header_nav_entrance_animation_delay) . 's;
        }
      }
      ';
        }

        //// Page header text effect.
        $page_header_text_effect = get_post_meta($post->ID, '_nectar_page_header_text-effect', true);
        if( 'rotate_in' === $page_header_text_effect ) {
            echo '
      #page-header-bg[data-text-effect="rotate_in"] .wraped,
      .overlaid-content[data-text-effect="rotate_in"] .wraped{
        display:inline-block
      }
      #page-header-bg[data-text-effect="rotate_in"] .wraped span,
      .overlaid-content[data-text-effect="rotate_in"] .wraped span,
      #page-header-bg[data-text-effect="rotate_in"] .inner-wrap >*:not(.top-heading),
      .overlaid-content[data-text-effect="rotate_in"] .inner-wrap >*:not(.top-heading){
        opacity:0;
        transform-origin:center center;
        -webkit-transform-origin:center center;
        transform:translateY(30px);
        -webkit-transform:translateY(30px);
        transform-style:preserve-3d;
        -webkit-transform-style:preserve-3d
      }

      #page-header-bg[data-text-effect="rotate_in"] .wraped span,
      #page-header-bg[data-text-effect="rotate_in"] .inner-wrap.shape-1 >*:not(.top-heading),
      #page-header-bg[data-text-effect="rotate_in"] >div:not(.nectar-particles) .span_6 .inner-wrap >*:not(.top-heading),
      .overlaid-content[data-text-effect="rotate_in"] .wraped span,
      .overlaid-content[data-text-effect="rotate_in"] .inner-wrap.shape-1 >*:not(.top-heading),
      .overlaid-content[data-text-effect="rotate_in"] .inner-wrap >*:not(.top-heading){
        transform:rotateX(90deg) translateY(35px);
        -webkit-transform:rotateX(90deg) translateY(35px)
      }
      #page-header-bg[data-text-effect="rotate_in"] .wraped,
      #page-header-bg[data-text-effect="rotate_in"] .wraped span,
      .overlaid-content[data-text-effect="rotate_in"] .wraped,
      .overlaid-content[data-text-effect="rotate_in"] .wraped span{
        display:inline-block
      }
      #page-header-bg[data-text-effect="rotate_in"] .wraped span,
      .overlaid-content[data-text-effect="rotate_in"] .wraped span{
        transform-origin:initial;
        -webkit-transform-origin:initial
      }';
        }

        //// Page header particles.
        $page_header_bg_type = get_post_meta($post->ID, '_nectar_slider_bg_type', true);
        if( 'particle_bg' === $page_header_bg_type ) {
            echo '
      #page-header-bg[data-alignment-v="top"].fullscreen-header .nectar-particles .span_6,
      #page-header-bg[data-alignment-v="middle"].fullscreen-header .nectar-particles .span_6 {
        top:auto!important;
        transform:none!important;
        -webkit-transform:none!important
      }
      #page-header-bg .canvas-bg{
        transition:background-color 0.7s ease;
        -webkit-transition:background-color 0.7s ease;
        position:absolute;
        top:0;
        left:0;
        width:100%;
        height:100%;
        z-index: 10;
      }
      #page-header-bg .nectar-particles .span_6,
      .nectar-box-roll .overlaid-content .span_6{
        backface-visibility:visible;
        transform-style:preserve-3d;
        -webkit-transform-origin:50% 100%;
        transform-origin:50% 100%;
        top:auto;
        bottom:auto;
        width:100%;
        height:100%
      }

      #page-header-bg .nectar-particles{
        width:100%;
        height:100%
      }
      #page-header-bg .nectar-particles .inner-wrap {
        top:0;
        left:0;
        position:absolute;
        width:100%;
      }

      @media only screen and (min-width: 1025px) {
        #page-header-bg[data-alignment-v="middle"][data-alignment="center"][data-parallax="1"] .nectar-particles .inner-wrap {
          height: 100%;
          top: 0;
          -webkit-transform: none;
          transform: none;
          -webkit-display: flex;
          display: flex;
          -webkit-align-items: center;
          align-items: center;
          -webkit-justify-content: center;
          justify-content: center;
          -webkit-flex-direction: column;
          flex-direction: column;
          padding-top: 0;
        }
      }

      #page-header-bg .nectar-particles .span_6 .inner-wrap{
        left:0;
        position:absolute;
        width:100%
      }

      .nectar-particles .inner-wrap .hide {
        visibility: hidden;
      }

      #page-header-wrap .nectar-particles .fade-out{
        content:"";
        display:block;
        width:100%;
        height:100%;
        position:absolute;
        top:0;
        left:0;
        z-index:1000;
        opacity:0;
        background-color:#000;
        pointer-events:none
      }

      .pagination-navigation{
        text-align:center;
        font-size:0;
        position:absolute;
        right:20px;
        top:50%;
        width:33px;
        transform:translateY(-50%) translateZ(0);
        -webkit-transform:translateY(-50%) translateZ(0);
        backface-visibility:hidden;
        -webkit-backface-visibility:hidden;
        opacity:0.5;
        line-height:1px;
        z-index:1000
      }
      @media only screen and (max-width:690px){
        #nectar-content-wrap .pagination-navigation,
        .pagination-navigation{
          display:none
        }
        .overlaid-content svg{
          display:none
        }
      }

      .pagination-dot, .pagination-current{
        transition: transform 0.3s cubic-bezier(.21, .6, .35, 1);
        position:relative;
        display:inline-block;
        width:10px;
        height:10px;
        padding:0;
        line-height:17px;
        background:#fff;
        border-radius:50%;
        margin:12px 7px;
        border:none;
        outline:none;
        font-size:14px;
        font-weight:bold;
        color:#fff;
        cursor:pointer;
        transform:translateY(20px);
        -webkit-transform:translateY(20px);
        opacity:0
      }
      .nectar-particles .pagination-current,
      .overlaid-content .pagination-current{
        position:absolute;
        left:1px;
        top:0;
        z-index:100;
        display: none;
      }

      .pagination-dot.active {
        transform: scale(1.7)!important;
      }
      body .pagination-navigation {
        -webkit-filter: none;
        filter: none;
      }
      ';
        }

        //// Page header blog archives;
        if( is_category() || is_author() || is_date() || is_tag() || is_home() ) {

            $using_gradient_header = false;
            if( isset(NectarThemeManager::$options['blog_archive_bg_functionality']) &&
                NectarThemeManager::$options['blog_archive_bg_functionality'] === 'color' ) {

                $color_layout = isset(NectarThemeManager::$options['blog_archive_bg_color_layout']) ? NectarThemeManager::$options['blog_archive_bg_color_layout'] : 'default';

                if ( 'gradient' === $color_layout ) {
                    $using_gradient_header = true;
                }

            }

            if (! $using_gradient_header) {
                // echo '
                // body[data-bg-header="true"].category .container-wrap,
                // body[data-bg-header="true"].author .container-wrap,
                // body[data-bg-header="true"].date .container-wrap,
                // body[data-bg-header="true"].blog .container-wrap{
                // padding-top:var(--container-padding)!important
                // }
                // ';
            } else {
                echo '
        body[data-bg-header="true"].category .container-wrap,
        body[data-bg-header="true"].author .container-wrap,
        body[data-bg-header="true"].date .container-wrap,
        body[data-bg-header="true"].blog .container-wrap{
        padding-top:0!important
        }
        ';
            }

            echo '
      .archive.author .row .col.section-title span,
      .archive.category .row .col.section-title span,
      .archive.tag .row .col.section-title span,
      .archive.date .row .col.section-title span{
        padding-left:0
      }

      body.author #page-header-wrap #page-header-bg,
      body.category #page-header-wrap #page-header-bg,
      body.tag #page-header-wrap #page-header-bg,
      body.date #page-header-wrap #page-header-bg {
        height: auto;
        padding-top: 8%;
          padding-bottom: 8%;
      }';

            $animate_in_effect = ( ! empty($nectar_options['header-animate-in-effect'])) ? $nectar_options['header-animate-in-effect'] : 'none';

            if( 'slide-down' !== $animate_in_effect ) {
                echo '.archive #page-header-wrap {
          height: auto;
        }';
            }

            echo '.archive.category .row .col.section-title p,
      .archive.tag .row .col.section-title p {
        margin-top: 10px;
      }


      body[data-bg-header="true"].archive .container-wrap.meta_overlaid_blog,
      body[data-bg-header="true"].category .container-wrap.meta_overlaid_blog,
      body[data-bg-header="true"].author .container-wrap.meta_overlaid_blog,
      body[data-bg-header="true"].date .container-wrap.meta_overlaid_blog {
        padding-top: 0!important;
      }


      #page-header-bg[data-alignment="center"] .span_6 p {
        margin: 0 auto;
      }

      body.archive #page-header-bg:not(.fullscreen-header) .span_6 {
        position: relative;
        -webkit-transform: none;
        transform: none;
        top: 0;
      }

      .blog-archive-header .nectar-author-gravatar img {
        width: 125px;
        border-radius: 100px;
      }

      .blog-archive-header .container .span_12 p {
        font-size: min(max(calc(1.3vw), 16px), 20px);
        line-height: 1.5;
        margin-top: 0.5em;
      }

      body .page-header-no-bg.color-bg {
        padding: 5% 0;
      }
      @media only screen and (max-width: 1024px) {
        body .page-header-no-bg.color-bg {
          padding: 7% 0;
        }
      }
      @media only screen and (max-width: 690px) {
        body .page-header-no-bg.color-bg {
          padding: 9% 0;
        }
        .blog-archive-header .nectar-author-gravatar img {
          width: 75px;
        }
      }

      .blog-archive-header.color-bg  .col.section-title{
        border-bottom: 0;
        padding: 0;
      }
      .blog-archive-header.color-bg * {
        color: inherit!important;
      }

      .nectar-archive-tax-count {
        position: relative;
        padding: 0.5em;
        transform: translateX(0.25em) translateY(-0.75em);
        font-size: clamp(14px,0.3em,20px);
        display: inline-block;
        vertical-align: super;
      }
      .nectar-archive-tax-count:before {
        content: "";
        display: block;
        padding-bottom: 100%;
        width: 100%;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border-radius: 100px;
        background-color: currentColor;
        opacity: 0.1;
      }';

        }

        // HEADER NAV
        $theme_skin = NectarThemeManager::$skin;
        $header_format = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';

        $centered_menu_bb_sep = (isset($nectar_options['centered-menu-bottom-bar-separator']) && ! empty($nectar_options['centered-menu-bottom-bar-separator'])) ? $nectar_options['centered-menu-bottom-bar-separator'] : '0';

        if( $header_format === 'centered-menu-bottom-bar' ) {
            $theme_skin = 'material';
        }

        //// Using image based logo.
        if( ! empty( $nectar_options['use-logo'] ) ) {
                $logo_height = ( ! empty($nectar_options['logo-height']) ) ? intval($nectar_options['logo-height']) : 30;
        }
        //// Using text logo.
        else {
                // Custom size from typography logo line height option.
                if( ! empty($nectar_options['logo_font_family']['line-height']) ) {
                    $logo_height = intval(substr($nectar_options['logo_font_family']['line-height'], 0, -2));
                }
                // Custom size from typography logo font size option.
                else if( ! empty($nectar_options['logo_font_family']['font-size']) ) {
                    $logo_height = intval(substr($nectar_options['logo_font_family']['font-size'], 0, -2));
                }
                // Default size.
                else {
                    $logo_height = 22;
                }
        }
        $header_padding = (! empty($nectar_options['header-padding'])) ? intval($nectar_options['header-padding']) : 28;
        $nav_font_size = (! empty($nectar_options['use-custom-fonts']) && $nectar_options['use-custom-fonts'] == 1 && ! empty($nectar_options['navigation_font_size']) && $nectar_options['navigation_font_size'] != '-') ? intval(substr($nectar_options['navigation_font_size'], 0, -2) * 1.4 ) : 20;
        $dd_indicator_height = (! empty($nectar_options['use-custom-fonts']) && $nectar_options['use-custom-fonts'] == 1 && ! empty($nectar_options['navigation_font_size']) && $nectar_options['navigation_font_size'] != '-') ? intval(substr($nectar_options['navigation_font_size'], 0, -2)) - 1 : 20;
        $padding_top = ceil(($logo_height / 2)) - ceil(($nav_font_size / 2));
        $padding_bottom = (ceil(($logo_height / 2)) - ceil(($nav_font_size / 2))) + $header_padding;
        $search_padding_top = ceil(($logo_height / 2)) - ceil(21 / 2) + 1;
        $search_padding_bottom = (ceil(($logo_height / 2)) - ceil(21 / 2));
        $using_secondary = (! empty($nectar_options['header_layout'])) ? $nectar_options['header_layout'] : ' ';

        //// Larger secondary header with material theme skin.
        if( $theme_skin === 'material' ) {
            $extra_secondary_height = ($using_secondary === 'header_with_secondary') ? 42 : 0;
        } else {
            $extra_secondary_height = ($using_secondary === 'header_with_secondary') ? 34 : 0;
        }

        if( $header_format === 'centered-menu-bottom-bar' ) {
            $sep_height = ($header_format === 'centered-menu-bottom-bar' && '1' === $centered_menu_bb_sep ) ? $header_padding : 0;
            $header_space = $logo_height + ($header_padding * 3) + $nav_font_size + $extra_secondary_height + $sep_height;
        }
        else if( $header_format === 'centered-menu-under-logo' ) {
            $header_space = $logo_height + ($header_padding * 2) + 20 + $nav_font_size + $extra_secondary_height;
        }
        else {
            $header_space = $logo_height + ($header_padding * 2) + $extra_secondary_height;
        }

        //// Hide scrollbar during loading if using fullpage option.
        $page_full_screen_rows = (isset($post->ID)) ? get_post_meta($post->ID, '_nectar_full_screen_rows', true) : '';
        if( $page_full_screen_rows === 'on' && ! is_search() ) {

            echo 'body,html {
        overflow: hidden;
        height: 100%;
      }';
        }

        // BODY BORDER.
        $body_border = (! empty($nectar_options['body-border'])) ? $nectar_options['body-border'] : 'off';
        $body_border_size = (! empty($nectar_options['body-border-size'])) ? $nectar_options['body-border-size'] : '20';
        $body_border_color = (! empty($nectar_options['body-border-color'])) ? $nectar_options['body-border-color'] : '#ffffff';

        if( $body_border === '1' ) {

            $using_boxed = (! empty($nectar_options['boxed_layout']) && $nectar_options['boxed_layout'] === '1') ? true : false;
            $headerFormat = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';
            $headerColorScheme = (! empty($nectar_options['header-color'])) ? $nectar_options['header-color'] : 'light';
            $userSetBG = (! empty($nectar_options['header-background-color']) && $headerColorScheme === 'custom') ? $nectar_options['header-background-color'] : '#ffffff';

            if( empty($nectar_options['transparent-header']) ) {
                $activate_transparency = false;
            }

            echo '@media only screen and (min-width: 1025px) {

        .page-submenu > .full-width-section,
        .page-submenu .full-width-content,
        .full-width-content.blog-fullwidth-wrap,
        .wpb_row.full-width-content,
        body .full-width-section .row-bg-wrap,
        body .full-width-section > .nectar-shape-divider-wrap,
        body .full-width-section > .video-color-overlay,
        body .full-width-section.parallax_section .row-bg-wrap {
          margin-left: calc(-50vw + ' . intval($body_border_size * 2) . 'px);
          margin-left: calc(-50vw + var(--scroll-bar-w)/2 + ' . intval($body_border_size * 2) . 'px);
          left: calc(50% - ' . intval($body_border_size) . 'px);
          width: calc(100vw - ' . intval($body_border_size) * 2 . 'px);
          width: calc(100vw - var(--scroll-bar-w) - ' . intval($body_border_size) * 2 . 'px);
        }';

                if( $headerFormat === 'left-header' ) {
                    echo '[data-header-format="left-header"] .full-width-content.blog-fullwidth-wrap,
          [data-header-format="left-header"] .wpb_row.full-width-content,
          [data-header-format="left-header"] .page-submenu > .full-width-section,
          [data-header-format="left-header"] .page-submenu .full-width-content,
          [data-header-format="left-header"] .full-width-section .row-bg-wrap,
          [data-header-format="left-header"] .full-width-section > .nectar-shape-divider-wrap,
          [data-header-format="left-header"] .full-width-section > .video-color-overlay,
          [data-header-format="left-header"] .full-width-section.parallax_section .row-bg-wrap,
          [data-header-format="left-header"] .nectar-slider-wrap[data-full-width="true"] {
          width: calc(100vw - 272px - ' . intval($body_border_size) . 'px);
          width: calc(100vw - 272px - var(--scroll-bar-w) - ' . intval($body_border_size) . 'px);
          margin-left: calc(-50vw + 135px + ' . intval($body_border_size) . 'px/2 + var(--scroll-bar-w)/2);
          }
          [data-header-format="left-header"] .full-width-section > .nectar-video-wrap {
            width: calc(100vw - 272px - var(--scroll-bar-w) - ' . intval($body_border_size) . 'px)!important;
          margin-left: calc(-50vw + 135px + ' . intval($body_border_size) . 'px/2 + var(--scroll-bar-w)/2)!important;
          }

          [data-header-format="left-header"] .container-wrap {
          padding-right: ' . esc_attr($body_border_size) . 'px;
          padding-left: 0
        }
        body > .nectar-global-section {
          padding-right: ' . esc_attr($body_border_size) . 'px;
        }
        body {
          padding-top: ' . esc_attr($body_border_size) . 'px;
        }';
            }

            if ( $using_boxed ) {
                echo '.container-wrap {
          padding-bottom: ' . esc_attr($body_border_size) . 'px;
        }';
            } else {
                echo '.container-wrap {
          padding-right: ' . esc_attr($body_border_size) . 'px;
          padding-left: ' . esc_attr($body_border_size) . 'px;
          padding-bottom: ' . esc_attr($body_border_size) . 'px;
        }';
            }

            echo '
      body {
        padding-bottom: ' . esc_attr($body_border_size) . 'px;
      }

       #footer-outer[data-full-width="1"] {
         padding-right: ' . esc_attr($body_border_size) . 'px;
         padding-left: ' . esc_attr($body_border_size) . 'px;
       }

       body[data-footer-reveal="1"] #footer-outer {
         bottom: ' . esc_attr($body_border_size) . 'px;
       }

       #slide-out-widget-area.fullscreen .bottom-text[data-has-desktop-social="false"],
       #slide-out-widget-area.fullscreen-alt .bottom-text[data-has-desktop-social="false"] {
         bottom: ' . intval($body_border_size + 28) . 'px;
       }

      #nectar-nav {
        box-shadow: none;
        -webkit-box-shadow: none;
      }

       .slide-out-hover-icon-effect.small,
       .slide-out-hover-icon-effect:not(.small) {
         margin-top: ' . esc_attr($body_border_size) . 'px;
         margin-right: ' . esc_attr($body_border_size) . 'px;
       }

       #slide-out-widget-area-bg.fullscreen-alt {
         padding: ' . esc_attr($body_border_size) . 'px;
       }

       #slide-out-widget-area.slide-out-from-right-hover {
         margin-right: ' . esc_attr($body_border_size) . 'px;
       }

       .orbit-wrapper div.slider-nav span.left,
       .swiper-container .slider-prev {
         margin-left: ' . esc_attr($body_border_size) . 'px;
       }
       .orbit-wrapper div.slider-nav span.right,
       .swiper-container .slider-next {
         margin-right: ' . esc_attr($body_border_size) . 'px;
       }

       .admin-bar #slide-out-widget-area-bg.fullscreen-alt {
         padding-top: ' . intval($body_border_size + 32) . 'px;
       }

       #nectar-nav,
       [data-hhun="1"] #nectar-nav.detached:not(.scrolling),
       #slide-out-widget-area.fullscreen .bottom-text {
         margin-top: ' . esc_attr($body_border_size) . 'px;
         padding-right: ' . esc_attr($body_border_size) . 'px;
         padding-left: ' . esc_attr($body_border_size) . 'px;
       }';

             if( nectar_is_contained_header() ) {
                echo 'html body #nectar-nav,
        html body[data-hhun="1"] #nectar-nav.detached:not(.scrolling) {
          margin-top: max(calc(var(--container-padding)/3),25px + ' . esc_attr($body_border_size) . 'px);
          width: calc(100% - ' . esc_attr($body_border_size * 2) . 'px - var(--container-padding)*2);
          padding-left: 0;
          padding-right: 0;
        }
        ';

             }

             echo '#nectar_fullscreen_rows {
         margin-top: ' . esc_attr($body_border_size) . 'px;
       }

      #slide-out-widget-area.fullscreen .off-canvas-social-links {
        padding-right: ' . esc_attr($body_border_size) . 'px;
      }

      #slide-out-widget-area.fullscreen .off-canvas-social-links,
      #slide-out-widget-area.fullscreen .bottom-text {
        padding-bottom: ' . esc_attr($body_border_size) . 'px;
      }

      body[data-button-style] .section-down-arrow,
      .scroll-down-wrap.no-border .section-down-arrow,
      [data-full-width="true"][data-fullscreen="true"] .swiper-wrapper .slider-down-arrow {
        bottom: calc(16px + ' . esc_attr($body_border_size) . 'px);
      }

      .ascend #search-outer #search #close,
      #page-header-bg .pagination-navigation {
        margin-right:  ' . esc_attr($body_border_size) . 'px;
      }

      #to-top {
        right: ' . intval($body_border_size + 17) . 'px;
        margin-bottom: ' . esc_attr($body_border_size) . 'px;
      }

      body[data-header-color="light"] #nectar-nav:not(.transparent) .sf-menu > li > ul {
        border-top: none;
      }

      .nectar-social.fixed {
        margin-bottom: ' . esc_attr($body_border_size) . 'px;
        margin-right: ' . esc_attr($body_border_size) . 'px;
      }

      .page-submenu.stuck {
        padding-left: ' . esc_attr($body_border_size) . 'px;
        padding-right: ' . esc_attr($body_border_size) . 'px;
      }

      #fp-nav {
        padding-right: ' . esc_attr($body_border_size) . 'px;
      }
      .body-border-left {
        background-color: ' . esc_attr($body_border_color) . ';
        width: ' . esc_attr($body_border_size) . 'px;
      }
      .body-border-right {
        background-color: ' . esc_attr($body_border_color) . ';
        width: ' . esc_attr($body_border_size) . 'px;
      }
      .body-border-bottom {
        background-color: ' . esc_attr($body_border_color) . ';
        height: ' . esc_attr($body_border_size) . 'px;
      }

      .body-border-top {
        background-color: ' . esc_attr($body_border_color) . ';
        height: ' . esc_attr($body_border_size) . 'px;
      }

    } ';

        if( ($body_border_color === '#ffffff' && $headerColorScheme === 'light' || $headerColorScheme === 'custom' && $body_border_color === $userSetBG ) && $activate_transparency !== true ) {

                echo '#nectar-nav:not([data-using-secondary="1"]):not(.transparent),
        body.ascend #search-outer,
        body[data-slide-out-widget-area-style="fullscreen-alt"] #nectar-nav:not([data-using-secondary="1"]),
        #nectar_fullscreen_rows,
        body #slide-out-widget-area-bg {
          margin-top: 0!important;
        }

        .body-border-top {
          z-index: 9997;
        }

        body:not(.material) #slide-out-widget-area.slide-out-from-right {
          z-index: 9997;
        }

        body #nectar-nav,
        body[data-slide-out-widget-area-style="slide-out-from-right-hover"] #nectar-nav {
          z-index: 9998;
        }

        @media only screen and (min-width: 1025px) {
          body[data-user-set-ocm="off"].material #nectar-nav[data-full-width="true"],
          body[data-user-set-ocm="off"].ascend #nectar-nav { z-index: 10010; }
        }

        @media only screen and (min-width: 1025px) {
          body #slide-out-widget-area.slide-out-from-right-hover { z-index: 9996; }
          #nectar-nav[data-full-width="true"]:not([data-transparent-header="true"]) header > .container,
          #nectar-nav[data-full-width="true"][data-transparent-header="true"].pseudo-data-transparent header > .container {
            padding-left: 0; padding-right: 0;
          }
        }

        @media only screen and (max-width: 1080px) and (min-width: 1025px) {
          .ascend[data-slide-out-widget-area="true"] #nectar-nav[data-full-width="true"]:not([data-transparent-header="true"]) header > .container {
            padding-left: 0;
            padding-right: 0;
          }
        }

        body[data-header-search="false"][data-slide-out-widget-area="false"].ascend #nectar-nav[data-full-width="true"][data-cart="true"]:not([data-transparent-header="true"]) header > .container {
          padding-right: 28px;
        }

        body[data-slide-out-widget-area-style="slide-out-from-right"] #nectar-nav[data-header-resize="0"] {
          -webkit-transition: -webkit-transform 0.7s cubic-bezier(0.645, 0.045, 0.355, 1), background-color 0.3s cubic-bezier(0.215,0.61,0.355,1), box-shadow 0.40s ease, margin 0.3s cubic-bezier(0.215,0.61,0.355,1)!important;
          transition: transform 0.7s cubic-bezier(0.645, 0.045, 0.355, 1), background-color 0.3s cubic-bezier(0.215,0.61,0.355,1), box-shadow 0.40s ease, margin 0.3s cubic-bezier(0.215,0.61,0.355,1)!important;
        }

        @media only screen and (min-width: 1025px) {
          body div.portfolio-items[data-gutter*="px"][data-col-num="elastic"] {
            padding: 0!important;
          }
        }

        body #nectar-nav[data-transparent-header="true"].transparent {
          transition: none;
          -webkit-transition: none;
        }
        body[data-slide-out-widget-area-style="fullscreen-alt"] #nectar-nav {
          transition:  background-color 0.3s cubic-bezier(0.215,0.61,0.355,1);
          -webkit-transition:  background-color 0.3s cubic-bezier(0.215,0.61,0.355,1);
        }

        @media only screen and (min-width: 1025px) {
          body.ascend[data-slide-out-widget-area="false"] #nectar-nav[data-header-resize="0"][data-cart="true"]:not(.transparent) {
            z-index: 100000;
          }
        } ';

            }

            else if( $body_border_color === '#ffffff' && $headerColorScheme === 'light' || $headerColorScheme === 'custom' && $body_border_color === $userSetBG) {

                echo '
        @media only screen and (min-width: 1025px) {
          #nectar-nav.small-nav:not(.transparent),
          #nectar-nav[data-header-resize="0"]:not([data-using-secondary="1"]).scrolled-down:not(.transparent),
          #nectar-nav[data-header-resize="0"]:not([data-using-secondary="1"]).fixed-menu:not(.transparent),
          #nectar-nav.detached,
          body.ascend #search-outer.small-nav,
          body[data-slide-out-widget-area-style="slide-out-from-right-hover"] #nectar-nav:not([data-using-secondary="1"]):not(.transparent),
          body[data-slide-out-widget-area-style="fullscreen-alt"] #nectar-nav:not([data-using-secondary="1"]).scrolled-down,
          body[data-slide-out-widget-area-style="fullscreen-alt"] #nectar-nav:not([data-using-secondary="1"]).transparent.side-widget-open {
            margin-top: 0px;
            z-index: 100000;
          }

          body[data-hhun="1"] #nectar-nav.detached {
            z-index: 100000!important;
          }

          body.ascend[data-slide-out-widget-area="true"] #nectar-nav[data-full-width="true"] .cart-menu-wrap,
          body.ascend[data-slide-out-widget-area="false"] #nectar-nav[data-full-width="true"][data-cart="true"] .cart-menu-wrap {
            transition: right 0.3s cubic-bezier(0.215, 0.61, 0.355, 1);
            -webkit-transition: all 0.3s cubic-bezier(0.215, 0.61, 0.355, 1);
          }


          #nectar-nav[data-full-width="true"][data-transparent-header="true"][data-header-resize="0"].scrolled-down:not(.transparent) .container,
          body[data-slide-out-widget-area-style="fullscreen-alt"] #nectar-nav[data-full-width="true"].scrolled-down .container,
          body[data-slide-out-widget-area-style="fullscreen-alt"] #nectar-nav[data-full-width="true"].transparent.side-widget-open .container {
            padding-left: 0!important;
            padding-right: 0!important;
          }

          @media only screen and (min-width: 1025px) {
            .material #nectar-nav[data-full-width="true"][data-transparent-header="true"][data-header-resize="0"].scrolled-down:not(.transparent) #search-outer .container {
              padding: 0 90px!important;
            }
          }

          body[data-header-search="false"][data-slide-out-widget-area="false"].ascend #nectar-nav[data-full-width="true"][data-cart="true"]:not(.transparent) header > .container {
            padding-right: 28px!important;
          }


        }

        @media only screen and (min-width: 1025px) {
          body div.portfolio-items[data-gutter*="px"][data-col-num="elastic"] { padding: 0!important; }
        }

        #nectar-nav[data-full-width="true"][data-header-resize="0"].transparent {
          transition: transform 0.7s cubic-bezier(0.645, 0.045, 0.355, 1),  background-color 0.3s cubic-bezier(0.215,0.61,0.355,1), margin 0.3s cubic-bezier(0.215,0.61,0.355,1)!important;
          -webkit-transition: -webkit-transform 0.7s cubic-bezier(0.645, 0.045, 0.355, 1),  background-color 0.3s cubic-bezier(0.215,0.61,0.355,1), margin 0.3s cubic-bezier(0.215,0.61,0.355,1)!important;
        }

        body #nectar-nav[data-transparent-header="true"][data-header-resize="0"] {
           -webkit-transition: -webkit-transform 0.7s cubic-bezier(0.645, 0.045, 0.355, 1), background-color 0.3s cubic-bezier(0.215,0.61,0.355,1), box-shadow 0.40s ease, margin 0.3s cubic-bezier(0.215,0.61,0.355,1)!important;
           transition: transform 0.7s cubic-bezier(0.645, 0.045, 0.355, 1), background-color 0.3s cubic-bezier(0.215,0.61,0.355,1), box-shadow 0.40s ease, margin 0.3s cubic-bezier(0.215,0.61,0.355,1)!important;
         }

        #nectar-nav[data-full-width="true"][data-header-resize="0"] header > .container {
          transition: padding 0.35s cubic-bezier(0.215,0.61,0.355,1);
          -webkit-transition: padding 0.35s cubic-bezier(0.215,0.61,0.355,1);
        }
        ';

            }

            else if ( $body_border_color !== '#ffffff' && $headerColorScheme == 'light' || $headerColorScheme === 'custom' && $body_border_color !== $userSetBG ) {
                echo '@media only screen and (min-width: 1025px) {
          #nectar-nav-spacer {
            margin-top: ' . esc_attr($body_border_size) . 'px;
          }
        }';
                echo 'html body.ascend[data-user-set-ocm="off"] #nectar-nav[data-full-width="true"] .cart-outer[data-user-set-ocm="off"] .cart-menu-wrap {
          right: ' . intval($body_border_size) . 'px!important;
        }
        html body.ascend[data-user-set-ocm="1"] #nectar-nav[data-full-width="true"] .cart-outer[data-user-set-ocm="1"] .cart-menu-wrap {
          right: ' . intval($body_border_size + 77) . 'px!important;
        }';

            }

        } //// Body border end.

        // HEADER NAV TRANSPARENCY
        if( ! empty($nectar_options['transparent-header']) &&
            $nectar_options['transparent-header'] == '1' &&
            ! nectar_is_perma_trans_header_forced() ||
            nectar_is_contained_header() ) {

            if( $activate_transparency ) {

                $headerFormat = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';

                if( $headerFormat !== 'left-header' ) {
                    echo '@media only screen and (max-width: 1024px) {
            body #nectar-nav-spacer[data-header-mobile-fixed="1"] {
              display: none;
            }
            #nectar-nav[data-mobile-fixed="false"] {
              position: absolute;
            }
          }';

                    // Secondary header always visible.
                    $using_secondary_nav = ( ! empty( $nectar_options['header_layout'] ) && $headerFormat !== 'left-header' ) ? $nectar_options['header_layout'] : ' ';
                    $header_secondary_m_display = ( ! empty( $nectar_options['secondary-header-mobile-display'] ) ) ? $nectar_options['secondary-header-mobile-display'] : 'default';
                    $header_secondary_m_bool = ( $using_secondary_nav === 'header_with_secondary' && $header_secondary_m_display === 'display_full' ) ? true : false;

                    echo '@media only screen and (max-width: 1024px) {
            body:not(.nectar-no-flex-height) #nectar-nav-spacer[data-secondary-header-display="full"]:not([data-header-mobile-fixed="false"]) {
              display: block!important;
              margin-bottom: -' . (intval($mobile_logo_height) + 26) . 'px;
            }
            #nectar-nav-spacer[data-secondary-header-display="full"][data-header-mobile-fixed="false"] {
              display: none;
            }';

                        if( $header_secondary_m_bool ) {

                            $page_full_screen_rows = ( isset( $post->ID ) ) ? get_post_meta( $post->ID, '_nectar_full_screen_rows', true ) : '';
                            $page_full_screen_rows_mobile_disable = ( isset( $post->ID ) ) ? get_post_meta( $post->ID, '_nectar_full_screen_rows_mobile_disable', true ) : '';
                            if( $page_full_screen_rows === 'on' && $page_full_screen_rows_mobile_disable === 'on' && ! is_search()) {
                                echo 'body.using-mobile-browser #nectar-nav-spacer:not([data-header-mobile-fixed="false"]) {
                  display: block!important;
                  margin-bottom: -' . (intval($mobile_logo_height) + 26) . 'px;
                }';
                                echo '#nectar-nav[data-mobile-fixed="false"], body.nectar_using_pfsr:not(.using-mobile-browser) #nectar-nav {';
                            } else {
                                echo '#nectar-nav[data-mobile-fixed="false"], body.nectar_using_pfsr #nectar-nav {';
                            }
                            echo 'top: 0!important;
                margin-bottom: -' . (intval($mobile_logo_height) + 26) . 'px!important;
                position: relative!important;
              }';

                        }

                    echo '}';

                }

                echo '@media only screen and (min-width: 1025px) {

          #nectar-nav-spacer {
            display: none;
          }
          .nectar-slider-wrap.first-section,
          .parallax_slider_outer.first-section,
          .full-width-content.first-section,
          .parallax_slider_outer.first-section .swiper-slide .content,
          .nectar-slider-wrap.first-section .swiper-slide .content,
          #page-header-bg, .nder-page-header,
          #page-header-wrap,
          .full-width-section.first-section {
            margin-top: 0!important;
          }

          body #page-header-bg, body #page-header-wrap {
            height: ' . esc_attr($header_space) . 'px;
          }

          body #search-outer { z-index: 100000; }

          }';

            } //activate

            else if( ! empty($nectar_options['header-bg-opacity']) ) {
                $header_space_bg_color = (! empty($nectar_options['overall-bg-color'])) ? $nectar_options['overall-bg-color'] : '#ffffff';
                echo '#nectar-nav-spacer { background-color: ' . esc_attr($header_space_bg_color) . '}';
            }

        } //using transparent theme option

        if ( nectar_is_contained_header() ) {
            $header_extra_space_to_remove = 0;
        } else {
            $header_extra_space_to_remove = $extra_secondary_height;

            if( $header_format === 'centered-menu-under-logo' || $header_format === 'centered-menu-bottom-bar' ) {
                $header_extra_space_to_remove += 20;
            } else {
                $remove_border = ( ! empty( $nectar_options['header-remove-border'] ) && $nectar_options['header-remove-border'] === '1' || $theme_skin === 'material' ) ? 'true' : 'false';
                if( 'true' === $remove_border ) {
                    $header_extra_space_to_remove += intval($header_padding);
                }
            }
        }

        // Desktop page header fullscreen calcs.
        if( (! empty($nectar_options['transparent-header']) &&
            $nectar_options['transparent-header'] === '1' &&
            $activate_transparency &&
            ! nectar_is_perma_trans_header_forced()) ||
            $header_format === 'left-header' ||
            nectar_is_contained_header() ) {

         $headerFormat = (! empty($nectar_options['header_format'])) ? $nectar_options['header_format'] : 'default';

         $contained_header_mod = 25;

         echo '
     @media only screen and (min-width: 1025px) {

        #page-header-wrap.fullscreen-header,
        #page-header-wrap.fullscreen-header #page-header-bg,
        html:not(.nectar-box-roll-loaded) .nectar-box-roll > #page-header-bg.fullscreen-header,
        .nectar_fullscreen_zoom_recent_projects,
        #nectar_fullscreen_rows:not(.afterLoaded) > div {
          height: 100vh;
        }

        .wpb_row.vc_row-o-full-height.top-level,
        .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
          min-height: 100vh;
        }';

                if( is_404() ) {
                    echo '.nectar_hook_404_content .wpb_row.vc_row-o-full-height > .col.span_12 {
            min-height: 100vh;
          }';
                }

                if( is_admin_bar_showing() ) {
                    echo '.admin-bar #page-header-wrap.fullscreen-header,
          .admin-bar #page-header-wrap.fullscreen-header #page-header-bg,
          .admin-bar .nectar_fullscreen_zoom_recent_projects,
          .admin-bar #nectar_fullscreen_rows:not(.afterLoaded) > div {
            height: calc(100vh - 32px);
          }
          .admin-bar .wpb_row.vc_row-o-full-height.top-level,
          .admin-bar .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
            min-height: calc(100vh - 32px);
          }';
                }

                if( $headerFormat !== 'left-header' &&
                    ! (has_action('nectar_hook_global_section_after_header_navigation') && nectar_is_contained_header() )) {
                    echo '#page-header-bg[data-alignment-v="middle"] .span_6 .inner-wrap,
          #page-header-bg[data-alignment-v="top"] .span_6 .inner-wrap,
          .blog-archive-header.color-bg .container {
            padding-top: ' . (intval($header_space) - $header_extra_space_to_remove + $contained_header_mod) . 'px;
          }
          #page-header-wrap.container #page-header-bg .span_6 .inner-wrap {
            padding-top: 0;
          }
          ';

                }

                echo '.nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
        .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container {
          height: calc(100vh + 2px)!important;
        }
        .admin-bar .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
        .admin-bar .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container {
          height: calc(100vh - 30px)!important;
        }


      }';

            // Mobile transparent header.
            if( (! empty($nectar_options['transparent-header']) &&
                $nectar_options['transparent-header'] === '1' &&
                $activate_transparency) ||
                nectar_is_contained_header()) {

                 $nectar_mobile_padding = ( $theme_skin === 'material' ) ? 10 : 25;

                 // OCM background specific.
                 $full_width_header = (! empty($nectar_options['header-fullwidth']) && $nectar_options['header-fullwidth'] === '1') ? true : false;
                 $ocm_menu_btn_color_non_compatible = ( 'ascend' === $theme_skin && true === $full_width_header ) ? true : false;

                 if( true !== $ocm_menu_btn_color_non_compatible &&
               isset($nectar_options['header-slide-out-widget-area-menu-btn-bg-color']) &&
               ! empty( $nectar_options['header-slide-out-widget-area-menu-btn-bg-color'] ) ) {
                 $nectar_mobile_padding = ( $theme_skin === 'material' ) ? 30 : 45;
                 }

                 if ( nectar_is_contained_header() ) {
                    $nectar_mobile_padding = 60;
                 }

                 if (! (has_action('nectar_hook_global_section_after_header_navigation') && nectar_is_contained_header())) {

                    if ( ! (nectar_is_yoast_breadcrumb_active() && nectar_is_contained_header()) ) {

                        echo '
            @media only screen and (max-width: 1024px) {

              #page-header-bg[data-alignment-v="middle"]:not(.fullscreen-header) .span_6 .inner-wrap,
              #page-header-bg[data-alignment-v="top"] .span_6 .inner-wrap,
              .blog-archive-header.color-bg .container {
                padding-top: ' . (intval($mobile_logo_height) + $nectar_mobile_padding) . 'px;
              }

              .vc_row.top-level.full-width-section:not(.full-width-ns) > .span_12,
              #page-header-bg[data-alignment-v="bottom"] .span_6 .inner-wrap {
                padding-top: ' . intval($mobile_logo_height) . 'px;
              }

            }

            @media only screen and (max-width: 690px) {
              .vc_row.top-level.full-width-section:not(.full-width-ns) > .span_12 {
                padding-top: ' . (intval($mobile_logo_height) + $nectar_mobile_padding) . 'px;
              }
              .vc_row.top-level.full-width-content .nectar-recent-posts-single_featured .recent-post-container > .inner-wrap {
                padding-top: ' . intval($mobile_logo_height) . 'px;
              }
            }';
                    }
                }

                 // When secondary header is visible.
                 if( $using_secondary === 'header_with_secondary' ) {
                     echo '
           @media only screen and (max-width: 1024px) and (min-width: 691px) {

             #page-header-bg[data-alignment-v="middle"]:not(.fullscreen-header) .span_6 .inner-wrap,
             #page-header-bg[data-alignment-v="top"] .span_6 .inner-wrap,
             .vc_row.top-level.full-width-section:not(.full-width-ns) > .span_12 {
               padding-top: ' . (intval($mobile_logo_height) + $nectar_mobile_padding + 40) . 'px;
             }

           }';
                 }

                 if( nectar_is_contained_header() ) {
                    echo '
          @media only screen and (max-width: 1024px) {
            .full-width-ns .nectar-slider-wrap .swiper-slide[data-y-pos="middle"] .content,
            .full-width-ns .nectar-slider-wrap .swiper-slide[data-y-pos="top"] .content {
              padding-top: 60px;
            }
          }';
                 } else {
                    echo '
          @media only screen and (max-width: 1024px) {
            .full-width-ns .nectar-slider-wrap .swiper-slide[data-y-pos="middle"] .content,
            .full-width-ns .nectar-slider-wrap .swiper-slide[data-y-pos="top"] .content {
              padding-top: 30px;
            }
          }';
                 }

             }

        }

        // Mobile page header fullscreen calcs.
        else {

            echo '@media only screen and (min-width: 1025px) {
        body #nectar-content-wrap.no-scroll {
          min-height:  calc(100vh - ' . esc_attr($header_space) . 'px);
          height: calc(100vh - ' . esc_attr($header_space) . 'px)!important;
        }
      }';

            echo '@media only screen and (min-width: 1025px) {
        #page-header-wrap.fullscreen-header,
        #page-header-wrap.fullscreen-header #page-header-bg,
        html:not(.nectar-box-roll-loaded) .nectar-box-roll > #page-header-bg.fullscreen-header,
        .nectar_fullscreen_zoom_recent_projects,
        #nectar_fullscreen_rows:not(.afterLoaded) > div {
          height: calc(100vh - ' . (intval($header_space) - 1) . 'px);
        }

        .wpb_row.vc_row-o-full-height.top-level,
        .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
          min-height: calc(100vh - ' . (intval($header_space) - 1) . 'px);
        }

        html:not(.nectar-box-roll-loaded) .nectar-box-roll > #page-header-bg.fullscreen-header {
          top: ' . esc_attr($header_space) . 'px;
        }';

                if( is_admin_bar_showing() ) {
                    echo '.admin-bar #page-header-wrap.fullscreen-header,
          .admin-bar #page-header-wrap.fullscreen-header #page-header-bg,
          .admin-bar .nectar_fullscreen_zoom_recent_projects,
          .admin-bar #nectar_fullscreen_rows:not(.afterLoaded) > div {
            height: calc(100vh - ' . (intval($header_space) - 1) . 'px - 32px);
          }
          .admin-bar .wpb_row.vc_row-o-full-height.top-level,
          .admin-bar .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
            min-height: calc(100vh - ' . (intval($header_space) - 1) . 'px - 32px);
          }';
                }

                echo '.nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
        .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container {
          height: calc(100vh - ' . (intval($header_space) - 2) . 'px)!important;
        }

        .admin-bar .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
        .admin-bar .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container  {
          height: calc(100vh - ' . (intval($header_space) - 2) . 'px - 32px)!important;
        }
      }

      .admin-bar[class*="page-template-template-no-header"] .wpb_row.vc_row-o-full-height.top-level,
      .admin-bar[class*="page-template-template-no-header"] .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
        min-height: calc(100vh - 32px);
      }
      body[class*="page-template-template-no-header"] .wpb_row.vc_row-o-full-height.top-level,
      body[class*="page-template-template-no-header"] .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
        min-height: 100vh;
      }';

        }

        // Extra padding on top level full width rows when using contained header size.
        $post_title_hidden = get_post_meta( $post->ID, '_nectar_blocks_hide_post_title', true );

        if ( $post_title_hidden === '1' && $activate_transparency ) {
          echo 'body.single-post.material[data-bg-header=true] .container-wrap {
            padding-top: 0px!important;
          }';
        }

        if( nectar_is_contained_header() && ( $post_title_hidden === '1' || true === $global_post_hide_title_vis ) ) {

            $first_row_inner = '.wp-block-nectar-blocks-row:is(:first-child) > .nectar-blocks-row__wrapper > .nectar-blocks-row__inner';
            // Global section after header section alters selector.
            if( has_action('nectar_hook_global_section_after_header_navigation')) {

                echo '.nectar_hook_global_section_after_header_navigation:first-of-type > .container > ' . $first_row_inner . ' {
          padding-top: ' . (intval($header_space) + 30) . 'px;
        }
        @media only screen and (max-width: 1024px) {
          .nectar_hook_global_section_after_header_navigation:first-of-type > .container > ' . $first_row_inner . '  {
            padding-top: ' . (intval($mobile_logo_height) + $nectar_mobile_padding) . 'px;
          }
        }';
            }
            else if( nectar_using_before_content_global_section() ) {
                // Second Global section which may be at the top of the page.
                echo '.nectar_hook_before_content_global_section:first-of-type > .container > ' . $first_row_inner . ' {
          padding-top: ' . (intval($header_space) + 30) . 'px;
        }
        @media only screen and (max-width: 1024px) {
          .nectar_hook_before_content_global_section:first-of-type > .container > ' . $first_row_inner . ' {
            padding-top: ' . (intval($mobile_logo_height) + $nectar_mobile_padding) . 'px;
          }

        }';

            }
            // Regular top level row.
            else if( ! nectar_is_yoast_breadcrumb_active() ) {
                echo '.nectar-content.main-content > ' . $first_row_inner . ',
            .nectar-content.main-content > .nectar-blocks-row__wrapper:is(:first-child) > .nectar-blocks-row__inner {
          padding-top: calc(' . (intval($header_space)) . 'px + max(calc(var(--container-padding)/3), 25px));
        }
        @media only screen and (max-width: 1024px) {
          .nectar-content.main-content > ' . $first_row_inner . ',
          .nectar-content.main-content > .nectar-blocks-row__wrapper:is(:first-child) > .nectar-blocks-row__inner {
            padding-top: calc(' . (nectar_get_mobile_header_height()) . 'px + 25px);
          }
        }';
            }

        }

     // Mobile fullscreen header/row height calcs.
    $nectar_mobile_browser_padding = 76;
    $nectar_mobile_padding = 23;
    $mobile_logo_height_header_calcs = $mobile_logo_height;

    if( $activate_transparency ) {
        $mobile_logo_height_header_calcs = 0;
        $nectar_mobile_padding = 1;
    }

    echo '@media only screen and (max-width: 1024px) {';

        if( $active_fullscreen_header ) {
            echo '.using-mobile-browser #page-header-wrap.fullscreen-header,
      .using-mobile-browser #page-header-wrap.fullscreen-header #page-header-bg {
        height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_browser_padding) . 'px);
      }';
        }
        echo '.using-mobile-browser #nectar_fullscreen_rows:not(.afterLoaded):not([data-mobile-disable="on"]) > div {
      height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_browser_padding) . 'px);
    }
    .using-mobile-browser .wpb_row.vc_row-o-full-height.top-level,
    .using-mobile-browser .wpb_row.vc_row-o-full-height.top-level > .col.span_12,
    [data-permanent-transparent="1"].using-mobile-browser .wpb_row.vc_row-o-full-height.top-level,
    [data-permanent-transparent="1"].using-mobile-browser .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
      min-height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_browser_padding) . 'px);
    }
    ';

        if( is_admin_bar_showing() ) {
            if( $active_fullscreen_header ) {
                echo '
        .admin-bar #page-header-wrap.fullscreen-header,
        .admin-bar #page-header-wrap.fullscreen-header #page-header-bg,';
            }
            echo 'html:not(.nectar-box-roll-loaded) .admin-bar .nectar-box-roll > #page-header-bg.fullscreen-header,
      .admin-bar .nectar_fullscreen_zoom_recent_projects,
      .admin-bar .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
      .admin-bar .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container,
      .admin-bar #nectar_fullscreen_rows:not(.afterLoaded):not([data-mobile-disable="on"]) > div  {
        height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_padding) . 'px - 46px);
      }
      .admin-bar .wpb_row.vc_row-o-full-height.top-level,
      .admin-bar .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
        min-height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_padding) . 'px - 46px);
      }
      ';
        } else {

            if( $active_fullscreen_header ) {
                echo '#page-header-wrap.fullscreen-header,
          #page-header-wrap.fullscreen-header #page-header-bg,';
            }
            echo '
       html:not(.nectar-box-roll-loaded) .nectar-box-roll > #page-header-bg.fullscreen-header,
       .nectar_fullscreen_zoom_recent_projects,
       .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
       .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container,
       #nectar_fullscreen_rows:not(.afterLoaded):not([data-mobile-disable="on"]) > div {
        height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_padding) . 'px);
      }
      .wpb_row.vc_row-o-full-height.top-level,
      .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {
        min-height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_padding) . 'px);
      }';
        }

    if( '1' === $perm_transparency ) {
      echo '[data-bg-header="true"][data-permanent-transparent="1"] #page-header-wrap.fullscreen-header,
      [data-bg-header="true"][data-permanent-transparent="1"] #page-header-wrap.fullscreen-header #page-header-bg,
      html:not(.nectar-box-roll-loaded) [data-bg-header="true"][data-permanent-transparent="1"] .nectar-box-roll > #page-header-bg.fullscreen-header,
      [data-bg-header="true"][data-permanent-transparent="1"] .nectar_fullscreen_zoom_recent_projects,
      [data-permanent-transparent="1"] .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded),
      [data-permanent-transparent="1"] .nectar-slider-wrap[data-fullscreen="true"]:not(.loaded) .swiper-container {
        height: 100vh;
      }

      [data-permanent-transparent="1"] .wpb_row.vc_row-o-full-height.top-level,
      [data-permanent-transparent="1"] .wpb_row.vc_row-o-full-height.top-level > .col.span_12 {	min-height: 100vh; }';
    }

        echo 'body[data-transparent-header="false"] #nectar-content-wrap.no-scroll {
      min-height:  calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_padding) . 'px);
      height: calc(100vh - ' . (intval($mobile_logo_height_header_calcs) + $nectar_mobile_padding) . 'px);
    }

  }';

        // Page full screen rows.
        global $post;
        $page_full_screen_rows_bg_color = (isset($post->ID)) ? get_post_meta($post->ID, '_nectar_full_screen_rows_overall_bg_color', true) : '#333333';
        $page_full_screen_rows_animation = (isset($post->ID)) ? get_post_meta($post->ID, '_nectar_full_screen_rows_animation', true) : '';

        if( $page_full_screen_rows_bg_color ) {
            echo '#nectar_fullscreen_rows {
        background-color: ' . esc_attr($page_full_screen_rows_bg_color) . ';
      }';
        }
        if( 'parallax' === $page_full_screen_rows_animation ) {
            echo '#nectar_fullscreen_rows > .wpb_row .full-page-inner-wrap {
        background-color: ' . esc_attr($page_full_screen_rows_bg_color) . ';
      }';
        }

        if( 'none' === $page_full_screen_rows_animation ) {
            echo '#nectar_fullscreen_rows {
        background-color: transparent;
      }';
        }

        global $woocommerce;
        // WooCommerce items.
        if( $woocommerce && ! empty($nectar_options['product_archive_bg_color']) ) {
            echo '.post-type-archive-product.woocommerce .container-wrap,
      .tax-product_cat.woocommerce .container-wrap {
        background-color: ' . esc_attr($nectar_options['product_archive_bg_color']) . ';
      } ';
        }

        if( $woocommerce && ! empty($nectar_options['product_tab_position']) && $nectar_options['product_tab_position'] === 'fullwidth' ||
           $woocommerce && ! empty($nectar_options['product_tab_position']) && $nectar_options['product_tab_position'] === 'fullwidth_centered' ) {
                 echo '.woocommerce.single-product #single-meta {
           position: relative!important;
           top: 0!important;
           margin: 0;
           left: 8px;
           height: auto;
         }

       .woocommerce.single-product #single-meta:after {
         display: block;
         content: " ";
         clear: both;
         height: 1px;
       }';
         }

         if( $woocommerce && ! empty($nectar_options['product_bg_color']) ) {
                echo '.woocommerce ul.products li.product.material,
        .woocommerce-page ul.products li.product.material {
          background-color: ' . esc_attr($nectar_options['product_bg_color']) . ';
        }';
         }

         if( $woocommerce && ! empty($nectar_options['product_minimal_bg_color']) ) {
            echo '.woocommerce ul.products li.product.minimal .product-wrap,
      .woocommerce ul.products li.product.minimal .background-color-expand,
      .woocommerce-page ul.products li.product.minimal .product-wrap,
      .woocommerce-page ul.products li.product.minimal .background-color-expand {
        background-color: ' . esc_attr($nectar_options['product_minimal_bg_color']) . ';
      }';

         }

        // Boxed theme option.
        if( ! empty($nectar_options['boxed_layout']) && $nectar_options['boxed_layout'] === '1' )  {

            $attachment = ( ! empty($nectar_options["background-attachment"]) ) ? $nectar_options["background-attachment"] : 'scroll';
            $position = ( ! empty($nectar_options["background-position"]) ) ? $nectar_options["background-position"] : '0% 0%';
            $repeat = ( ! empty($nectar_options["background-repeat"]) ) ? $nectar_options["background-repeat"] : 'repeat';
            $background_color = ( ! empty($nectar_options["background-color"]) ) ? $nectar_options["background-color"] : '#ffffff';

            echo '
       body {';
                if( ! empty($nectar_options["background_image"]['id']) || ! empty($nectar_options["background_image"]['url']) ) {
                    echo 'background-image: url("' . nectar_options_img($nectar_options["background_image"]) . '");';
                }
                echo 'background-position: ' . esc_attr($position) . ';
        background-repeat: ' . esc_attr($repeat) . ';
        background-color: ' . esc_attr($background_color) . '!important;
        background-attachment: ' . esc_attr($attachment) . ';';
                if( ! empty($nectar_options["background-cover"]) && $nectar_options["background-cover"] === '1' ) {
                    echo 'background-size: cover;
          -webkit-background-size: cover;';
                }

             echo '}
      ';
        }

        // Blog next post coloring
        if( is_singular('post') ) {

            $next_post = get_previous_post();
            if (! empty($next_post) ) {

                $blog_next_bg_color = get_post_meta($next_post->ID, '_nectar_header_bg_color', true);
                $blog_next_font_color = get_post_meta($next_post->ID, '_nectar_header_font_color', true);

                if(! empty($blog_next_font_color)){
                    echo '.blog_next_prev_buttons .col h3, .full-width-content.blog_next_prev_buttons > .col.span_12.dark h3, .blog_next_prev_buttons span {
            color: ' . esc_attr($blog_next_font_color) . ';
          }';
                }
                if(! empty($blog_next_bg_color)){
                    echo '.blog_next_prev_buttons {
            background-color: ' . esc_attr($blog_next_bg_color) . ';
          }';
                }
            }
        }

        // Search results list number count
        if( is_search() &&
                isset($nectar_options['search-results-layout']) &&
                in_array($nectar_options['search-results-layout'], ['list-with-sidebar','list-no-sidebar']) ) {

                $current_page_num = intval(get_query_var( 'paged', 1 ));
                $posts_per_page = intval(get_query_var( 'posts_per_page', 12 ));

                if( $posts_per_page > 1 && $current_page_num > 1 ) {

                    $current_page_num -= 1;

                    for($i = 0; $i <= $posts_per_page; $i++) {
                        echo 'body.search-results #search-results[data-layout*="list"] article:nth-child(' . $i . '):before {
              content: "' . esc_attr($i + ($posts_per_page * $current_page_num)) . '";
            }';
                    }

                }

        }

        // WooCommerce cart global sections
        if ( function_exists('is_cart') && is_cart() ) {
            echo '#nectar-content-wrap .row > .woocommerce .full-width-content,
      #nectar-content-wrap .row > .woocommerce .full-width-section .row-bg-wrap,
      #nectar-content-wrap .row > .woocommerce .full-width-section .nectar-parallax-scene,
      #nectar-content-wrap .row > .woocommerce .full-width-section > .nectar-shape-divider-wrap,
      #nectar-content-wrap .row > .woocommerce .full-width-section  > .video-color-overlay {
        margin-left: 0;
        left: 0;
        width: 100%;
      }
      #nectar-content-wrap .row > .woocommerce .nectar-global-section > .container {
        padding: 0;
      }';
        }

        // Page builder element styles.
        $portfolio_content = ( $post && isset($post->ID) ) ? get_post_meta( $post->ID, '_nectar_portfolio_extra_content', true ) : false;
        $portfolio_content_preview = ( $post && isset($post->ID) ) ? get_post_meta( $post->ID, '_nectar_portfolio_extra_content_preview', true ) : false;

        // WooCommerce.
        if( ! empty(NectarElAssets::$woo_shop_content) ) {
            echo NectarElDynamicStyles::generate_styles(NectarElAssets::$woo_shop_content);
        }
        else if( ! empty(NectarElAssets::$woo_taxonmy_content) ) {
            echo NectarElDynamicStyles::generate_styles(NectarElAssets::$woo_taxonmy_content);
        }
        if( ! empty(NectarElAssets::$woo_short_desc_content) ) {
            echo NectarElDynamicStyles::generate_styles(NectarElAssets::$woo_short_desc_content);
        }
        // Portfolio.
        if( is_singular( 'portfolio' ) && $portfolio_content ) {
            echo NectarElDynamicStyles::generate_styles($portfolio_content);

            // Previews.
            if( is_preview() && $portfolio_content_preview ) {
                echo NectarElDynamicStyles::generate_styles($portfolio_content_preview);
            }
        }
        // Everything else.
        else if( $post && isset($post->post_content) && ! is_archive() && ! is_home() ) {
          echo NectarElDynamicStyles::generate_styles($post->post_content);
        }

    // PUM
    if( function_exists('pum_get_all_popups') &&
        function_exists('pum_is_popup_loadable') &&
        ! is_admin() ) {

        $popups = pum_get_all_popups();

        if ( ! empty( $popups ) ) {

            foreach ( $popups as $popup ) {
              if ( isset($popup->ID) &&
                  pum_is_popup_loadable( $popup->ID ) &&
                  isset($popup->content) &&
                  ! empty($popup->content) ) {

                  echo NectarElDynamicStyles::generate_styles($popup->content);
              }
            }

         }

    }

        // Global template theme options.
    $theme_template_locations = NectarThemeManager::$global_seciton_options;
    foreach ($theme_template_locations as $key => $location) {

      if( isset($nectar_options[$location]) &&
          ! empty($nectar_options[$location]) ) {

          $template_ID = intval($nectar_options[$location]);
          $global_section_content_query = get_post($template_ID);

          if( isset($global_section_content_query->post_content) &&
              ! empty($global_section_content_query->post_content) ) {
                                // Clear existing styles.
                NectarElDynamicStyles::$element_css = [];
                                // Generate global section styles.
                echo NectarElDynamicStyles::generate_styles($global_section_content_query->post_content);

          }

      }

    } // End global section theme option loop.

        // Update assist.
        echo '.screen-reader-text, .nectar-skip-to-content:not(:focus) {
      border: 0;
      clip: rect(1px, 1px, 1px, 1px);
      clip-path: inset(50%);
      height: 1px;
      margin: -1px;
      overflow: hidden;
      padding: 0;
      position: absolute!important;
      width: 1px;
      word-wrap: normal!important;
    }';

        /* SVG image sizing */
        if ( false === apply_filters('nectar_bypass_svg_img_sizing', false) ) {
            echo '.row .col img:not([srcset]){
        width: auto;
      }
      .row .col img.img-with-animation.nectar-lazy:not([srcset]) {
        width: 100%;
      }';
        }
        else {
            echo '.row .col img:not([srcset]):not([src*="svg"]){
        width: auto;
      }
      .row .col img.img-with-animation.nectar-lazy:not([srcset]):not(.loaded) {
        width: 100%;
      }';
        }

        $dynamic_css = ob_get_contents();
        ob_end_clean();

        return nectar_quick_minify($dynamic_css);

    }
}

/**
 * Adds Lovelo to font list
 * @since 4.0
 */
if( ! function_exists('nectar_lovelo_font') ) {

    function nectar_lovelo_font() {
        /* A font fabric font - http://fontfabric.com/lovelo-font/ */
        $nectar_custom_font = "@font-face { font-family: 'Lovelo'; src: url('" . get_template_directory_uri() . "/css/fonts/Lovelo_Black.eot'); src: url('" . get_template_directory_uri() . "/css/fonts/Lovelo_Black.eot?#iefix') format('embedded-opentype'), url('" . get_template_directory_uri() . "/css/fonts/Lovelo_Black.woff') format('woff'),  url('" . get_template_directory_uri() . "/css/fonts/Lovelo_Black.ttf') format('truetype'), url('" . get_template_directory_uri() . "/css/fonts/Lovelo_Black.svg#loveloblack') format('svg'); font-weight: normal; font-style: normal; }";

        wp_add_inline_style( 'main-styles', $nectar_custom_font );
    }

}

$font_fields = [
    'navigation_font_family',
    'navigation_dropdown_font_family',
    'page_heading_font_family',
    'page_heading_subtitle_font_family',
    'off_canvas_nav_font_family',
    'off_canvas_nav_subtext_font_family',
    'body_font_family',
    'h1_font_family',
    'h2_font_family',
    'h3_font_family',
    'h4_font_family',
    'h5_font_family',
    'h6_font_family',
    'i_font_family',
    'label_font_family',
    'nectar_slider_heading_font_family',
    'home_slider_caption_font_family',
    'testimonial_font_family',
    'sidebar_footer_h_font_family',
    'team_member_h_font_family',
    'nectar_dropcap_font_family'];

foreach( $font_fields as $k => $v ) {

    if( isset($nectar_options[$v]['font-family']) && $nectar_options[$v]['font-family'] == 'Lovelo, sans-serif' ) {
        add_action( 'wp_enqueue_scripts', 'nectar_lovelo_font' );
        break;
    }

}
