<?php

/**
 * Enqueue styles
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
 * Register/Enqueue frontend CSS.
 *
 * @since 1.0
 */
function nectar_main_styles() {

        global $nectar_get_template_directory_uri;
        global $nectar_options;

         $nectar_using_VC_front_end_editor = (isset($_GET['vc_editable'])) ? sanitize_text_field($_GET['vc_editable']) : '';
         $nectar_using_VC_front_end_editor = ($nectar_using_VC_front_end_editor == 'true') ? true : false;

         $nectar_theme_version = nectar_get_theme_version();

        $nectar_dev_mode = apply_filters('nectar_dev_mode', false);
        $src_dir = ( $nectar_dev_mode == true ) ? 'src' : 'build';

         // Core.
         wp_register_style( 'main-styles', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/style.css', '', $nectar_theme_version );
         wp_register_style( 'main-styles-non-critical', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/style-non-critical.css', '', $nectar_theme_version );

         wp_register_style( 'nectar-smooth-scroll', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/plugins/lenis.css', '', $nectar_theme_version );

         // WooCommerce
         wp_register_style( 'nectar-product-style-minimal', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/woocommerce/product-style-minimal.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-product-style-classic', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/woocommerce/product-style-classic.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-product-style-text-on-hover', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/woocommerce/product-style-text-on-hover.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-product-style-material', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/woocommerce/product-style-material.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-woocommerce-non-critical', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/woocommerce/woocommerce-non-critical.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-woocommerce-single', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/woocommerce/product-single.css', '', $nectar_theme_version );
         wp_register_style( 'woocommerce', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/woocommerce.css', '', $nectar_theme_version );

        // Elements.
        wp_register_style( 'nectar-element-product-carousel', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/elements/element-product-carousel.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-element-recent-posts', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/elements/element-recent-posts.css', '', $nectar_theme_version );

         wp_register_style( 'iconsmind', $nectar_get_template_directory_uri . '/css/iconsmind.css', '', '12.5' );
         wp_register_style( 'iconsmind-core', $nectar_get_template_directory_uri . '/css/iconsmind-core.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-steadysets', $nectar_get_template_directory_uri . '/css/steadysets.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-brands', $nectar_get_template_directory_uri . '/css/nectar-brands.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-linecon', $nectar_get_template_directory_uri . '/css/linecon.css', '', $nectar_theme_version );

         // Header Formats.
         wp_register_style( 'nectar-header-layout-left', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-layout-left.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-header-layout-left-aligned', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-layout-menu-left-aligned.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-header-layout-centered-bottom-bar', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-layout-centered-bottom-bar.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-header-layout-centered-menu', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-layout-centered-menu.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-header-layout-centered-menu-under-logo', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-layout-centered-menu-under-logo.css', '', $nectar_theme_version );

         wp_register_style( 'nectar-header-layout-centered-logo-between-menu-alt', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-layout-centered-logo-between-menu-alt.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-header-secondary-nav', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-secondary-nav.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-header-perma-transparent', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/header/header-perma-transparent.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-single-styles', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/single.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-search-results', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/search.css', '', $nectar_theme_version );

         // Blog.
        wp_register_style( 'nectar-blog-masonry-core', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/blog/masonry-core.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-blog-masonry-classic-enhanced', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/blog/masonry-classic-enhanced.css', ['nectar-blog-masonry-core'], $nectar_theme_version );
        wp_register_style( 'nectar-blog-masonry-meta-overlaid', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/blog/masonry-meta-overlaid.css', ['nectar-blog-masonry-core'], $nectar_theme_version );
        wp_register_style( 'nectar-blog-auto-masonry-meta-overlaid-spaced', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/blog/auto-masonry-meta-overlaid-spaced.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-blog-standard-featured-left', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/blog/standard-featured-left.css', '', $nectar_theme_version );

         // Off canvas menu styles.
        wp_register_style( 'nectar-ocm-core', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/off-canvas/core.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-ocm-slide-out-right-hover', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/off-canvas/slide-out-right-hover.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-ocm-fullscreen-legacy', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/off-canvas/fullscreen-legacy.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-ocm-fullscreen-split', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/off-canvas/fullscreen-split.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-ocm-simple', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/off-canvas/simple-dropdown.css', '', $nectar_theme_version );
        wp_register_style( 'nectar-ocm-slide-out-right-material', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/off-canvas/slide-out-right-material.css', '', $nectar_theme_version );

         // Third Party.
         wp_register_style('nectar-blocks-swiper', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/plugins/swiper.css', [], '9.4.1');
         wp_register_style( 'select2', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/plugins/select2.css', '', '4.0.1' );

         wp_register_style( 'responsive', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/responsive.css', '', $nectar_theme_version );
         wp_register_style( 'nectar-blocks-theme-skin', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/nectar-blocks-theme-skin.css', '', $nectar_theme_version );

         global $post;

         if ( ! is_object( $post ) ) {
            $post = (object) [
                'post_content' => ' ',
                'ID' => ' ',
            ];
        }

     // Grid system.
     if( function_exists('nectar_use_flexbox_grid') && true === nectar_use_flexbox_grid() ) {
         /* NectarBlocks provides a modern flexbox grid system as of v10.6 as long
         as the NectarBlocks core and NectarBlocks page builder plugins are up to date. */
         $nectar_modern_grid_compat = true;
     } else {
         $nectar_modern_grid_compat = false;
     }

    // wp_enqueue_style( 'nectar-grid-system' );

     // Smooth scrolling
     if ( isset( $nectar_options['smooth-scroll'] ) && $nectar_options['smooth-scroll'] === '1' ) {
        wp_enqueue_style( 'nectar-smooth-scroll' );
    }

     // Main NectarBlocks styles.
     wp_enqueue_style( 'main-styles' );

     // Header layouts.
     $header_format = ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default';

     if( $header_format === 'left-header' ) {
         wp_enqueue_style( 'nectar-header-layout-left' );
     }
    else if( $header_format === 'menu-left-aligned' ) {
        wp_enqueue_style( 'nectar-header-layout-left-aligned' );
    }
     else if( $header_format === 'centered-menu-bottom-bar' ) {
         wp_enqueue_style( 'nectar-header-layout-centered-bottom-bar' );
     }
     else if ( $header_format === 'centered-menu-under-logo' ) {
         wp_enqueue_style( 'nectar-header-layout-centered-menu-under-logo' );
     }
     else if ( $header_format === 'centered-menu' ) {
         wp_enqueue_style( 'nectar-header-layout-centered-menu' );
     }
     else if( $header_format === 'centered-logo-between-menu-alt' ) {
         wp_enqueue_style( 'nectar-header-layout-centered-logo-between-menu-alt' );
     }

    // Secondary navigation bar.
    $header_secondary_format = ( ! empty( $nectar_options['header_layout'] ) ) ? $nectar_options['header_layout'] : 'standard';
    if( $header_secondary_format === 'header_with_secondary') {
        wp_enqueue_style( 'nectar-header-secondary-nav' );
    }

     // Permanent transparent navigation option.
     $header_trans = ( ! empty( $nectar_options['transparent-header'] ) ) ? $nectar_options['transparent-header'] : '0';
     $header_perma_trans = ( ! empty( $nectar_options['header-permanent-transparent'] ) ) ? $nectar_options['header-permanent-transparent'] : '0';

     if( $header_trans === '1' && $header_perma_trans === '1' ) {
         wp_enqueue_style( 'nectar-header-perma-transparent' );
     }

     // Single posts.
     if( is_single() && ! is_singular( 'product' ) ) {
         wp_enqueue_style( 'nectar-single-styles' );
     }

     // Product Carousel.
     if ( NectarElAssets::locate(['[nectar_woo_products']) ) {
         if( NectarElAssets::locate(['carousel="1"']) || NectarElAssets::locate(["carousel='1'"]) ) {
             wp_enqueue_style( 'nectar-element-product-carousel' );
         }
     }

     // Single post using related posts.
     $nectar_using_related_posts = ( ! empty( $nectar_options['blog_related_posts'] ) ) ? $nectar_options['blog_related_posts'] : 'off';
     if( is_single() && $nectar_using_related_posts === '1') {
         wp_enqueue_style( 'nectar-element-recent-posts' );
     }

     if( defined('WPCF7_VERSION') ) {
        wp_enqueue_style( 'nectar-cf7', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/cf7.css', '', $nectar_theme_version );
    }
    if( defined('WPFORMS_VERSION') ) {
        wp_enqueue_style( 'nectar-wpforms', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/wpforms.css', '', $nectar_theme_version );
    }
    if( class_exists( 'bbPress' ) ) {
        wp_enqueue_style( 'nectar-basic-bbpress', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/bbpress.css', '', $nectar_theme_version );
    }
    if( class_exists( 'BuddyPress' ) ) {
        wp_enqueue_style( 'nectar-basic-buddypress', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/buddypress.css', '', $nectar_theme_version );
    }
    if( class_exists( 'Tribe__Main' ) ) {
        wp_enqueue_style( 'nectar-basic-events-calendar', $nectar_get_template_directory_uri . '/css/' . $src_dir . '/third-party/events-calendar.css', '', $nectar_theme_version );
    }

    // Icons
    if ( NectarElAssets::locate(['steadysets-']) ) {
        wp_enqueue_style('nectar-steadysets');
    }
    if( NectarElAssets::locate(['linecon']) ) {
        wp_enqueue_style('nectar-linecon');
    }
    if( NectarElAssets::locate(['nectar-brands']) ) {
        wp_enqueue_style('nectar-brands');
    }

    // Load Google fonts from theme options.
    // $google_fonts = Nectar_Dynamic_Fonts::create_google_fonts_link();

    // if ( $google_fonts ) {
    //   wp_enqueue_style( 'nectar-google-fonts', $google_fonts, [], null );
    // }

    // Remove WP CSS.
    if( isset($nectar_options['rm-block-editor-css']) &&
            ! empty($nectar_options['rm-block-editor-css']) &&
            '1' === $nectar_options['rm-block-editor-css'] ) {

        $post_content_length = ( $post && isset($post->post_content) ) ? strlen( $post->post_content ) : 0;

        if ( ! NectarElAssets::locate(['<!-- wp:']) || $post_content_length < 100 ) {
            wp_dequeue_style( 'wp-block-library' );
            wp_dequeue_style( 'wp-block-library' );
            wp_dequeue_style( 'wc-block-style' );
            wp_dequeue_style( 'wc-blocks-style' );
        }

    }

}

add_action( 'wp_enqueue_scripts', 'nectar_main_styles' );

/**
 * Page specific frontend CSS.
 *
 * @since 1.0
 */
function nectar_page_sepcific_styles() {

    global $post;
    global $nectar_options;

    if ( ! is_object( $post ) ) {
        $post = (object) [
            'post_content' => ' ',
            'ID' => ' ',
        ];
    }

    // Blog std style containing image gallery grid - non archive.
    if ( NectarElAssets::locate(['[nectar_blog']) && NectarElAssets::locate(['layout="std-blog-']) && NectarElAssets::locate(['blog_standard_style="classic']) ||
        NectarElAssets::locate(['[nectar_blog']) && NectarElAssets::locate(['layout="std-blog-']) && NectarElAssets::locate(['blog_standard_style="minimal']) ) {
        wp_enqueue_style( 'nectar-portfolio' );
    }

    // Blog styles - page builder element.
    $nectar_using_related_posts = ( ! empty( $nectar_options['blog_related_posts'] ) ) ? $nectar_options['blog_related_posts'] : 'off';
    $nectar_related_posts_style = ( isset( $nectar_options['blog_related_posts_style'] ) && ! empty( $nectar_options['blog_related_posts_style'] ) ) ? $nectar_options['blog_related_posts_style'] : 'material';

    $posttype = get_post_type( $post );
    $nectar_on_post_archive_check = ( is_archive() || is_author() || is_category() || is_home() || is_tag() );
    $nectar_blog_type = ( ! empty( $nectar_options['blog_type'] ) ) ? $nectar_options['blog_type'] : 'masonry-blog-fullwidth';
    $nectar_blog_std_style = ( ! empty( $nectar_options['blog_standard_type'] ) ) ? $nectar_options['blog_standard_type'] : 'featured_img_left';
    $nectar_blog_masonry_style = ( ! empty( $nectar_options['blog_masonry_type'] ) ) ? $nectar_options['blog_masonry_type'] : 'auto_meta_overlaid_spaced';

    // Archives.
    $using_post_grid_archive = has_action('nectar_template_archive');

    // Portfolio
    $nectar_on_portfolio_archive_check = ( is_post_type_archive( 'nectar_portfolio' ) || is_tax( 'portfolio_category' ) || is_tax( 'portfolio_tag' ) );
    if( $nectar_on_portfolio_archive_check && ! $using_post_grid_archive ) {
        wp_enqueue_style( 'nectar-blog-masonry-core' );
        wp_enqueue_style( 'nectar-blog-auto-masonry-meta-overlaid-spaced' );

    } // End archive check.

    // Blog
    if( $nectar_on_post_archive_check && ! $using_post_grid_archive ) {

        //// Masonry Styles.
        if( $nectar_blog_type === 'masonry-blog-sidebar' ||
            $nectar_blog_type === 'masonry-blog-fullwidth' ||
          $nectar_blog_type === 'masonry-blog-full-screen-width' ) {

        //// Core.
        if( 'classic' === $nectar_blog_masonry_style ||
            'material' === $nectar_blog_masonry_style ) {
          wp_enqueue_style( 'nectar-blog-masonry-core' );
        }

                //// Classic enhanced.
                if( 'classic_enhanced' === $nectar_blog_masonry_style ) {
                    wp_enqueue_style( 'nectar-blog-masonry-classic-enhanced' );
                }

                //// Meta Overlaid.
                if( 'meta_overlaid' === $nectar_blog_masonry_style ) {
                    wp_enqueue_style( 'nectar-blog-masonry-meta-overlaid' );
                }

                //// Auto Masonry Meta Overlaid.
                if( 'auto_meta_overlaid_spaced' === $nectar_blog_masonry_style ) {
                    wp_enqueue_style( 'nectar-blog-auto-masonry-meta-overlaid-spaced' );

          // remove container padding.
          if( 'post' === $posttype && $nectar_blog_type === 'masonry-blog-full-screen-width' ) {
            $n_auto_masonry_meta_overlaid_spaced_css = '#nectar-content-wrap.container-wrap { padding-top: 0px!important; }';
            wp_add_inline_style( 'nectar-blog-auto-masonry-meta-overlaid-spaced', $n_auto_masonry_meta_overlaid_spaced_css );
          }
                }

        }
        //// Standard Styles.
        else {

            if( 'featured_img_left' === $nectar_blog_std_style ) {
                wp_enqueue_style( 'nectar-blog-standard-featured-left' );
            }

        }

    } // End archive check.

    //// Related Posts
    if( '1' === $nectar_using_related_posts && is_single() ) {

        if( 'classic_enhanced' === $nectar_related_posts_style ) {
            wp_enqueue_style( 'nectar-blog-masonry-classic-enhanced' );
        }

    }

    // Blog std style containing image gallery grid - archive.
    if ( $nectar_on_post_archive_check ) {

        if ( $nectar_blog_type === 'std-blog-sidebar' || $nectar_blog_type === 'std-blog-fullwidth' ) {
            //// Standard styles that could contain gallery sliders.
            if ( $nectar_blog_std_style === 'classic' || $nectar_blog_std_style === 'minimal' ) {
                 wp_enqueue_style( 'nectar-portfolio' );
            }
        }
    }

    // Responsive.
    wp_enqueue_style( 'responsive' );

    // WooCommerce.
    if ( function_exists( 'is_woocommerce' ) ) {

        // Product styles
        if( isset($nectar_options['product_style']) && 'classic' === $nectar_options['product_style'] ) {
            wp_enqueue_style( 'nectar-product-style-classic' );
        }
        else if( isset($nectar_options['product_style']) && 'text_on_hover' === $nectar_options['product_style'] ) {
            wp_enqueue_style( 'nectar-product-style-text-on-hover' );
        }
        else if( isset($nectar_options['product_style']) && 'material' === $nectar_options['product_style'] ) {
            wp_enqueue_style( 'nectar-product-style-material' );
        }
        else if( isset($nectar_options['product_style']) && 'minimal' === $nectar_options['product_style'] ) {
            wp_enqueue_style( 'nectar-product-style-minimal' );
        }
        else {
            wp_enqueue_style( 'nectar-product-style-classic' );
        }

        wp_enqueue_style( 'woocommerce' );

        if( is_product() ) {
            wp_enqueue_style( 'nectar-woocommerce-single' );
            if( isset( $nectar_options['single_product_related_upsell_carousel'] ) && '1' === $nectar_options['single_product_related_upsell_carousel'] ) {
                wp_enqueue_style( 'nectar-element-product-carousel' );
                wp_enqueue_style( 'nectar-blocks-swiper' );
            }
        }

        /* Compatibility fix for when plugins enqueue selectWoo
        https://github.com/woocommerce/selectWoo/issues/41 */
        if ( wp_script_is('selectWoo', 'enqueued')) {
            $select_woo_css = '.woocommerce div.product form.variations_form .fancy-select-wrap {
				position: relative;
			 }
			 .woocommerce div.product form.variations_form .select2-container--open:not(.select2) {
				top: 105%!important;
				min-width: 150px;
			 }';
            wp_add_inline_style( 'main-styles', $select_woo_css );
        }

    }

    $fancy_rcs = ( ! empty( $nectar_options['form-fancy-select'] ) ) ? $nectar_options['form-fancy-select'] : 'default';
    if ( $fancy_rcs === '1' ) {
        wp_enqueue_style( 'select2' );
    }

    // Portfolio template inline styles.
    if( is_page_template( 'template-portfolio.php' ) ) {

        $nectar_portfolio_archive_layout = ( ! empty($nectar_options['main_portfolio_layout']) ) ? $nectar_options['main_portfolio_layout'] : '3';
        $nectar_inline_filters = ( ! empty( $nectar_options['portfolio_inline_filters'] ) && $nectar_options['portfolio_inline_filters'] === '1' ) ? '1' : '0';
        $nectar_portfolio_archive_bg = get_post_meta( $post->ID, '_nectar_header_bg', true );

        $nectar_portfolio_css = '.page-template-template-portfolio-php .row .col.section-title h1{
		  margin-bottom:0
		}';

        if( $nectar_portfolio_archive_layout === 'fullwidth' ) {
            $nectar_portfolio_css .= '.container-wrap { padding-bottom: 0px!important; } #call-to-action .triangle { display: none; }';
        }

        if( $nectar_portfolio_archive_layout === 'fullwidth' && ! empty($nectar_portfolio_archive_bg) ) {
            $nectar_portfolio_css .= '.container-wrap { padding-top: 0px!important; }';
        }

        if( $nectar_inline_filters === '1' && empty($nectar_portfolio_archive_bg) ) {
            $nectar_portfolio_css .= '.page-header-no-bg { display: none; }
			.container-wrap { padding-top: 0px!important; }
			body #portfolio-filters-inline { margin-top: -50px!important; padding-top: 5.8em!important; }';
        }

        if( $nectar_inline_filters === '1' && empty($nectar_portfolio_archive_bg) && $nectar_portfolio_archive_layout != 'fullwidth') {
            $nectar_portfolio_css .= '#portfolio-filters-inline.non-fw { margin-top: -37px!important; padding-top: 6.5em!important;}';
        }

        if( $nectar_inline_filters === '1' && ! empty($nectar_portfolio_archive_bg) && $nectar_portfolio_archive_layout != 'fullwidth') {
            $nectar_portfolio_css .= '.container-wrap { padding-top: 3px!important; }';
        }

        wp_add_inline_style( 'main-styles', $nectar_portfolio_css );

    }

    // Search template inline styles.
    if( is_search() ) {

        wp_enqueue_style( 'nectar-search-results' );

        $search_results_header_bg_color = ( ! empty( $nectar_options['search-results-header-bg-color'] ) ) ? $nectar_options['search-results-header-bg-color'] : '#f4f4f4';
        $search_results_header_font_color = ( ! empty( $nectar_options['search-results-header-font-color'] ) ) ? $nectar_options['search-results-header-font-color'] : '#000000';

        $nectar_search_css = '
		body:not(.archive) #page-header-bg {
			background-color: ' . $search_results_header_bg_color . ';
		}
		body:not(.archive) #page-header-bg h1, #page-header-bg .result-num {
			color: ' . $search_results_header_font_color . ';
		}
		';

        if( nectar_is_contained_header() ) {
            $nectar_search_css .= '.search-no-results #search-results  {
				padding-top: 8%;
			}';
        }

        wp_add_inline_style( 'main-styles', $nectar_search_css );
    }

    // 404 template inline styles.
    if( is_404() ) {

        $page_404_bg_color = ( ! empty( $nectar_options['page-404-bg-color'] ) ) ? $nectar_options['page-404-bg-color'] : '';
        $page_404_font_color = ( ! empty( $nectar_options['page-404-font-color'] ) ) ? $nectar_options['page-404-font-color'] : '';
        $page_404_bg_image_overlay_color = ( ! empty( $nectar_options['page-404-bg-image-overlay-color'] ) ) ? $nectar_options['page-404-bg-image-overlay-color'] : '';

        $nectar_404_css = '
		#error-404{
		  text-align:center;
		  padding: 10% 0;
		  position: relative;
		  z-index: 10;
		}
		body.error {
		  padding: 0;
		}
		body #error-404[data-cc="true"] h1,
		body #error-404[data-cc="true"] h2,
		body #error-404[data-cc="true"] p {
		  color: inherit;
		}
		body.error404 .error-404-bg-img,
		body.error404 .error-404-bg-img-overlay {
		  position: absolute;
		  top: 0;
		  left: 0;
		  width: 100%;
		  height: 100%;
		  background-size: cover;
		  background-position: 50%;
		  z-index: 1;
		}
		body.error404 .error-404-bg-img-overlay {
		  opacity: 0.8;
		}

		body #nectar-content-wrap #error-404 h1 {
		  font-size:250px;
		  line-height:250px;
		}
		body #nectar-content-wrap #error-404 h2 {
		  font-size:54px;
		}
		body #error-404 .nectar-button {
		  margin-top: 50px;
		  align-items: center;
		}

		body.error404 .main-content > .col.span_12 {
			padding-bottom: 0;
		}

		#error-404 .nectar-button {
			display: inline-flex;
		}

		@media only screen and (max-width : 690px) {

			body .row #error-404 h1,
			body #nectar-content-wrap #error-404 h1 {
				font-size: 150px;
				line-height: 150px;
			}

			body #nectar-content-wrap #error-404 h2 {
				font-size: 32px;
			}

			body .row #error-404 {
				margin-bottom: 0;
			}
		}
		';

        if ( ! empty( $page_404_bg_color ) ) {
            $nectar_404_css .= 'html .error404 .container-wrap {
				background-color: ' . $page_404_bg_color . ';
			}';
        }

        if ( ! empty( $page_404_bg_image_overlay_color ) ) {
            $nectar_404_css .= '.error404 .error-404-bg-img-overlay {
				background-color: ' . $page_404_bg_image_overlay_color . ';
			}';
        }
        if ( ! empty( $page_404_font_color ) ) {
            $nectar_404_css .= '.error404 #error-404,
			.error404 #error-404 h1,
			.error404 #error-404 h2 {
				color: ' . $page_404_font_color . ';
			}';
        }

        wp_add_inline_style( 'main-styles', $nectar_404_css );
    }

    // Sidebar templates
    if( is_page_template( 'page-sidebar.php' ) || is_page_template( 'page-left-sidebar.php' ) ) {

        $sidebar_template_css = 'body.page-template-page-sidebar-php .main-content > .post-area,
		body.page-template-page-sidebar-php .main-content > #sidebar,
		body.page-template-page-left-sidebar-php .main-content >.post-area,
		body.page-template-page-left-sidebar-php .main-content >#sidebar{
		  margin-top:30px
		}';

        wp_add_inline_style( 'main-styles', $sidebar_template_css );
    }

    // Legacy Dual Mobile Menu.
    $legacy_double_menu = nectar_legacy_mobile_double_menu();
    if( true === $legacy_double_menu ) {

        $nectar_dual_mobile_menu = '@media only screen and (max-width: 1024px) and (min-width: 1px) {
			#nectar-nav[data-has-menu="true"] #top .span_3 .nectar-ocm-trigger-open {
				display: inline-block;
				position: absolute;
				left: 0;
				top: 50%;
				transform: translateY(-50%);
			}
			#nectar-nav[data-has-menu="true"] #top .span_3 {
				text-align: center!important;
			}
		}';

        wp_add_inline_style( 'main-styles', $nectar_dual_mobile_menu );
    }

}

add_action( 'wp_enqueue_scripts', 'nectar_page_sepcific_styles' );

if( ! function_exists('nectar_preload_key_requests') ) {
    function nectar_preload_key_requests() {

        global $nectar_options;
        global $nectar_get_template_directory_uri;

        if( isset($nectar_options['typography_font_swap']) && '1' === $nectar_options['typography_font_swap'] ) {

            // Icomoon.
            echo '<link rel="preload" href="' . esc_attr($nectar_get_template_directory_uri) . '/css/fonts/icomoon.woff?v=1.6" as="font" type="font/woff" crossorigin="anonymous">';
        }
    }

}
add_action( 'wp_head', 'nectar_preload_key_requests', 5 );

/**
 * Non rendering blocking CSS.
 *
 * @since 1.0
 */
 if( ! function_exists('nectar_deferred_styles') ) {
    function nectar_deferred_styles() {

        $nectar_options = get_nectar_theme_options();

        wp_enqueue_style('main-styles-non-critical');

         // WooCommerce.
         if ( function_exists( 'is_woocommerce' ) ) {
             wp_enqueue_style( 'nectar-woocommerce-non-critical');
         }

         // Lightbox.
         $lightbox_script = ( ! empty( $nectar_options['lightbox_script'] ) ) ? $nectar_options['lightbox_script'] : 'magnific';
         if ( $lightbox_script === 'pretty_photo' ) {
             $lightbox_script = 'magnific';
         }
         if ( $lightbox_script === 'magnific' ) {
             wp_enqueue_style( 'magnific' );
         } elseif ( $lightbox_script === 'fancybox' ) {
             wp_enqueue_style( 'fancyBox' );
         }

         // Off canvas menu.
         wp_enqueue_style( 'nectar-ocm-core' );

         $header_off_canvas_style = ( isset( $nectar_options['header-slide-out-widget-area-style'] ) ) ? $nectar_options['header-slide-out-widget-area-style'] : 'slide-out-from-right';

         $legacy_double_menu = nectar_legacy_mobile_double_menu();

         if( $header_off_canvas_style === 'slide-out-from-right-hover' ) {
            wp_enqueue_style( 'nectar-ocm-slide-out-right-hover' );
         }
         else if( $header_off_canvas_style === 'fullscreen' ||
                  $header_off_canvas_style === 'fullscreen-alt' ) {
            wp_enqueue_style( 'nectar-ocm-fullscreen-legacy' );
         }
         else if( $header_off_canvas_style === 'fullscreen-split' ) {
             wp_enqueue_style( 'nectar-ocm-fullscreen-split' );
         }
         else if( $header_off_canvas_style === 'slide-out-from-right' ) {
            wp_enqueue_style('nectar-ocm-slide-out-right-material');

         }

         if( $header_off_canvas_style === 'simple' || true === $legacy_double_menu ) {
             wp_enqueue_style( 'nectar-ocm-simple' );
         }

    }
}
add_action( 'wp_footer', 'nectar_deferred_styles' );

if( ! function_exists('nectar_deferred_style_list') ) {
    function nectar_deferred_style_list() {
        return [
            'main-styles-non-critical',
            'nectar-woocommerce-non-critical',
            'nectar-ocm-simple',
            'nectar-ocm-fullscreen-split',
            'nectar-ocm-slide-out-right-material',
            'nectar-ocm-fullscreen-legacy',
            'nectar-ocm-slide-out-right-hover',
            'nectar-ocm-core',
            'fancyBox',
            'magnific',
        ];

    }
}

if( ! function_exists('nectar_deferred_mod_style_attrs') ) {

    function nectar_deferred_mod_style_attrs($tag, $handle) {

        $deferred_styles = nectar_deferred_style_list();

        if ( in_array($handle, $deferred_styles) ) {

            $modded_stylesheet = str_replace( '<link', '<link data-pagespeed-no-defer data-nowprocket data-wpacu-skip data-no-optimize data-noptimize', $tag );

            return $modded_stylesheet;
        }

        return $tag;
    }
}

// Modify deferred stylesheets to exclude from performance plugins
add_filter( 'style_loader_tag', 'nectar_deferred_mod_style_attrs', 10, 2 );

if( ! function_exists('nectar_deferred_styles_slice') ) {

    function nectar_deferred_styles_slice($stylesheets) {

        $deferred_styles = nectar_deferred_style_list();

        foreach($stylesheets as $key => $style) {

            foreach( $deferred_styles as $nectar_stylesheet ) {
                if(  strpos($style, $nectar_stylesheet) !== false ) {
                    unset($stylesheets[$key]);
                }
            }

        }

        return $stylesheets;
    }

}

if( ! function_exists('nectar_deferred_styles_add') ) {

    function nectar_deferred_styles_add($stylesheets) {

        $deferred_styles = nectar_deferred_style_list();
        foreach( $deferred_styles as $style ) {
            $stylesheets[] = $style;
        }
        return $stylesheets;
    }

}

if( ! function_exists('nectar_deferred_styles_add_string') ) {

    function nectar_deferred_styles_add_string() {

        $string = '';
        $stylesheets = [];
        $deferred_styles = nectar_deferred_style_list();

        foreach( $deferred_styles as $style ) {
            $stylesheets[] = $style;
        }
        return implode(',', $stylesheets);
    }

}

// W3 Total Cache
add_filter( 'w3tc_minify_css_style_tags', 'nectar_deferred_styles_slice', 10, 2);

// Siteground Optimizer
add_filter('sgo_css_minify_exclude', 'nectar_deferred_styles_add', 10);
add_filter('sgo_css_combine_exclude', 'nectar_deferred_styles_add', 10);

// Clearify
add_filter('wmac_filter_css_exclude', 'nectar_deferred_styles_add_string', 10);

/**
 * Allow users to disable default WP emojis
 *
 * @since 13.0
 */
 if ( ! function_exists( 'nectar_disable_emojis_dns_prefetch' ) ) {

    function nectar_disable_emojis_dns_prefetch( $urls, $relation_type ) {

        if ( 'dns-prefetch' === $relation_type ) {
            foreach ( $urls as $key => $url ) {
                if (  false !== strpos( $url, 'https://s.w.org/images/core/emoji' ) ) {
                    unset( $urls[$key] );
                }
            }
        }

        return $urls;
    }

 }

if ( ! function_exists( 'nectar_disable_emojis' ) ) {
 function nectar_disable_emojis() {

        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );

        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );

        add_filter( 'wp_resource_hints', 'nectar_disable_emojis_dns_prefetch', 10, 2 );

    }
}

global $nectar_options;
if( isset($nectar_options['rm-wp-emojis']) &&
        ! empty($nectar_options['rm-wp-emojis']) &&
        '1' === $nectar_options['rm-wp-emojis'] ) {

        add_action( 'init', 'nectar_disable_emojis' );
}

