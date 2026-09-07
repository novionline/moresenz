<?php

declare(strict_types=1);

namespace Nectar\Update;

use Nectar\Licensing\Expired_Updates_Service;
use Nectar\Shared\RemoteVersionCheck;

/**
 * Updaters
 *
 * The registry of Nectar update-check caches present on this install. The plugin
 * always has one; the theme and the importer-exporter register theirs only when
 * those products are installed, so both are probed with class_exists().
 *
 * Exists so the two places that invalidate update state — the admin "reset updater
 * transients" action and a license transfer, after which every updater's cached
 * check was made against a token bound to the old domain — clear exactly the same
 * set instead of each keeping its own list.
 *
 * @since 3.3.0
 */
class Updaters {
  /**
   * Every updater cache key registered on this install.
   *
   * @return string[]
   */
  public static function update_keys(): array {
    $keys = [ NectarBlocksUpdater::UPDATE_KEY ];

    if ( class_exists( '\\NectarThemeUpdater' ) ) {
      $keys[] = \NectarThemeUpdater::UPDATE_KEY;
    }

    if ( class_exists( '\\Nectar\\Update\\NectarBlocksIEUpdater' ) ) {
      $keys[] = \Nectar\Update\NectarBlocksIEUpdater::UPDATE_KEY;
    }

    return $keys;
  }

  /**
   * Drop every updater's cached version check, its error sentinel and its throttle
   * reservation, plus the cached "expired license" payload. Purging through
   * RemoteVersionCheck (rather than a bare delete_transient) is what lifts the
   * 15-minute remote-check floor, so the next check runs immediately instead of
   * being suppressed by a reservation made before the state changed.
   */
  public static function purge_all(): void {
    foreach ( self::update_keys() as $key ) {
      RemoteVersionCheck::purge( $key );
    }

    Expired_Updates_Service::purge();
  }
}
