<?php

namespace Nectar\Global_Settings;

use Nectar\Global_Settings\Settings_Base;
use Nectar\Utilities\Log;

/**
 * Nectar Blocks Options.
 * @version 2.0.0
 * @since 0.0.4
 */
class Nectar_Blocks_Options extends Settings_Base {
  public static string $OPTION_NAME = 'nectar_blocks_options';

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
    Log::debug('Default nectar_options initialized');
  }

  /**
   * Provides defaults for global options.
   */
  private function defaults() {
    return [
      // Versioning
      // The current NB version.
      'currentNBVersion' => NECTAR_BLOCKS_VERSION,
      // The NB version when a migration was last ran.
      'migrationVersion' => NECTAR_BLOCKS_VERSION,

      // Licensing
      'licenseKey' => '',
      'isLicenseActive' => false,
      'token' => '',
      // JWT V2 refresh lifecycle. All optional and default-safe: a stored
      // options row that predates these keys reads them via `?? <default>` at
      // every call site, and tokenVersion 1 (absent => 1) means the eternal-token
      // V1 behavior is preserved with no cron and no expiry enforcement.
      'refreshToken' => '',       // opaque, single-use; rotated on every refresh
      'tokenExpiresAt' => 0,      // absolute unix ts (time()+expiresIn), not the raw expiresIn
      'tokenVersion' => 1,        // 1 = legacy eternal token; 2 = refreshable pair
      'registeredHostname' => '', // exact hostname sent to /register; source of truth for aud + refresh
      // Settings
      'autoUpdate' => false,
      'analytics' => false,
      'bugReports' => true
    ];
  }
}
