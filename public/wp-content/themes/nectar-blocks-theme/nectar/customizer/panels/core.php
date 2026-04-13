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
class NectarBlocks_Customizer_Core {
  private static function get_title() {
    return [
      'id' => 'core-title',
      'settings' => [
        'type' => 'nectar-title',
        'title' => esc_html__( 'WordPress / Plugin Options', 'nectar-blocks-theme' ),
        'priority' => 39,
      ]
    ];
  }

  public static function get_partials() {
    return [
      self::get_title()
    ];
  }
}