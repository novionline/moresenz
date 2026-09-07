<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\Log;

/**
 * Nectar Adobe Fonts Settings.
 * @version 3.0.0
 * @since 3.0.0
 */
class Nectar_Adobe_Fonts extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_blocks_adobe_fonts';

  function __construct() {
    add_action( 'after_setup_theme', [ $this, 'initialize_defaults' ] );
  }

  public function initialize_defaults() {
    $nectar_options = get_option($this::$OPTION_NAME);

    if ($nectar_options !== false) {
      return;
    }

    update_option(
        $this::$OPTION_NAME,
        $this->defaults()
    );
    Log::debug('Default adobe_fonts initialized.');
  }

  /**
   * Provides defaults for adobe fonts.
   */
  private function defaults() {
    return [
      'token' => '',
      'isConnected' => false,
      'kits' => [],
      'fonts' => []
    ];
  }
}
