<?php

/**
 * Nectar Global Color Links
 *
 * Allows customizer color settings to be linked to plugin global colors,
 * so they output CSS variables (var(--slug)) that stay in sync when
 * global colors change.
 *
 * Link data is stored as companion theme_mods ({id}-global-link) registered
 * as Kirki fields, so they export/import automatically with the rest of the
 * customizer settings.
 *
 * @package Nectar Blocks Theme
 * @since 3.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class Nectar_Global_Color_Links {
  const LINK_SUFFIX = '-global-link';

  /**
   * Default links applied on new installations.
   */
  const DEFAULT_LINKS = [
    'overall-bg-color' => 'light',
    'accent-color' => 'accentPrimary',
    'accent-text-color' => 'light',
    'blog_archive_bg_color' => 'accentPrimary',
    'header-border-color' => 'dark',
  ];

  private static $instance = null;

  public function __construct() {
    // Defer initialization to after_setup_theme so the plugin's
    // classes are guaranteed to be loaded (plugins_loaded has fired).
    add_action( 'after_setup_theme', [ $this, 'init' ], 5 );
  }

  /**
   * Initialize after plugin classes are available.
   */
  public function init() {
    // Capture the theme activation signal before the plugin check.
    // WordPress sets 'theme_switched' on activation and clears it on
    // init priority 99. We save our own flag so it persists until
    // the plugin is active and maybe_set_defaults() can use it.
    self::save_activation_signal();

    if ( ! class_exists( 'Nectar\\Global_Settings\\Global_Colors' ) ) {
      return;
    }

    // Apply default links once for genuinely new installations.
    self::maybe_set_defaults();

    // On the frontend (non-customizer), register theme_mod filters
    // so linked settings resolve to var(--slug).
    $is_customize_preview = isset( $_GET['customize_messenger_channel'] );

    if ( ! $is_customize_preview ) {
      $this->register_frontend_filters();
    }

    // Sync hex values and localize data when customizer loads.
    add_action( 'customize_controls_enqueue_scripts', [ $this, 'sync_linked_colors' ] );
    add_action( 'customize_controls_enqueue_scripts', [ $this, 'localize_data' ], 20 );
  }

  /**
   * Capture WordPress's theme_switched signal into our own persistent flag.
   *
   * WordPress clears theme_switched on init priority 99, but the plugin
   * may not be active on that first request. Our flag persists until
   * maybe_set_defaults() can act on it.
   */
  private static function save_activation_signal() {
    if (
      get_option( 'theme_switched', false ) &&
      ! get_option( 'nectar_global_color_links_defaults_set', false )
    ) {
      update_option( 'nectar_gcl_theme_activated', true );
    }
  }

  /**
   * Apply DEFAULT_LINKS for new theme activations only.
   *
   * Uses two signals:
   * - nectar_gcl_theme_activated: set by save_activation_signal() when
   *   WordPress reports a theme switch. Present on fresh installs and
   *   theme switches, absent on in-place upgrades.
   * - nectar_global_color_links_defaults_set: permanent flag ensuring
   *   this logic runs only once.
   */
  public static function maybe_set_defaults() {
    if ( get_option( 'nectar_global_color_links_defaults_set', false ) ) {
      return;
    }

    // Only write defaults when the theme was just activated AND no
    // existing theme_mods are present. This prevents overriding a
    // user's colors when they switch away, upgrade, and switch back
    // (WordPress preserves theme_mods_{slug} across theme switches).
    if ( get_option( 'nectar_gcl_theme_activated', false ) ) {
      // Only write defaults if no active links exist. get_links()
      // skips empty values, so '' entries from set_default_values()
      // won't block a fresh install from getting defaults.
      if ( empty( self::get_links() ) ) {
        foreach ( self::DEFAULT_LINKS as $setting_id => $slug ) {
          set_theme_mod( self::link_key( $setting_id ), $slug );
        }
      }
      delete_option( 'nectar_gcl_theme_activated' );
    }

    update_option( 'nectar_global_color_links_defaults_set', true );
  }

  public static function get_instance() {
    if ( self::$instance === null ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Get the theme_mod key that stores the link for a color setting.
   *
   * @param string $setting_id The color setting ID (e.g. 'accent-color').
   * @return string The companion link key (e.g. 'accent-color-global-link').
   */
  public static function link_key( $setting_id ) {
    return $setting_id . self::LINK_SUFFIX;
  }

  /**
   * Get the global color slug linked to a setting, or false.
   *
   * @param string $setting_id The customizer setting ID.
   * @return string|false
   */
  public static function get_linked_slug( $setting_id ) {
    $value = get_theme_mod( self::link_key( $setting_id ), '' );
    return ! empty( $value ) ? $value : false;
  }

  /**
   * Get all active links as setting_id => slug.
   *
   * @return array
   */
  public static function get_links() {
    $links = [];
    $mods = get_theme_mods();

    if ( ! is_array( $mods ) ) {
      return $links;
    }

    $suffix_len = strlen( self::LINK_SUFFIX );

    foreach ( $mods as $key => $value ) {
      if ( empty( $value ) || ! is_string( $value ) ) {
        continue;
      }
      // Check if the key ends with our suffix.
      if ( substr( $key, -$suffix_len ) === self::LINK_SUFFIX ) {
        $setting_id = substr( $key, 0, -$suffix_len );
        $links[$setting_id] = $value;
      }
    }

    return $links;
  }

  /**
   * Register theme_mod filters on the frontend so linked settings
   * resolve to CSS variable references instead of hex values.
   */
  private function register_frontend_filters() {
    $links = self::get_links();

    if ( empty( $links ) ) {
      return;
    }

    foreach ( $links as $setting_id => $slug ) {
      add_filter( "theme_mod_{$setting_id}", function() use ( $slug ) {
        return 'var(--' . sanitize_html_class( $slug ) . ')';
      }, 20 );
    }
  }

  /**
   * Sync hex values from current global colors for all linked settings.
   *
   * When the customizer loads, this ensures the color pickers show
   * the current global color value (in case it was changed in the plugin).
   */
  public function sync_linked_colors() {
    $links = self::get_links();
    if ( empty( $links ) ) {
      return;
    }

    $global_colors = \Nectar\Global_Settings\Global_Colors::get_global_colors();
    if ( ! isset( $global_colors['solids'] ) ) {
      return;
    }

    // Build slug => value map.
    $color_map = [];
    foreach ( $global_colors['solids'] as $color ) {
      if ( isset( $color['slug'], $color['value'] ) ) {
        $color_map[$color['slug']] = $color['value'];
      }
    }

    foreach ( $links as $setting_id => $slug ) {
      if ( isset( $color_map[$slug] ) ) {
        set_theme_mod( $setting_id, $color_map[$slug] );
      } else {
        // Global color was deleted — remove the stale link.
        remove_theme_mod( self::link_key( $setting_id ) );
      }
    }
  }

  /**
   * Localize global colors data and current links for the customizer JS.
   */
  public function localize_data() {
    $global_colors = \Nectar\Global_Settings\Global_Colors::get_global_colors();
    $solids = isset( $global_colors['solids'] ) ? $global_colors['solids'] : [];

    // Exclude reassigned (deleted) colors — they still output CSS redirects
    // but should not appear as selectable options.
    $solids = array_filter( $solids, function( $color ) {
      return is_array( $color ) && ! isset( $color['reassigned'] );
    } );

    wp_localize_script( 'nectar-customizer-js', 'nectarGlobalColorLinks', [
      'colors' => array_values( $solids ),
      'links' => (object) self::get_links(),
      'linkSuffix' => self::LINK_SUFFIX,
    ] );
  }
}

/**
 * Initialize the Nectar_Global_Color_Links class.
 */
Nectar_Global_Color_Links::get_instance();
