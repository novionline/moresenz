<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\Log;

/**
 * Nectar Blocks Code Options.
 * @version 0.0.9
 * @since 0.0.9
 */
class Code_Options extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_code_options';

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
    Log::debug('Default nectar_code initialized');
  }

  /**
   * Provides defaults for global options.
   */
  private function defaults() {
    return [
      'cssCode' => '',
      'jsCodeHead' => '',
      'jsCodeBody' => ''
    ];
  }
}
