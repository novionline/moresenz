<?php

/**
 * Customizer Layout Footer section.
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Layout Footer customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_Layout_Footer {
  public static function get_kirki_partials() {
    return [
      self::get_footer()
    ];
  }

  public static function get_footer() {
    $controls = [
      [
        'id' => 'enable-main-footer-area',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Main Footer Area', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Do you want use the main footer that contains all the widgets areas?', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0',
        'priority' => 1,
      ],

      [
        'id' => 'footer_columns',
        'type' => 'select',
        'required' => [
          [ 'enable-main-footer-area', '=', '1' ]
        ],
        'title' => esc_html__('Footer Columns', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the number of columns you would like for your footer.', 'nectar-blocks-theme'),
        'desc' => '',
        'options' => [
          "1" => esc_html__('1 Column', 'nectar-blocks-theme'),
          "2" => esc_html__('2 Columns', 'nectar-blocks-theme'),
          "3" => esc_html__('3 Columns', 'nectar-blocks-theme'),
          "4" => esc_html__('4 Columns', 'nectar-blocks-theme'),
          "5" => esc_html__('4 Columns Alt', 'nectar-blocks-theme'),
        ],
        'default' => '3'
      ],

      [
        'id' => 'footer-custom-color',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Custom Footer Color Scheme', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'footer-background-color',
        'type' => 'color',
        'title' => '',
        'subtitle' => esc_html__('Footer Background Color', 'nectar-blocks-theme'),
        'desc' => '',
        'required' => [ [ 'footer-custom-color', '=', '1' ] ],
        'class' => 'five-columns always-visible',
        'default' => '#313233',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('footer-background-color'),
        'transparent' => false
      ],

      [
        'id' => 'footer-font-color',
        'type' => 'color',
        'title' => '',
        'required' => [ [ 'footer-custom-color', '=', '1' ] ],
        'subtitle' => esc_html__('Footer Font Color', 'nectar-blocks-theme'),
        'class' => 'five-columns always-visible',
        'desc' => '',
        'default' => '#CCCCCC',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('footer-font-color'),
        'transparent' => false
      ],

      [
        'id' => 'footer-secondary-font-color',
        'type' => 'color',
        'title' => '',
        'required' => [ [ 'footer-custom-color', '=', '1' ] ],
        'subtitle' => esc_html__('2nd Footer Font Color', 'nectar-blocks-theme'),
        'class' => 'five-columns always-visible',
        'desc' => '',
        'default' => '#777777',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('footer-secondary-font-color'),
        'transparent' => false
      ],

      [
        'id' => 'footer-copyright-background-color',
        'type' => 'color',
        'title' => '',
        'required' => [ [ 'footer-custom-color', '=', '1' ] ],
        'class' => 'five-columns always-visible',
        'subtitle' => esc_html__('Copyright Background Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#1F1F1F',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('footer-copyright-background-color'),
        'transparent' => false
      ],

      [
        'id' => 'footer-copyright-font-color',
        'type' => 'color',
        'required' => [ [ 'footer-custom-color', '=', '1' ] ],
        'title' => '',
        'class' => 'five-columns always-visible',
        'subtitle' => esc_html__('Footer Copyright Font Color', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '#777777',
        'output' => Nectar_Dynamic_Colors()->kirki_arrays('footer-copyright-font-color'),
        'transparent' => false
      ],

      [
        'id' => 'footer-full-width',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Footer Full Width', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This to cause your footer content to display full width.', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => '0'
      ],

      [
        'id' => 'footer-link-hover',
        'type' => 'select',
        'title' => esc_html__('Footer Link Hover Effect', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the hover effect you would like for links in your footer copyright area.', 'nectar-blocks-theme'),
        'options' => [
          "default" => esc_html__("Opacity/Color Change", 'nectar-blocks-theme'),
          "underline" => esc_html__("Animated Underline", 'nectar-blocks-theme')
        ],
        'default' => 'default'
      ],

      [
        'id' => 'disable-copyright-footer-area',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Disable Footer Copyright Area', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('This will hide the copyright bar in your footer', 'nectar-blocks-theme'),
        'desc' => '',
        'default' => ''
      ],

      [
        'id' => 'footer-copyright-layout',
        'type' => 'select',
        'title' => esc_html__('Footer Copyright Layout', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please select the layout you would like for your footer copyright area.', 'nectar-blocks-theme'),
        'options' => [
          "default" => esc_html__("Determined by Footer Columns", 'nectar-blocks-theme'),
          "centered" => esc_html__("Centered", 'nectar-blocks-theme')
        ],
        'default' => 'default'
      ],

      [
        'id' => 'footer-copyright-text',
        'type' => 'text',
        'title' => esc_html__('Footer Copyright Section Text', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Please enter the copyright section text. e.g. All Rights Reserved', 'nectar-blocks-theme'),
        'desc' => ''
      ],

      [
        'id' => 'disable-auto-copyright',
        'type' => 'nectar_blocks_switch_legacy',
        'title' => esc_html__('Disable Automatic Copyright', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('By default, your copyright section will say "© {YEAR} {SITENAME}" before the additional text you add above in the Footer Copyright Section Text input - This option allows you to remove that.', 'nectar-blocks-theme'),
        'desc' => ''
      ],

      [
        'id' => 'footer-background-image',
        'type' => 'media',
        'title' => esc_html__('Footer Background Image', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Upload an image that will be used as the background image on your footer. ', 'nectar-blocks-theme'),
        'desc' => ''
      ],

      [
        'id' => 'footer-background-image-overlay',
        'type' => 'slider',
        'title' => esc_html__('Footer Background Overlay', 'nectar-blocks-theme'),
        'subtitle' => esc_html__('Adjust the overlay opacity here - the overlay colors pulls from your defined footer background color.', 'nectar-blocks-theme'),
        'desc' => '',
        "default" => 0.8,
        "min" => 0,
        "step" => 0.1,
        "max" => 1,
        "resolution" => 0.1,
        'display_value' => 'text'
      ]

    ];

    return [
      'section_id' => 'footer-panel',
      'settings' => [
        'title' => esc_html__( 'Footer', 'nectar-blocks-theme' ),
        'priority' => 16
      ],
      'controls' => $controls
    ];
  }
}
