<?php

namespace Nectar\Shared;

/**
 * Shared helper functions for Nectar Blocks plugin and theme.
 *
 * @since 1.0.0
 */
final class Helpers {

  /**
   * Check if a value is set and not empty (common pattern in theme/plugin).
   *
   * @param mixed $value Option or variable to check.
   * @return bool
   */
  public static function option_isset( $value ) {
    return isset( $value ) && ! empty( $value );
  }
}
