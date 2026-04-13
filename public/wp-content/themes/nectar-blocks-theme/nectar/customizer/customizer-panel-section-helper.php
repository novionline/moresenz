<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Custom components
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-extended/nectar-title.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-extended/nectar-divider.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/image-select/ImageSelect.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/typography/Typography.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/divider/Divider.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/components/switch-legacy/SwitchLegacy.php';

// Panel imports
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/before-panels.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/after-panels.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/general-settings.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/typography.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/core.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/general-wordpress-settings.php';

// Layout
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/layout.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/layout/footer.php';

// Post Types
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/post-types.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/blog.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/woocommerce.php';
require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/panels/post-types/portfolio.php';

define('NECTAR_CUSTOMIZER_STATUS', 'nectar_customizer_status');

if ( ! class_exists( 'NectarBlocks_Panel_Section_Helper' ) ) {

  class NectarBlocks_Panel_Section_Helper {
    private static $instance = null;

    public function __construct() {
      // add_action('wp', [ $this, 'nectar_child_theme_activation_hook' ]);
    }

    /**
     * Gets an instance
     *
     * @since 14.1.0
     * @return NectarBlocks_Panel_Section_Helper
     */
    public static function get_instance() {
      if (self::$instance == null) {
        self::$instance = new NectarBlocks_Panel_Section_Helper();
      }

      return self::$instance;
    }

    /**
     * List of panels and sections.
     *
     * @since 14.1.0
     * @return array of panels and sections
     */
    public function nectar_customizer_panels_flat() {
      return array_merge(
          NectarBlocks_Customizer_General_Settings::get_kirki_partials(),
          NectarBlocks_Customizer_Typography::get_kirki_partials(),
          NectarBlocks_Customizer_Layout::get_kirki_partials(),
          NectarBlocks_Customizer_Layout_Footer::get_kirki_partials(),
          NectarBlocks_Customizer_Post_Types_Blog::get_kirki_partials(),
          NectarBlocks_Customizer_Post_Types_WooCommerce::get_kirki_partials(),
          NectarBlocks_Customizer_Post_Types_Portfolio::get_kirki_partials(),
          NectarBlocks_Customizer_General_WP_Settings::get_kirki_partials()
      );
    }

    public function nectar_customizer_panels_flattened() {
      $partials = $this->nectar_customizer_panels_flat();
      $flat = [];
      foreach ($partials as $part) {
        // Ignore panel definitions as they don't have controls
        if ( isset( $part['panel_id'] ) ) {
          continue;
        }

        $flat = array_merge($flat, $part['controls']);
      }
      return $flat;
    }

    public function nectar_customizer_settings_mapped_id() {
      $flattened = $this->nectar_customizer_panels_flattened();
      $arr = [];
      foreach ($flattened as $option) {
        $arr[$option['id']] = $option;
      }
      return $arr;
    }

    public function nectar_child_theme_activation_hook() {
      error_log('running theme change hook');
      $status_data = get_option(NECTAR_CUSTOMIZER_STATUS, []);

      // TODO: Make sure windows doesn't mess this up
      $kaboom = explode('/', get_theme_file_uri());
      $new_theme_slug = array_pop( $kaboom );

      // Must be a child theme of NectarBlocks for this to be running
      // Can assume that is true/
      if ( is_child_theme() ) {
        error_log('is_child_theme is true: ' . $new_theme_slug);

        if ( isset( $status_data['defaults'] ) && isset( $status_data['defaults'][$new_theme_slug] ) ) {

          error_log('Getting defaults from salient');
          $mods = get_option( "theme_mods_" . NECTAR_THEME_NAME);

          foreach ($mods as $key => $value) {
            set_theme_mod($key, $value);
          }

          if ( empty( $status_data ) ){
            add_option(NECTAR_CUSTOMIZER_STATUS, [
              'defaults' => [
                $new_theme_slug => true
              ],
              'migrations' => []
            ]);
          } else {
            $status_data['defaults'][$new_theme_slug] = true;
            $updated = update_option(NECTAR_CUSTOMIZER_STATUS, $status_data);
            error_log('Saving to default that exists ' . $updated);
          }

        } else {
          error_log('Defaults are already set');
        }
      } else {
        error_log('Not child theme');
      }

    }

    /**
     * Returns a map of control ids to controls (current just their defaults)
     * @since 14.1.0
     */
    public function nectar_customizer_panels_map() {
      $arr = []; // Array of all the controls and settings
      $panels = $this->nectar_customizer_panels_flat();

      foreach ( $panels as &$p ) {

        // Ignore panel definitions as they don't have controls
        if ( isset( $p['panel_id'] ) ) {
          continue;
        }

        if ( isset( $p['section_id']) && array_key_exists( 'controls', $p ) ) {

            foreach ( $p['controls'] as &$c ) {

              if ( isset( $c['default'] )) {
                $arr[$c['id']] = [ 'default' => $c['default'] ];
              } else {
                // error_log('No default: '. $c['id']);
                $arr[$c['id']] = [ 'default' => '' ];
              }

            }

        } else {
          error_log('Unable to find section_id: ' . json_encode($p));
        }

      }

      return $arr;
    }
  }

}

/**
 * Initialize the NectarBlocks_Customizer instance.
 */
NectarBlocks_Panel_Section_Helper::get_instance();
