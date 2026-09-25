<?php

namespace Nectar\Global_Settings;

/**
 * Settings_Base
 * @version 0.1.5
 * @since 0.1.5
 */
class Settings_Base {
  public static string $OPTION_NAME;

  public static function get_options() {
    return get_option(static::$OPTION_NAME);
  }

  public static function update_options($value) {
    return update_option(static::$OPTION_NAME, $value);
  }
}
