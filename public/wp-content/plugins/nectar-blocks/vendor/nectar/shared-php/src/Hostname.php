<?php

namespace Nectar\Shared;

/**
 * The one canonical form for every hostname the licensing surfaces transmit:
 * lowercase, whitespace-trimmed, trailing root dot removed. Register records it,
 * the updaters and the Template Library report it, so the backend compares
 * byte-identical values on both sides of the JWT `aud` check (its own
 * canonicalHostname applies the same folding). www. is deliberately kept, and
 * wp_parse_url(PHP_URL_HOST) never yields a port, so neither needs handling.
 *
 * @since 1.0.0
 */
class Hostname {

  public static function canonicalize( string $host ): string {
    return strtolower( rtrim( trim( $host ), '.' ) );
  }

  /**
   * The site's own hostname, canonicalized — the single derivation shared by
   * /license/register (Token_Service::current_hostname) and the updaters'
   * upgrade-check body (RemoteVersionCheck::request_body).
   *
   * Resolved from home_url() rather than $_SERVER['HTTP_HOST'], whose port suffix
   * and proxy rewrites make it drift from the value the activation was registered
   * with. Both sides must derive it identically or a legitimate site fails the
   * `aud` check, which is why there is exactly one implementation.
   *
   * @return string Host component of home_url(), or '' when home_url() has no
   *                parseable host — wp_parse_url() returns null rather than throwing.
   */
  public static function site_host(): string {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );

    return is_string( $host ) ? self::canonicalize( $host ) : '';
  }
}
