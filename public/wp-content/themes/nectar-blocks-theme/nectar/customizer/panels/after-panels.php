<?php

/**
 * Customizer Core section.
 *
 * @since 14.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Core customizer options.
 *
 * @since 14.1.0
 */
class NectarBlocks_Customizer_After_Panels {
  private static function get_divider() {
    return [
      'id' => 'after-panels-divider',
      'settings' => [
        'type' => 'nectar-divider',
        'priority' => 38,
      ]
    ];
  }

  public static function get_partials() {
    return [
      self::get_divider()
    ];
  }
}