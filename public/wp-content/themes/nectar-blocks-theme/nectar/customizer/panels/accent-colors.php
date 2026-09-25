<?php

require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-extended/nectar-section.php';

/**
 * Customizer Section + Panel: General Settings Styling
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// if ( class_exists( 'NectarBlocks_WP_Customize_General_Settings_Panel' ) ) {

  /**
   * General Settings Panel.
   *
   * @since 14.0.2
   */
  class NectarBlocks_WP_Customize_Accent_Color_Panel {
    public static $panel_id = 'accent-color-panel';

    public static function get_panel_settings() {
      return [
        'id' => self::$panel_id,
        'settings' => [
          'title' => 'General Settings',
          'priority' => 1,
        ]
      ];
    }

    public static function get_sections() {
      return [
        // 'styling' => self::get_section_styling(),
        // 'functionality' => self::get_section_functionality(),
        // 'extra' => self::get_section_extra(),
        'performance' => self::get_section_performance(),
      ];
    }

    public static function get_section_performance() {

      $controls = [
        // array(
        //   'id' => 'global_lazy_load_images',
        //   'type' => 'switch',
        //   'title' => esc_html__('Lazy Load Page Builder Element Images', 'nectar-blocks-theme'),
        //   'subtitle' => esc_html__('Enabling this will globally activate lazy loading for theme elements which support it.', 'nectar-blocks-theme'),
        //   'desc' => '',
        //   'default' => '0'
        // ),
        // array(
        //   'id' => 'typography_font_swap',
        //   'type' => 'switch',
        //   'title' => esc_html__('Font Display Swap', 'nectar-blocks-theme'),
        //   'subtitle' => esc_html__('This is a font performance option which will your allow text to display in a default font before Google fonts have loaded. Enabling this will correct the page speed recommendation "Ensure text remains visible during webfont load".', 'nectar-blocks-theme'),
        //   'desc' => '',
        //   'default' => '0'
        // ),
      //   array(
      //    'id' => 'page_header_responsive_images',
      //    'type' => 'switch',
      //    'title' => esc_html__('Responsive Page/Post Header Image Sizing', 'nectar-blocks-theme'),
      //    'subtitle' => esc_html__('This will swap the background image of all page/post headers to use smaller sizes on mobile devices. Enabling this will increase the Google lighthouse largest contentful paint metric wherever page headers are used.', 'nectar-blocks-theme'),
      //    'desc' => '',
      //    'default' => '0'
      //  ),
      //   array(
      //     'id' => 'rm-legacy-icon-css',
      //     'type' => 'switch',
      //     'title' => esc_html__('Remove Legacy Icon CSS', 'nectar-blocks-theme'),
      //     'subtitle' => esc_html__('Removes extra icon CSS for legacy users.', 'nectar-blocks-theme'),
      //     'desc' => '',
      //     'default' => '0'
      //   ),
      //   array(
      //    'id' => 'rm-font-awesome',
      //    'type' => 'switch',
      //    'title' => esc_html__('Remove Global Font Awesome', 'nectar-blocks-theme'),
      //    'subtitle' => esc_html__('By default, the Font Awesome icon library is enqueued everywhere. Enabling this will remove that and instead late enqueue the icon library if any Font Awesome icons are found on the current page.', 'nectar-blocks-theme'),
      //    'desc' => '',
      //    'default' => '0'
      //  ),
       /*array(
         'id' => 'defer-google-fonts',
         'type' => 'switch',
         'title' => esc_html__('Defer Google Fonts', 'nectar-blocks-theme'),
         'subtitle' => esc_html__('Enabling this will cause fonts to load later than normal, but be non render-blocking.', 'nectar-blocks-theme'),
         'desc' => '',
         'default' => '0'
       ),*/
      //  array(
      //    'id' => 'defer-javascript',
      //    'type' => 'switch',
      //    'title' => esc_html__('Move jQuery to Footer', 'nectar-blocks-theme'),
      //    'subtitle' => esc_html__('Attempts to move jQuery to the footer to make it non render-blocking. Note that this can break third party plugins/scripts which require JavaScript to be loaded in the head. Additionally certain WordPress plugins will still force jQuery to load in the head, such as WooCommerce.', 'nectar-blocks-theme'),
      //    'desc' => '',
      //    'default' => '0'
      //  ),
      //   array(
      //     'id' => 'rm-wp-emojis',
      //     'type' => 'switch',
      //     'title' => esc_html__('Remove WordPress Emoji Script/CSS', 'nectar-blocks-theme'),
      //     'subtitle' => esc_html__('Removes the WordPress Emoji assets which automatically convert emoticons to WP specific emojis.', 'nectar-blocks-theme'),
      //     'desc' => '',
      //     'default' => '0'
      //   ),
      //   array(
      //     'id' => 'rm-block-editor-css',
      //     'type' => 'switch',
      //     'title' => esc_html__('Remove Block Editor (Gutenberg) CSS', 'nectar-blocks-theme'),
      //     'subtitle' => esc_html__('Removes the block editor element css.', 'nectar-blocks-theme'),
      //     'desc' => '',
      //     'default' => '0'
      //   ),
      ];

      return [
        'section_id' => 'general-settings-performance-section',
        'settings' => [
          'title' => esc_html__( 'Performance', 'nectar-blocks-theme' ),
          'priority' => 4,
          'panel' => self::$panel_id,
        ],
        'controls' => $controls
      ];
    }
  }

// }
