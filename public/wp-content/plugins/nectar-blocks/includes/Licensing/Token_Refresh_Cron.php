<?php

declare(strict_types=1);

namespace Nectar\Licensing;

/**
 * Token_Refresh_Cron
 *
 * Thin wiring for the token-refresh WP-Cron: the custom ~12h schedule, the refresh
 * action, and the plugin's (first) deactivation hook. Kept small so Token_Service
 * stays WP-free-testable — all the decision logic lives there.
 *
 * @since 3.1.0
 */
class Token_Refresh_Cron {
  function __construct() {
    add_filter( 'cron_schedules', [ $this, 'add_schedule' ] );
    add_action( Token_Service::CRON_HOOK, [ Token_Service::class, 'cron_refresh' ] );
    register_deactivation_hook( NECTAR_BLOCKS_FILE, [ $this, 'on_deactivate' ] );
  }

  /**
   * Register the ~12h recurrence used for token refresh (the 24h access-token
   * half-life). Idempotent — skips if another load already added it.
   */
  public function add_schedule( $schedules ) {
    if ( ! isset( $schedules[Token_Service::CRON_SCHEDULE] ) ) {
      $schedules[Token_Service::CRON_SCHEDULE] = [
        'interval' => 12 * HOUR_IN_SECONDS,
        'display' => __( 'Twice Daily (Nectar Blocks token refresh)', 'nectar-blocks' ),
      ];
    }
    return $schedules;
  }

  public function on_deactivate() {
    Token_Service::unschedule_cron();
  }
}
