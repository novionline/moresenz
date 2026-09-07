<?php

declare(strict_types=1);

namespace Nectar\Licensing;

use Nectar\Global_Settings\Nectar_Blocks_Options;

/**
 * Expired_Updates_Service
 *
 * Fetches — at most once a day — the "expired license" summary from the Nectar
 * licensing API and caches it in a transient. Shared by the admin "resubscribe"
 * notice and the editor Template Library banner so the endpoint is pinged once
 * per day in total, regardless of which surface triggers the first read.
 *
 * Mirrors the token-POST + transient + error-sentinel pattern in
 * Nectar\Shared\RemoteVersionCheck, but with a 24h TTL.
 *
 * @since 3.0.0
 */
class Expired_Updates_Service {
  const TRANSIENT_KEY = 'nectar_expired_updates';

  // Resolved at class-load time, so NECTAR_HOST_URL (defined in nectar-vars.php
  // during the normal plugin bootstrap) must already exist before this class is
  // autoloaded. Standard load order guarantees that; tests define it explicitly.
  const UPDATE_URL = 'https://api.' . NECTAR_HOST_URL . '/v1/license/expired-updates';

  // Brief sentinel written before the remote call so a burst of cold-cache
  // requests doesn't all fire the HTTP request at once (thundering herd).
  const INFLIGHT_TTL = 30;

  /**
   * Returns the decoded `data` object from the endpoint (cached), or null when
   * there is no license token or the request fails. The object is returned
   * regardless of `hasExpiredLicense` — callers gate on that flag themselves so
   * active subscribers (hasExpiredLicense === false) simply render nothing.
   *
   * NOTE: on a cold cache this makes a synchronous 5s outbound request, so it
   * briefly blocks the admin page / Template Library response. Don't call it on a
   * front-end / non-admin request without first priming the cache.
   *
   * @return object|null { hasExpiredLicense, expiredAt, newFeatures, bugFixes,
   *                       newTemplates, latestVersion, signature }
   */
  public static function get_data() {
    $token = self::get_token();

    // No token => the site was never activated, so "resubscribe" doesn't apply.
    if ( $token === '' ) {
      return null;
    }

    return self::fetch_for_token( $token );
  }

  /**
   * Reads the license token from options. Split out so fetch_for_token() can be
   * unit-tested without faking the static options class.
   */
  private static function get_token(): string {
    $nb_options = Nectar_Blocks_Options::get_options();
    return is_array( $nb_options ) && isset( $nb_options['token'] ) ? (string) $nb_options['token'] : '';
  }

  /**
   * Transient-cached fetch for a known non-empty token. Separated from
   * get_data() so the caching/sentinel branches can be unit-tested with the WP
   * HTTP + transient functions stubbed (get_data()'s token read hits a static
   * options class the test harness can't easily fake).
   *
   * @internal Public only so the unit tests can invoke it directly; not part of
   *           this class's supported surface. Plugin code should call get_data().
   * @return object|null
   */
  public static function fetch_for_token( string $token ) {
    $cached = get_transient( self::TRANSIENT_KEY );

    // 'error' = recent failure; 'inflight' = another request is already fetching.
    // Either way, skip the remote call and render nothing this load.
    if ( $cached === 'error' || $cached === 'inflight' ) {
      return null;
    }

    if ( $cached !== false ) {
      // A non-object here is a legacy/corrupt value (we only ever write an object
      // or the sentinels handled above). Purge it so the next load can re-fetch
      // instead of returning null until the TTL expires.
      if ( is_object( $cached ) ) {
        return $cached;
      }
      delete_transient( self::TRANSIENT_KEY );
      return null;
    }

    $body = wp_json_encode( [ 'token' => $token ] );

    // wp_json_encode returns false on invalid UTF-8; don't fire a doomed request
    // (and pay the timeout) — record the short error sentinel and bail.
    if ( $body === false ) {
      set_transient( self::TRANSIENT_KEY, 'error', MINUTE_IN_SECONDS * 10 );
      return null;
    }

    // Claim the fetch before the request so concurrent cold-cache loads don't all
    // hit the API. A small TOCTOU window remains with DB-backed transients (the
    // duplicate request is harmless); the sentinel self-expires so a crashed
    // request can't wedge the cache.
    set_transient( self::TRANSIENT_KEY, 'inflight', self::INFLIGHT_TTL );

    $response = wp_safe_remote_post( self::UPDATE_URL, [
      'method' => 'POST',
      // Kept short: this runs synchronously on a cold-cache admin page load.
      'timeout' => 5,
      'headers' => [ 'Content-Type' => 'application/json' ],
      'body' => $body,
    ] );

    // JWT V2: an expired/invalid access token returns 401. Refresh the token once
    // and retry with the new one before falling through to the error sentinel. The
    // retry is an inline POST (not a recursive fetch_for_token call), so a second
    // 401 simply drops to the guard ladder below — no reauth loop. V1 tokens never
    // 401, and reauthorize() is a no-op on a V1 site, so this is inert there.
    //
    // The body's error_code (when the backend sends one) tells reauthorize() why the
    // token was rejected, so a moved site's aud_mismatch skips the pointless refresh.
    if (
      ! is_wp_error( $response )
      && (int) wp_remote_retrieve_response_code( $response ) === 401
    ) {
      $new_token = Token_Service::reauthorize( self::error_code_from_json( wp_remote_retrieve_body( $response ) ) );
      if ( is_string( $new_token ) && $new_token !== '' ) {
        $retry_body = wp_json_encode( [ 'token' => $new_token ] );
        if ( $retry_body !== false ) {
          $response = wp_safe_remote_post( self::UPDATE_URL, [
            'method' => 'POST',
            'timeout' => 5,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => $retry_body,
          ] );
        }
      }
    }

    if (
      is_wp_error( $response )
      || (int) wp_remote_retrieve_response_code( $response ) !== 200
      || wp_remote_retrieve_body( $response ) === ''
    ) {
      // Short error TTL so a transient outage doesn't suppress the notice for a
      // full day, but we still don't hammer the endpoint on every page load.
      set_transient( self::TRANSIENT_KEY, 'error', MINUTE_IN_SECONDS * 10 );
      return null;
    }

    $json = json_decode( wp_remote_retrieve_body( $response ) );

    // Require an object payload: caching a falsy `data` would be indistinguishable
    // from a cache miss. The explicit is_object($json) guard also keeps the
    // $json->status access safe against a top-level JSON array/scalar.
    if ( ! $json || ! is_object( $json ) || ! isset( $json->status ) || $json->status !== 'success' || ! isset( $json->data ) || ! is_object( $json->data ) ) {
      set_transient( self::TRANSIENT_KEY, 'error', MINUTE_IN_SECONDS * 10 );
      return null;
    }

    set_transient( self::TRANSIENT_KEY, $json->data, DAY_IN_SECONDS );

    return $json->data;
  }

  /**
   * Read the JWT V2 `error_code` out of a 401 response body.
   *
   * Deliberately tolerant: an older backend answers 401 with no `error_code` at all,
   * and reauthorize() treats null as "decide locally", so anything that isn't a
   * non-empty string field reads as absent rather than as an error.
   *
   * @param string|mixed $body Raw response body.
   * @return string|null The error_code, or null when the body carries none.
   */
  private static function error_code_from_json( $body ): ?string {
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
   * Clears the cached result (e.g. for a future manual "refresh" action or tests).
   */
  public static function purge() {
    delete_transient( self::TRANSIENT_KEY );
  }
}
