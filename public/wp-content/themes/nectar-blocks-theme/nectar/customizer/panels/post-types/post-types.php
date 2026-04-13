<?php

/**
 * Customizer Layout section.
 *
 * @since 14.0.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Layout customizer options.
 *
 * @since 14.0.2
 */
class NectarBlocks_Customizer_Post_Types {
  private static function get_title() {
    return [
      'id' => 'post-types-title',
      'settings' => [
        'type' => 'nectar-title',
        'title' => esc_html__( 'Post Types', 'nectar-blocks-theme' ),
        'priority' => 23,
      ]
    ];
  }

  public static function get_partials() {
    return [
      self::get_title()
    ];
  }
}