<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
* Nectar Theme Manager.
*/

if( ! class_exists('NectarThemeManager') ) {

  class NectarThemeManager {
    private static $instance;

    public static $options = '';

    public static $skin = 'material';

    public static $ocm_style = '';

    public static $woo_product_filters = false;

    public static $colors = [];

    public static $available_theme_colors = [];

    public static $header_format = '';

    public static $column_gap = '';

    public static $header_hover_effect = '';

    public static $global_seciton_options = [
      'global-section-after-header-navigation',
      'global-section-above-footer'
    ];

    private function __construct() {

      $this->hooks();

    }

    /**
     * Initiator.
     */
    public static function get_instance() {
      if ( ! self::$instance ) {
        self::$instance = new self;
      }
      return self::$instance;
    }

    /**
     * Determines all theme settings
     * which are conditionally forced.
     */
    public static function setup() {

      self::$options = get_nectar_theme_options();

      $header_format = ( isset(self::$options['header_format']) ) ? self::$options['header_format'] : 'default';

      self::$header_format = $header_format;

      // OCM style.
      $theme_ocm_style = ( isset( self::$options['header-slide-out-widget-area-style'] ) && ! empty( self::$options['header-slide-out-widget-area-style'] ) ) ? self::$options['header-slide-out-widget-area-style'] : 'slide-out-from-right';
      $legacy_double_menu = ( function_exists('nectar_legacy_mobile_double_menu') ) ? nectar_legacy_mobile_double_menu() : false;

      if( true === $legacy_double_menu && in_array($theme_ocm_style, ['slide-out-from-right-hover', 'simple']) ) {
         $theme_ocm_style = 'slide-out-from-right';
      }

      self::$ocm_style = esc_html($theme_ocm_style);

      // Woo filter area.
      $product_filter_trigger = ( isset( self::$options['product_filter_area']) && '1' === self::$options['product_filter_area'] ) ? true : false;
            $main_shop_layout = ( isset( self::$options['main_shop_layout'] ) ) ? self::$options['main_shop_layout'] : 'no-sidebar';

            if( $main_shop_layout != 'right-sidebar' && $main_shop_layout != 'left-sidebar' ) {
                $product_filter_trigger = false;
            }

      self::$woo_product_filters = $product_filter_trigger;

      // Column Gap.
      self::$column_gap = ( isset( self::$options['column-spacing']) ) ? self::$options['column-spacing'] : 'default';

      self::$header_hover_effect = ( isset( self::$options['header-hover-effect'] ) ) ? self::$options['header-hover-effect'] : 'default';

      // Theme Colors.
      self::$available_theme_colors = [
        'accent-color' => 'Nectar Accent Color',
        'extra-color-1' => 'Nectar Extra Color #1',
        'extra-color-2' => 'Nectar Extra Color #2',
        'extra-color-3' => 'Nectar Extra Color #3'
      ];

      $custom_colors = apply_filters('nectar_additional_theme_colors', []);
      if( $custom_colors && ! empty($custom_colors) ) {
        $custom_colors = array_flip($custom_colors);
      }

      self::$available_theme_colors = array_merge(self::$available_theme_colors, $custom_colors);

      foreach( self::$available_theme_colors as $color => $display_name ) {

          self::$colors[$color] = [
            'display_name' => $display_name,
            'value' => ''
          ];

          if( isset( self::$options[$color]) && ! empty( self::$options[$color]) ) {
            self::$colors[$color]['value'] = self::$options[$color];
          }

      }

      // Overall Colors.
      if ( ! defined('NECTAR_BLOCKS_ROOT_DIR_PATH') ) {
        $overall_font_color = ( isset(self::$options['overall-font-color']) ) ? self::$options['overall-font-color'] : false;
      } else {
        $overall_font_color = 'var(--body-color, var(--dark))';
      }
      if( $overall_font_color ) {
        self::$colors['overall_font_color'] = $overall_font_color;
      }

    }

    public function hooks() {

      add_action('init', ['NectarThemeManager', 'setup']);

      // Store available post types in a transient
      add_action( 'wp_loaded', [ $this, 'set_available_post_types' ] );

      add_action( 'switch_theme', function() {
        delete_transient( 'nectar_available_post_types' );
      } );

      add_action( 'activated_plugin', function() {
          delete_transient( 'nectar_available_post_types' );
      } );

      add_action( 'deactivated_plugin', function() {
          delete_transient( 'nectar_available_post_types' );
      } );

    }

    public function set_available_post_types() {
      $post_types = get_post_types( [ 'public' => true ], 'objects' );
      $post_types = array_filter( $post_types, function( $post_type ) {
        return ! in_array( $post_type->name, [
          'attachment',
          'product', // products for now until we extend WooCommerce templates to accommodate.
          'elementor_library',
          'salient_g_sections',
          'nectar_sections',
          'nectar_templates',
          'wp_block',
          'page'
        ] );
      } );
      set_transient( 'nectar_available_post_types', $post_types, DAY_IN_SECONDS );
    }

    public static function get_active_special_locations() {
      $special_locations = get_option( 'nectar_global_section_special_locations', [] );
      return $special_locations;
    }

   /**
    * Determines if a special location is active.
    * @param string $location
    * @return mixed
    */
    public static function is_special_location_active($location) {
        $special_locations = self::get_active_special_locations();
        return isset($special_locations[$location]) ? $special_locations[$location] : false;
    }
  }

  /**
     * Initialize the NectarThemeManager class
     */
    NectarThemeManager::get_instance();
}
