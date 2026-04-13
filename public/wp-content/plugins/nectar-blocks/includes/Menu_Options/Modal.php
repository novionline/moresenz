<?php

namespace Nectar\Menu_Options;

use Nectar\Menu_Options\Settings;
use Nectar\Menu_Options\Setting_Field;

class Modal {
  private static $instance;

  public function __construct() {

    if( current_user_can('administrator') ) {
      add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
      add_action( 'admin_footer', [ $this, 'nectar_menu_item_modal_markup' ] );
    }

    add_action( 'wp_ajax_nectar_menu_item_settings', [$this, 'nectar_menu_item_settings' ] );
    add_action( 'wp_ajax_nectar_menu_item_settings_save', [$this, 'nectar_menu_item_settings_save' ] );

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

  public function enqueue_assets($hook) {

    if ($hook === 'nav-menus.php') {

      $global_sections_JS_asset_path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/menuOptions.asset.php';
      $args_array = include($global_sections_JS_asset_path);

      // JS
      wp_register_script(
          'nectarblocks-admin-wp-menu',
          NECTAR_BLOCKS_PLUGIN_PATH . '/build/menuOptions.js',
          [ 'jquery', 'wp-color-picker' ],
          $args_array['version']
      );

      //// Translations.
      $translation_arr = [
        'edit_button_text' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 109.42 101.08">
        <path fill="#ffffff" d="M41.81,101.08c-10.68,0-21.35-4.12-29.57-12.34C4.35,80.84,0,70.34,0,59.17s4.35-21.67,12.25-29.57L41.81,0l28.87,28.9c3.1,3.11,3.1,8.14,0,11.25-3.11,3.11-8.14,3.1-11.25,0l-17.62-17.64-18.32,18.34c-4.9,4.9-7.59,11.4-7.59,18.33s2.7,13.43,7.59,18.32c9.99,9.99,25.85,10.21,36.1.53,3.19-3.01,8.22-2.87,11.24.32,3.02,3.19,2.87,8.22-.32,11.24-8.12,7.67-18.42,11.5-28.7,11.5Z" stroke-width="0"/>
        <path fill="#ffffff" d="M108.55,60.66c-8.74,4.01-12.67,7.94-16.69,16.69-.53,1.16-2.19,1.16-2.72,0-4.01-8.74-7.94-12.67-16.69-16.69-1.16-.53-1.16-2.19,0-2.72,8.74-4.01,12.67-7.94,16.69-16.69.53-1.16,2.19-1.16,2.72,0,4.01,8.74,7.94,12.67,16.69,16.69,1.16.53,1.16,2.19,0,2.72Z" stroke-width="0"/>
        </svg>' .
        '<span>'
          . esc_html__( 'Menu Item Options', 'nectar-blocks' ) .
        '</span>',
        'saving' => esc_html__( 'Saving...', 'nectar-blocks' ),
        'error' => esc_html__( 'Error Saving', 'nectar-blocks' ),
        'success' => esc_html__( 'Saved Successfully', 'nectar-blocks' ),
      ];
      wp_localize_script( 'nectarblocks-admin-wp-menu', 'nectar_menu_i18n', $translation_arr );

      $color_arr = [
        'color1' => '#81d742',
        'color2' => '#eeee22',
        'color3' => '#dd9933',
        'color4' => '#dd3333',
        'color5' => '#ffffff',
        'color6' => '#000000',
      ];

      // Pull colors from Nectarblocks global settings
      if (class_exists('Nectar\Global_Settings\Global_Colors')) {
        $global_colors_class = new \Nectar\Global_Settings\Global_Colors();
        $global_colors = $global_colors_class->get_global_colors();
        if (isset($global_colors['solids'])) {
            $values = array_column($global_colors['solids'], 'value');
            // Remove duplicate values
            $values = array_values(array_unique($values));

            // Replace values in $color_arr with values from $values
            $i = 0;
            foreach ($color_arr as $key => $value) {
                if (isset($values[$i])) {
                    $color_arr[$key] = $values[$i];
                    $i++;
                } else {
                    break;
                }
            }
        }
    }

      //// Localize.
      wp_localize_script(
          'nectarblocks-admin-wp-menu',
          'nectar_menu',
          [
          'ajaxurl' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('nectar_menu_settings_ajax_nonce'),
          'colors' => $color_arr
        ]
      );

      wp_enqueue_script( 'nectarblocks-admin-wp-menu' );

      // CSS
      wp_enqueue_style( 'wp-color-picker' );
      wp_enqueue_style(
          'nectarblocks-admin-wp-menu',
          NECTAR_BLOCKS_PLUGIN_PATH . '/build/menuOptions.css',
          '',
          $args_array['version']
      );

    } // Endif on nav-menus.

  }

  /**
   * Get settings.
   */
  public function nectar_menu_item_settings() {

    // Access Level.
    if( ! current_user_can('administrator') ) {
        die ( 'Only an administrator can access these settings.');
    }

    // Verify Nonce.
    $nonce = $_POST['nonce'];

    if ( ! wp_verify_nonce( $nonce, 'nectar_menu_settings_ajax_nonce' ) ) {
       die ( 'Invalid Nonce!');
    }

    // Grab post.
    $parent_id = (int) sanitize_text_field( $_POST['parent_id'] );
    $menu_item_depth = (int) sanitize_text_field( $_POST['menu_item_depth'] );
    $menu_item_id = (int) sanitize_text_field( $_POST['menu_item_id'] );

    $nectar_menu_item_settings = Settings::get_instance();
    $nectar_menu_item_settings_arr = $nectar_menu_item_settings::get_settings();

    foreach( $nectar_menu_item_settings_arr as $id => $field ) {

      $max_depth = isset($field['max_depth']) ? (int) $field['max_depth'] : 100;
      $min_depth = isset($field['min_depth']) ? (int) $field['min_depth'] : 0;

      if( $menu_item_depth > $max_depth && -1 !== $max_depth ||
          $menu_item_depth < $min_depth ) {
        continue;
      }

        $options = maybe_unserialize( get_post_meta( $menu_item_id, 'nectar_menu_options', true ) );

      $value = ( isset($options[$id]) ) ? $options[$id] : false;

      if( ! $value ) {
        $value = ( isset( $field['default_value'] ) ) ? $field['default_value'] : null;
      }

      // theme option conditional
      if( defined( 'NECTAR_THEME_NAME' ) && function_exists('get_nectar_theme_options') && isset($field['theme_option_conditional']) ) {

        $nectar_options = get_nectar_theme_options();
        $theme_option_field = $field['theme_option_conditional'][0];
        $theme_option_required_val = $field['theme_option_conditional'][1];

        if( isset($nectar_options[$theme_option_field]) && 
          $nectar_options[$theme_option_field] !== $theme_option_required_val ) {
          continue;
        }

      }

        new Setting_Field( $id, $field, $value );
    }

    wp_die();

  }

  /**
   * Save.
   */
  public function nectar_menu_item_settings_save() {

    $result = [];

    // Access Level.
    if( ! current_user_can('administrator') ) {

      $result['type'] = 'fail';
      wp_send_json($result);

      die ( 'Only an administrator can access these settings.');

    }

    // Verify Nonce.
    $nonce = $_POST['nonce'];

    if ( ! wp_verify_nonce( $nonce, 'nectar_menu_settings_ajax_nonce' ) ) {

      $result['type'] = 'fail';
      wp_send_json($result);

      die ( 'Invalid Nonce!');
    }

    // Sanitize and get setup data for saving.
    $menu_id = (int) sanitize_text_field( $_POST['id'] );

    $menu_options = [];

    /*
    Widget area options
    'menu_item_widget_area'                   => 'regular',
    'menu_item_widget_area_marign'            => 'regular',*/

    $options_arr = [
      'enable_mega_menu' => 'regular',
      'mega_menu_global_section' => 'regular',
      'mega_menu_global_section_mobile' => 'regular',

      'menu_item_icon_type' => 'regular',
      'menu_item_icon_custom' => 'array',
      'menu_item_icon' => 'regular',
      'menu_item_icon_iconsmind' => 'regular',
      'menu_item_icon_position' => 'regular',
      'menu_item_icon_size' => 'regular',
      'menu_item_hide_menu_title' => 'regular',
      'menu_item_icon_custom_text' => 'regular',
      'menu_item_icon_spacing' => 'regular',
      'menu_item_persist_mobile_header' => 'regular',
      'menu_item_hide_menu_title_modifier' => 'regular',
      'menu_item_link_link_style' => 'regular',
      'menu_item_link_link_text_style' => 'regular',

      'menu_item_link_button_color' => 'regular',
      'menu_item_link_button_color_text' => 'regular',
      'menu_item_link_button_color_hover' => 'regular',
      'menu_item_link_button_color_text_hover' => 'regular',
      'menu_item_link_button_color_border' => 'regular',
      'menu_item_link_button_color_border_text' => 'regular',
      'menu_item_link_button_color_border_hover' => 'regular',
    ];

    foreach ($options_arr as $param_name => $type) {

      if( isset($_POST['options'][$param_name]) &&
          ! empty($_POST['options'][$param_name]) ) {

        // Array Values.
        if( 'array' === $type ) {

          if( isset($_POST['options'][$param_name]) && is_array($_POST['options'][$param_name]) ) {

            $menu_options[$param_name] = [];

            foreach ($_POST['options'][$param_name] as $key => $value) {
              $menu_options[$param_name][sanitize_key($key)] = sanitize_text_field($value);
            }

          }

        }
        // Regular Values.
        else {

          // Encoded.
          if( 'menu_item_icon_custom_text' === $param_name ) {
            $menu_options[$param_name] = urlencode( sanitize_text_field( $_POST['options'][$param_name] ) );
          } else {
            $menu_options[$param_name] = sanitize_text_field( $_POST['options'][$param_name] );
          }

        }

      } // End option isset.

    }

    update_post_meta($menu_id, 'nectar_menu_options', $menu_options);

    $result['type'] = 'success';
    wp_send_json($result);

    wp_die();

  }

  /**
   * Modal Markup
   */
  public function nectar_menu_item_modal_markup() {

    if( ! function_exists('get_current_screen') ) {
      return;
    }

    $current_screen = get_current_screen();

    if ( $current_screen && property_exists( $current_screen, 'base') ) {
      if ( 'nav-menus' === $current_screen->base ) {
        echo '<div id="nectar-menu-settings-modal-wrap" class="loading">
        <div id="nectar-menu-settings-modal">
        <div class="header">
          <div class="row">
            <h2>"<span class="menu-item-name"></span>" ' . esc_html__('Options', 'nectar-blocks') . '</h2>
            <div class="categories">
              <a href="#" data-rel="menu-item"><span>' . esc_html__('Menu Item', 'nectar-blocks') . '</span></a>
              <a href="#" data-rel="menu-icon"><span>' . esc_html__('Icon', 'nectar-blocks') . '</span></a>
            </div>
            <a href="#" class="close-modal"><div class="dashicons dashicons-no-alt"></div></a>
          </div>
        </div>
        <div class="nectar-menu-settings-inner">
          <form class="menu-options-form"></form>
        </div>
        <div class="bottom-controls">
          <a href="#" class="close-modal">' . esc_html__('Close', 'nectar-blocks') . '</a>
          <a href="#" class="save">
            <span class="inner">
              <span class="default">' . esc_html__('Save Changes', 'nectar-blocks') . '</span>
              <span class="dynamic"></span>
            </span>
          </a>
        </div>
        <div class="loading-wrap"><div class="dashicons dashicons-admin-generic"></div></div>
        </div>
        <div id="nectar-menu-settings-overlay"></div>
        </div>';
      }
    }

  }
}
