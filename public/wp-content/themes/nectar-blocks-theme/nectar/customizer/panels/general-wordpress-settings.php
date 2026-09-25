<?php

/**
 * Customizer Core section.
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Core customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_General_WP_Settings {
  private static function get_title() {
    return [
      'id' => 'general-WP-settings-title',
      'settings' => [
        'type' => 'nectar-title',
        'title' => esc_html__( 'General WordPress Pages', 'nectar-blocks-theme' ),
        'priority' => 36,
      ]
    ];
  }

  public static function get_partials() {
    return [
      self::get_title()
    ];
  }

  public static function get_kirki_partials() {
    return [
      self::get_search_results_template(),
      self::get_404_template()
    ];
  }

  public static function get_search_results_template() {
    $controls = [
      [
        'id' => 'search-results-layout',
        'type' => 'select',
        'title' => esc_html__('Layout', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('The layout for your search results.', 'nectar-blocks-theme'),
        'options' => [
          "default" => esc_html__("Grid & Sidebar", 'nectar-blocks-theme'),
          "masonry-no-sidebar" => esc_html__("Grid No Sidebar", 'nectar-blocks-theme'),
          "list-with-sidebar" => esc_html__("List & Sidebar", 'nectar-blocks-theme'),
          "list-no-sidebar" => esc_html__("List No Sidebar", 'nectar-blocks-theme')
        ],
        'default' => 'masonry-no-sidebar'
      ],
      [
        'id' => 'search-results-header-bg-color',
        'type' => 'color',
        'title' => esc_html__('Header Background Color', 'nectar-blocks-theme'),
        'subtitle' => '',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('search-results-header-bg-color'),
        'transparent' => false,
        'default' => '#f7f8fb'
      ],
      [
        'id' => 'search-results-header-font-color',
        'type' => 'color',
        'title' => esc_html__('Header Font Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Default is #000000', 'nectar-blocks-theme'),
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('search-results-header-font-color'),
        'transparent' => false,
      ],
      [
        'id' => 'search-results-header-bg-image',
        'type' => 'media',
        'title' => esc_html__('Header Background Image', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Upload an optional background that will be used on your search results page', 'nectar-blocks-theme'),
        'desc' => ''
      ]
    ];

    return [
      'section_id' => 'search-results-template-section',
      'settings' => [
        'title' => esc_html__( 'Search Results Template', 'nectar-blocks-theme' ),
        'priority' => 37
      ],
      'controls' => $controls
    ];
  }

  public static function get_404_template() {
    $controls = [
      [
        'id' => 'page-404-bg-color',
        'type' => 'color',
        'title' => esc_html__('Background Color', 'nectar-blocks-theme')
      ],
      [
        'id' => 'page-404-font-color',
        'type' => 'color',
        'title' => esc_html__('Font Color', 'nectar-blocks-theme')
      ],
      [
        'id' => 'page-404-bg-image',
        'type' => 'media',
        'title' => esc_html__('Background Image', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Upload an optional background that will be used on the 404 page', 'nectar-blocks-theme')
      ],
      [
        'id' => 'page-404-bg-image-overlay-color',
        'type' => 'color',
        'title' => esc_html__('Background Overlay Color', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('If you would like a color to overlay your background image, select it here.', 'nectar-blocks-theme')
      ],
      [
        'id' => 'page-404-home-button',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Add Button To Direct User Home', 'nectar-blocks-theme'),
        'sub_desc' => esc_html__('This will add a button onto your 404 template which links back to your home page.', 'nectar-blocks-theme'),
        'default' => '1'
      ]
    ];

    return [
      'section_id' => 'general-wordpress-pages-404-section',
      'settings' => [
        'title' => esc_html__( '404 Not Found Template', 'nectar-blocks-theme' ),
        'priority' => 37
      ],
      'controls' => $controls
    ];
  }
}