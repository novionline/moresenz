<?php

declare(strict_types=1);

namespace Nectar\Licensing;

/**
 * License_Client
 *
 * Side-effect-free HTTP wrapper around the external Nectar licensing API's token
 * endpoints (register / refresh / transfer / deregister). Every input is passed as an
 * argument — this class never reads WP options or writes transients — so its
 * request building and response parsing are unit-testable with only the WP HTTP
 * functions stubbed (mirrors the fetch_for_token() split in Expired_Updates_Service).
 *
 * Unlike Expired_Updates_Service / RemoteVersionCheck, which collapse every failure
 * to a single 'error' sentinel, post() returns the HTTP status code so callers can
 * tell a 401 (terminal for this token pair: Token_Service routes it by error_code
 * to a recovery re-register, a give-up, or a leave-alone) apart from a transient
 * 5xx (leave the stored tokens untouched and try again later).
 *
 * @since 3.1.0
 */
class License_Client {
  // Resolved at class-load, like Expired_Updates_Service::UPDATE_URL. NECTAR_HOST_URL
  // is defined in nectar-vars.php during bootstrap; tests define it explicitly.
  const BASE_URL = 'https://api.' . NECTAR_HOST_URL . '/v1';

  // Short: refresh runs synchronously from WP-Cron and (reactively) from an admin
  // request, so a dead endpoint must not hang the request for long.
  const TIMEOUT = 8;

  /**
   * POST /license/register — obtain a token (V1) or token pair (V2) for a license
   * key + hostname.
   *
   * @param array $analytics Optional environment payload (php/wp/plugin versions).
   */
  public static function register( string $license, string $hostname, array $analytics = [] ): array {
    return self::post( '/license/register', [
      'license' => $license,
      'hostname' => $hostname,
      'analytics' => $analytics,
      // Explicit so the backend can version-gate V2 issuance reliably rather than
      // parsing the plugin version out of the analytics blob (see the register.ts
      // follow-up in the plan).
      'pluginVersion' => NECTAR_BLOCKS_VERSION,
    ] );
  }

  /**
   * POST /license/refresh — exchange the single-use refresh token for a new pair.
   */
  public static function refresh( string $refresh_token, string $hostname ): array {
    return self::post( '/license/refresh', [
      'refreshToken' => $refresh_token,
      'hostname' => $hostname,
    ] );
  }

  /**
   * POST /license/transfer — move the activation behind $old_token to
   * $new_hostname (admin-confirmed domain move). Only the token's signature is
   * checked server-side (exp/aud ignored), the source activation is ALWAYS marked
   * inactive on success, and the response carries a fresh V2 pair in the same
   * envelope as refresh. Rate limited to 3 transfers per key per 7 days.
   *
   * error_codes: invalid_hostname (400); invalid_token, activation_not_found,
   * activation_inactive, license_mismatch, key_inactive (401);
   * max_activations_reached, max_dev_activations_reached (409);
   * transfer_rate_limited (429; the per-IP limiter's 429 carries no code);
   * transfer_failed (500).
   */
  public static function transfer( string $old_token, string $new_hostname, string $license ): array {
    return self::post( '/license/transfer', [
      'oldToken' => $old_token,
      'newHostname' => $new_hostname,
      'licenseKey' => $license,
      // Same version gate register() feeds: transfer mints exactly what register
      // would for this client, so omitting it would silently downgrade a moving V2
      // site to a V1 token.
      'pluginVersion' => NECTAR_BLOCKS_VERSION,
    ] );
  }

  /**
   * POST /license/deregister — release the activation for this token + hostname.
   */
  public static function deregister( string $token, string $hostname ): array {
    return self::post( '/license/deregister', [
      'token' => $token,
      'hostname' => $hostname,
    ] );
  }

  /**
   * A result array for a failure decided locally, without an HTTP call (e.g. a
   * caller's own precondition guard). Same eight keys post() returns, so callers
   * never have to special-case where the failure came from.
   */
  public static function failure( string $error_code, int $code = 0 ): array {
    $result = self::empty_result();
    $result['error_code'] = $error_code;
    $result['code'] = $code;

    return $result;
  }

  /**
   * The canonical result shape, before anything is parsed into it.
   *
   * @return array{
   *   ok: bool, code: int, error_code: ?string, message: ?string, token: ?string,
   *   refreshToken: ?string, expiresIn: ?int, tokenVersion: int
   * }
   */
  private static function empty_result(): array {
    return [
      'ok' => false,
      'code' => 0,
      'error_code' => null,
      'message' => null,
      'token' => null,
      'refreshToken' => null,
      'expiresIn' => null,
      'tokenVersion' => 1,
    ];
  }

  /**
   * Shared POST + parse. Reuses the guard ladder proven in
   * Expired_Updates_Service::fetch_for_token() (is_wp_error → response code →
   * empty body → json_decode → is_object) but *returns* the parsed fields plus the
   * HTTP code instead of caching a sentinel.
   *
   * V2 token fields are read from the `data` envelope when present (matching the
   * existing `{status:'success', data:{...}}` register shape) and fall back to the
   * top level, so a bare `{token, refreshToken, expiresIn}` refresh body parses too.
   *
   * @return array{
   *   ok: bool, code: int, error_code: ?string, token: ?string,
   *   refreshToken: ?string, expiresIn: ?int, tokenVersion: int
   * }
   */
  private static function post( string $path, array $body ): array {
    $result = self::empty_result();

    $encoded = wp_json_encode( $body );
    if ( $encoded === false ) {
      return $result; // invalid UTF-8; don't fire (and pay the timeout on) a doomed request
    }

    $response = wp_safe_remote_post( self::BASE_URL . $path, [
      'method' => 'POST',
      'timeout' => self::TIMEOUT,
      'headers' => [ 'Content-Type' => 'application/json' ],
      'body' => $encoded,
    ] );

    if ( is_wp_error( $response ) ) {
      return $result; // transport failure; code stays 0
    }

    $result['code'] = (int) wp_remote_retrieve_response_code( $response );
    $raw = wp_remote_retrieve_body( $response );

    if ( $raw === '' ) {
      return $result;
    }

    $json = json_decode( $raw );
    if ( ! is_object( $json ) ) {
      return $result;
    }

    // The failure envelope carries a stable machine-readable reason at the top
    // level, plus an English `message` fallback for codes the UI doesn't map yet.
    if ( isset( $json->error_code ) ) {
      $result['error_code'] = (string) $json->error_code;
    }
    if ( isset( $json->message ) ) {
      $result['message'] = (string) $json->message;
    }

    // Token fields live under `data` in the register envelope; fall back to root
    // so an unwrapped refresh body parses identically.
    $data = ( isset( $json->data ) && is_object( $json->data ) ) ? $json->data : $json;

    if ( isset( $data->token ) ) {
      $result['token'] = (string) $data->token;
    }
    if ( isset( $data->refreshToken ) ) {
      $result['refreshToken'] = (string) $data->refreshToken;
    }
    if ( isset( $data->expiresIn ) ) {
      $result['expiresIn'] = (int) $data->expiresIn;
    }
    if ( isset( $data->tokenVersion ) ) {
      $result['tokenVersion'] = (int) $data->tokenVersion;
    }

    // ok = the HTTP call itself succeeded (200 + a success status when one is
    // present). Field-presence (the rotation guard) is the caller's job, because
    // deregister legitimately succeeds with no token in the body.
    $status_ok = ! isset( $json->status ) || $json->status === 'success';
    $result['ok'] = ( $result['code'] === 200 && $status_ok );

    return $result;
  }
}
