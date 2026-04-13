<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\Log;

/**
 * Nectar Plugin Options.
 * @version 1.3.0
 * @since 1.3.0
 */
class Nectar_Plugin_Options extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_blocks_plugin_options';

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
    Log::debug('Default nectar_blocks_plugin_options initialized');
  }

  /**
   * Provides defaults for nectar plugin options.
   */
  private function defaults() {
    return [
      'shouldHideTitleDefault' => false,
      'shouldDisableNectarGlobalTypography' => false,
      'defaultTextBlock' => 'nectar',
    ];
  }
}
