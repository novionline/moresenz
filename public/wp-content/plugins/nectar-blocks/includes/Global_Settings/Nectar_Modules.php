<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\Log;

/**
 * Nectar Modules Options.
 * @version 2.0.0
 * @since 2.0.0
 */
class Nectar_Modules extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_blocks_module_options';

  function __construct() {
    $this->initialize_defaults();
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
    Log::debug('Default nectar_modules initialized');
  }

  /**
   * Provides defaults for nectar plugin options.
   */
  private function defaults() {
    return [
      'portfolioPostType' => true
    ];
  }
}
