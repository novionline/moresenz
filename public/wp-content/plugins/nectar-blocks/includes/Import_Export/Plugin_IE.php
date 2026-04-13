<?php

namespace Nectar\Import_Export;

use Nectar\Global_Settings\{Global_Colors,Code_Options,Global_Typography};

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
    $data = [
      'global_colors' => $global_colors,
      'global_typography' => $global_typography,
      'code' => $code
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
      Global_Colors::update_options($parsed_import_data['global_colors']);
    }

    if (array_key_exists( 'global_typography', $parsed_import_data )) {
      Global_Typography::update_options($parsed_import_data['global_typography']);
    }

    if (array_key_exists( 'code', $parsed_import_data )) {
      Code_Options::update_options($parsed_import_data['code']);
    }
  }
}