<?php

namespace Nectar\Shared;

/**
 * Shared logic for Nectar update API: fetch version info with token, cache, and error handling.
 * Used by plugin, theme, and importer-exporter updaters.
 *
 * @since 1.0.0
 */
class RemoteVersionCheck {

  /**
   * Minimum seconds between remote update-check attempts for a given cache key.
   *
   * Enforced via a database option (see fetch()) so it holds even on hosts where the
   * transient cache never persists — e.g. a misconfigured external object cache, which
   * otherwise turns every `site_transient_update_*` read into a live API request. A plain
   * integer (not WordPress' MINUTE_IN_SECONDS) so the class still loads outside a WP
   * runtime for unit tests. ~15 minutes, matching the existing 10-minute error cadence.
   */
  const MIN_REMOTE_INTERVAL = 900;

  /**
   * Option-name prefix for the per-cache-key "next allowed attempt" timestamp.
   */
  const THROTTLE_OPTION_PREFIX = 'nectar_rvc_next_';

  /**
   * Per-request memo of fetch() results, keyed by cache key + token hash + cache flag. A
   * class property (not a fetch()-local static) so purge() can drop the matching entries
   * and a post-upgrade re-check within the same request re-fetches instead of returning the
   * stale pre-purge value. See fetch() for why it is populated and purge() for the clear.
   *
   * @var array<string,object|false>
   */
  private static $request_cache = [];

  /**
   * Fetch remote version data from the Nectar API (or return cached data).
   *
   * @param string $update_url   Full URL for the upgrade endpoint (e.g. https://api.example.com/v1/upgrade/plugin).
   * @param string $token       License token.
   * @param string $transient_key Cache key for storing the response.
   * @param bool   $cache_allowed Whether to use/set transient cache. When false, the caller
   *                              is forcing a fresh check, so the cross-request throttle floor
   *                              is bypassed rather than silently suppressing the request.
   * @param callable|null $reauth Optional JWT V2 reauth callback. On a 401 it is
   *   invoked to obtain a fresh token (returning a string) and the request is
   *   retried once. It receives the 401 body's `error_code` (or null when the
   *   backend sent none) as its single argument, so the callback can tell an
   *   expired token from a moved domain. Additive and backward compatible: a
   *   4-arg call passes null and behaves exactly as before, and a callback that
   *   declares no parameter still works. Only the plugin supplies one (it owns
   *   the token lifecycle); the theme/importer-exporter keep their 4-arg calls
   *   unchanged.
   * @return object|false Decoded response data object, or false on failure.
   */
  public static function fetch( $update_url, $token, $transient_key, $cache_allowed = true, $reauth = null ) {
    if ( $token === '' ) {
      return false;
    }

    // Per-request memo. WordPress fires the `site_transient_update_*` read filters that
    // drive these updaters many times per admin request; without this, each fire that
    // misses the persistent cache would issue its own network POST. Keyed by cache key +
    // token + $cache_allowed so a mid-request token change is never served a stale entry,
    // and a forced ($cache_allowed = false) check is never served a normal call's cached
    // memo. Held in a class property (self::$request_cache) so purge() can invalidate it.
    // `??=` is safe here: cached values are object|false, never null.
    $memo_key = $transient_key . '|' . md5( $token ) . '|' . ( $cache_allowed ? '1' : '0' );
    return self::$request_cache[ $memo_key ] ??= self::fetch_uncached( $update_url, $token, $transient_key, $cache_allowed, $reauth );
  }

  /**
   * Uncached worker for fetch(): transient cache, cross-request throttle floor, and the
   * network POST. Only runs on a per-request memo miss — fetch() stores every return
   * value into self::$request_cache.
   *
   * @param string $update_url    Full URL for the upgrade endpoint.
   * @param string $token         License token (never empty; fetch() guards).
   * @param string $transient_key Cache key for storing the response.
   * @param bool   $cache_allowed Whether to use/set transient cache (see fetch()).
   * @param callable|null $reauth Optional JWT V2 reauth callback (see fetch()).
   * @return object|false Decoded response data object, or false on failure.
   */
  private static function fetch_uncached( $update_url, $token, $transient_key, $cache_allowed, $reauth = null ) {
    $remote = get_transient( $transient_key );

    if ( $remote === 'error' ) {
      return false;
    }

    if ( $remote !== false && $cache_allowed ) {
      return $remote;
    }

    // Cross-request throttle. The transient checks above are the normal cache; this is a
    // floor for when they don't persist (a misconfigured object cache silently drops every
    // set_transient, so the fast paths always miss and each page load would hit the API).
    // The reservation lives in the options table, which survives a broken object cache, and
    // is claimed *before* the request. This is a best-effort floor, not a lock: it is a
    // check-then-act, so a rare race between truly simultaneous loads can still let more than
    // one attempt through — but it collapses the common repeated-load case to a single POST.
    //
    // Only the cache-backed path ($cache_allowed) is throttled. A caller passing
    // $cache_allowed = false is explicitly forcing a fresh check, so it bypasses the floor
    // rather than being swallowed by a prior reservation. Safe because every amplification
    // source (the site_transient_update_* read filters, all three consumers) uses the
    // default $cache_allowed = true; a forced check is a deliberate, infrequent action.
    if ( $cache_allowed ) {
      $throttle_option = self::THROTTLE_OPTION_PREFIX . $transient_key;
      $now = time();
      if ( ! self::interval_elapsed( (int) get_option( $throttle_option, 0 ), $now ) ) {
        return false;
      }
      update_option( $throttle_option, $now + self::MIN_REMOTE_INTERVAL, false );
    }

    $response = wp_safe_remote_post( $update_url, [
      'method'  => 'POST',
      'timeout' => 10,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body'    => self::request_body( $token ),
    ] );

    // JWT V2: on a 401 (expired/invalid access token) let the caller refresh the
    // token and retry once. The retry is inline, so a second 401 falls through to
    // the error sentinel below — no loop. The body's `error_code` goes to the
    // callback so it can skip the refresh for a moved domain (aud_mismatch), which
    // no amount of refreshing fixes.
    if (
      is_callable( $reauth )
      && ! is_wp_error( $response )
      && (int) wp_remote_retrieve_response_code( $response ) === 401
    ) {
      $new_token = call_user_func( $reauth, self::error_code_from_json( wp_remote_retrieve_body( $response ) ) );
      if ( is_string( $new_token ) && $new_token !== '' ) {
        $response = wp_safe_remote_post( $update_url, [
          'method'  => 'POST',
          'timeout' => 10,
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'body'    => self::request_body( $new_token ),
        ] );
      }
    }

    if (
      is_wp_error( $response )
      || wp_remote_retrieve_response_code( $response ) !== 200
      || wp_remote_retrieve_body( $response ) === ''
    ) {
      set_transient( $transient_key, 'error', MINUTE_IN_SECONDS * 10 );
      return false;
    }

    $json_data = json_decode( wp_remote_retrieve_body( $response ) );

    if ( ! $json_data || ( isset( $json_data->status ) && $json_data->status === 'failure' ) ) {
      set_transient( $transient_key, 'error', MINUTE_IN_SECONDS * 10 );
      return false;
    }

    $data = isset( $json_data->data ) ? $json_data->data : $json_data;
    set_transient( $transient_key, $data, 4 * HOUR_IN_SECONDS );

    return $data;
  }

  /**
   * Read the JWT V2 `error_code` out of a token-consumer 401 response body.
   *
   * Deliberately tolerant: an older backend answers 401 with no `error_code` at
   * all, and the callback treats null as "decide locally", so anything that is
   * not a non-empty string field reads as absent rather than as an error. Takes
   * the raw body (not the response array) so it stays free of WordPress calls
   * and is unit-testable inside this package.
   *
   * @param string|mixed $body Raw response body.
   * @return string|null The error_code, or null when the body carries none.
   */
  private static function error_code_from_json( $body ) {
    if ( ! is_string( $body ) || $body === '' ) {
      return null;
    }

    $decoded = json_decode( $body );

    if ( ! is_object( $decoded ) || ! isset( $decoded->error_code ) || ! is_string( $decoded->error_code ) || $decoded->error_code === '' ) {
      return null;
    }

    return $decoded->error_code;
  }

  /**
   * Build the JSON request body for an upgrade check.
   *
   * Carries the site's own hostname alongside the token so the backend can check
   * it against the JWT V2 `aud` claim. The field is optional on the wire: builds
   * that predate it send nothing and the backend skips the audience check for
   * that request, so this can ship ahead of any backend enforcement change.
   *
   * The reported host is deliberately the *live* one (Hostname::site_host(), the
   * same derivation /license/register records with) and not the stored
   * registeredHostname: the stored value is what `aud` was minted from, so
   * echoing it back would make the comparison tautological and enforce nothing.
   *
   * @param string $token License token to authenticate the check with.
   * @return string|false Encoded body, or false from wp_json_encode on bad UTF-8.
   */
  private static function request_body( $token ) {
    $body = [ 'token' => $token ];

    $hostname = Hostname::site_host();
    // Only ever send a non-empty value. The backend canonicalises whatever it
    // receives and rejects an unparseable hostname, so an empty string would
    // fail the whole request where an absent field is simply skipped.
    if ( $hostname !== '' ) {
      $body['hostname'] = $hostname;
    }

    return wp_json_encode( $body );
  }

  /**
   * Whether the reserved "next allowed attempt" time has passed. A 0 reservation
   * (option never set) is always treated as elapsed.
   *
   * @param int $next_allowed_ts Unix timestamp of the earliest permitted next attempt.
   * @param int $now             Current Unix timestamp.
   * @return bool True when a new remote attempt is allowed.
   */
  private static function interval_elapsed( $next_allowed_ts, $now ) {
    return $now >= $next_allowed_ts;
  }

  /**
   * Clear the version check cache (e.g. after an upgrade).
   *
   * @param string $transient_key Cache key passed to fetch().
   */
  public static function purge( $transient_key ) {
    delete_transient( $transient_key );
    // Drop the throttle reservation too, so a check that runs right after an upgrade
    // (to clear the "update available" flag) isn't blocked by the interval floor.
    delete_option( self::THROTTLE_OPTION_PREFIX . $transient_key );

    // Invalidate this key's per-request memo(s) so a re-check later in the *same* request
    // (WP core reads site_transient_update_* again after an upgrade) re-fetches instead of
    // returning the stale pre-purge value. purge() has neither token nor cache flag, so
    // clear every entry for this cache key (memo_key = "$transient_key|<token-hash>|<flag>").
    $memo_prefix = $transient_key . '|';
    foreach ( array_keys( self::$request_cache ) as $memo_key ) {
      if ( strpos( $memo_key, $memo_prefix ) === 0 ) {
        unset( self::$request_cache[ $memo_key ] );
      }
    }
  }
}
