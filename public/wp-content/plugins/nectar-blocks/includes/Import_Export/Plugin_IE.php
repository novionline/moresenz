<?php

namespace Nectar\Import_Export;

use Nectar\Global_Settings\{Global_Colors,Code_Options,Global_Typography,Nectar_Plugin_Options};
use Nectar\Render\Local_Google_Fonts;

/**
 * Plugin_IE aka Import Export
 *
 * @since 0.1.5
 */
class Plugin_IE {
  private static $instance = null;

  public function __construct() {}

  /**
   * Creates an instance.
   *
   * @since 0.1.5
   * @return Plugin_IE
   */
  public static function get_instance() {
    if (self::$instance == null) {
      self::$instance = new Plugin_IE();
    }

    return self::$instance;
  }

  /**
   * @since 0.1.5
   */
  function export_options() {
    $global_colors = Global_Colors::get_options();
    $global_typography = Global_Typography::get_options();
    $code = Code_Options::get_options();
    $plugin_options = Nectar_Plugin_Options::get_options();
    $data = [
      'global_colors' => $global_colors,
      'global_typography' => $global_typography,
      'code' => $code,
      'plugin_options' => $plugin_options
    ];

    return $data;
  }

  /**
   * Imports the plugin options.
   *
   * @since 0.1.5
   * @return array
   */
  function import_options($parsed_import_data) {
    if (array_key_exists( 'global_colors', $parsed_import_data )) {
      $import_colors = $parsed_import_data['global_colors'];

      // Preserve saved palettes if the import doesn't include them.
      if (! isset($import_colors['savedPalettes'])) {
        $existing = Global_Colors::get_options();
        if (is_array($existing) && isset($existing['savedPalettes'])) {
          $import_colors['savedPalettes'] = $existing['savedPalettes'];
        }
      }

      Global_Colors::update_options($import_colors);
    }

    if (array_key_exists( 'global_typography', $parsed_import_data )) {
      Global_Typography::update_options($parsed_import_data['global_typography']);

      // Clear local Google fonts cache to trigger regeneration with new typography
      if ( class_exists( Local_Google_Fonts::class ) && Local_Google_Fonts::is_enabled() ) {
        Local_Google_Fonts::clear_cache();
      }
    }

    if (array_key_exists( 'code', $parsed_import_data )) {
      Code_Options::update_options($parsed_import_data['code']);
    }

    if (array_key_exists( 'plugin_options', $parsed_import_data )) {
      // Merge over existing values so newly-added keys keep their defaults
      // when importing from an older export that doesn't include them.
      $existing = Nectar_Plugin_Options::get_options();
      $existing = is_array($existing) ? $existing : [];
      $imported = is_array($parsed_import_data['plugin_options'])
        ? $parsed_import_data['plugin_options']
        : [];
      Nectar_Plugin_Options::update_options(array_merge($existing, $imported));
    }
  }
}