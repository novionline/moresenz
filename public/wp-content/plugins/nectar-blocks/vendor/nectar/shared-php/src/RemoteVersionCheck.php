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
   * Fetch remote version data from the Nectar API (or return cached data).
   *
   * @param string $update_url   Full URL for the upgrade endpoint (e.g. https://api.example.com/v1/upgrade/plugin).
   * @param string $token       License token.
   * @param string $transient_key Cache key for storing the response.
   * @param bool   $cache_allowed Whether to use/set transient cache.
   * @return object|false Decoded response data object, or false on failure.
   */
  public static function fetch( $update_url, $token, $transient_key, $cache_allowed = true ) {
    if ( $token === '' ) {
      return false;
    }

    $remote = get_transient( $transient_key );

    if ( $remote === 'error' ) {
      return false;
    }

    if ( $remote !== false && $cache_allowed ) {
      return $remote;
    }

    $response = wp_safe_remote_post( $update_url, [
      'method'  => 'POST',
      'timeout' => 10,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'body'    => wp_json_encode( [ 'token' => $token ] ),
    ] );

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
   * Clear the version check cache (e.g. after an upgrade).
   *
   * @param string $transient_key Cache key passed to fetch().
   */
  public static function purge( $transient_key ) {
    delete_transient( $transient_key );
  }
}
