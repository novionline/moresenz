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
class NectarBlocks_Customizer_Before_Panels {
  private static function get_title() {
    return [
      'id' => 'before-panels-title',
      'settings' => [
        'type' => 'nectar-title',
        'title' => esc_html__( 'Nectarblocks Options', 'nectar-blocks-theme' ),
        'priority' => 1,
      ]
    ];
  }

  public static function get_partials() {
    return [
      self::get_title()
    ];
  }
}