<?php

namespace Nectar\Global_Sections;

use Nectar\Global_Sections\Global_Sections;
use Nectar\Utilities\FlatMap;

/**
 * Visual hook locations.
 * @since 0.1.4
 * @version 2.0.0
 */
class Visual_Hook_Locations {
  private static $instance;

  public $show_hook_locations = false;

  private function __construct() {
    if ( ! function_exists( 'current_user_can' ) ) {
      return;
    }
    // Admin user only.
    if ( ! current_user_can( 'manage_options' ) || is_admin() ) {
      return;
    }

    add_action( 'wp', [ $this, 'setup' ], 40);
    add_action( 'admin_bar_menu', [ $this, 'register_admin_toolbar_link' ], 80 );
    add_action( 'wp', [ $this, 'display_hook_locations' ], 50);
  }

  /**
   * Initiator.
   */
  public static function get_instance() {
    if (! self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  public function setup() {
    if( isset( $_GET['nectar-blocks-hook-locations'] ) && 'true' === $_GET['nectar-blocks-hook-locations'] ) {
      $this->show_hook_locations = true;
    }

    return;
  }

  public function register_admin_toolbar_link( \WP_Admin_Bar $admin_bar ) {
    $title = '<span class="ab-icon"></span>' . esc_html__('Display Nectar Hooks', 'nectar-blocks');
    $href = add_query_arg( ['nectar-blocks-hook-locations' => 'true'] );
    $id = 'nectar-blocks-hook-locations';

    if( $this->show_hook_locations === true ) {
      $title = '<span class="ab-icon"></span>' . esc_html__('Hide Nectar Hooks', 'nectar-blocks');
      $href = remove_query_arg( 'nectar-blocks-hook-locations' );
      $id = 'nectar-blocks-hook-locations-active';
    }

    $admin_bar->add_menu( [
      'parent' => null,
      'group' => null,
      'title' => $title,
      'href' => $href,
      'id' => $id
    ] );
  }

  public function display_hook_locations() {
    if ( ! $this->show_hook_locations ) {
      return;
    }

    add_action( 'wp_enqueue_scripts', [$this, 'enqueue_styles'] );

    $locations = Global_Sections::get_locations();
    $locations_flat_values = FlatMap::flatMap(fn($locations) => $locations['options'], $locations);

    foreach( $locations_flat_values as $hook ) {
      $label = esc_html($hook['label']);
      $hook = esc_html($hook['value']);

      if ( Render::get_instance()->omit_global_section_render($hook) ) {
        continue;
      }

      Render::get_instance()->modify_salient_markup($hook);

      add_action( $hook, function() use ($label, $hook) {
        echo '<div class="nectar-blocks-hook-location nectar-global-section nectar-link-underline ' . esc_html($hook) . '"><div class="container normal-container row">';
        $add_new_button = '<a target="_blank" rel="noreferrer" href="' .
          admin_url('post-new.php?post_type=nectar_sections&nectar_starting_hook=' .
          esc_html($hook)) . '"><i class="fa fa-plus-circle"></i> <span>' .
          esc_html__('Add New Global Section', 'nectar-blocks') . '</span></a>';

        $row_shortcode = '<div class="nectar-blocks-hook-location__content"><span>' .
          esc_html__('Hook:', 'nectar-blocks') . ' ' .
          esc_html($label) . '</span>' . $add_new_button . '</div>';

        echo $row_shortcode;
        echo '</div></div>';
      }, 1 );
    }
  }

  public function enqueue_styles() {

    // Toolbar styling.
    wp_add_inline_style( 'main-styles', '
    #wpadminbar [id*="wp-admin-bar-nectar-blocks-hook-locations"] .ab-item {
      display: flex;
      align-items: center;
    }
    #wpadminbar #wp-admin-bar-nectar-blocks-hook-locations .ab-icon:before {
      content: "\f177";
    }
    #wpadminbar #wp-admin-bar-nectar-blocks-hook-locations-active .ab-icon:before {
      content: "\f530";
    }' );

    // Hook locations styling.
    wp_add_inline_style( 'main-styles', '

      #header-space {
        margin-bottom: 90px;
      }

      .nectar-blocks-hook-location__content {
        transition: box-shadow 0.35s ease, border-color 0.35s ease;
        border: 1px dashed var(--nectar-accent-color);
        border-radius: 10px;
        margin: 10px 0;
      }

      .nectar-blocks-hook-location__content:hover {
        border: 1px dashed transparent;
        box-shadow: 0 0 0px 4px inset var(--nectar-accent-color);
      }

      .nectar-blocks-hook-location__content {
        padding: 15px;
        line-height: 1;
      }

      .nectar-blocks-hook-location__content,
      .row .nectar-blocks-hook-location__content a span,
      .row .nectar-blocks-hook-location__content a i {
        color: #000;
      }
      .row .nectar-blocks-hook-location__content a {
        display: inline-flex;
        font-weight: 700;
        font-size: 13px;
        align-items: center;
        text-decoration: none;
        line-height: 1.3;
        transform: scale(0.9);
        transition: transform 0.35s ease, opacity 0.35s ease;
        opacity: 0;
      }
      .nectar-blocks-hook-location__content > span {
        display: block;
        transform: translateY(50%);
        transition: transform 0.35s ease, opacity 0.35s ease;
      }
      .nectar-blocks-hook-location__content a i {
        top: 0;
      }
      .nectar-blocks-hook-location .nectar-blocks-hook-location__content:hover > span {
        transform: translateY(0);
      }
      .nectar-blocks-hook-location .nectar-blocks-hook-location__content:hover a {
        transform: translateY(0) scale(1);
        opacity: 1;
      }
    ');

  }
}
