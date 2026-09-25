<?php

declare(strict_types=1);

namespace Nectar\API\Licensing;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Licensing\{License_Client, Token_Service};

/**
 * License_API
 *
 * Server-side license register/deregister/transfer. Moved off the browser (which called the
 * external API directly) so token issuance has one source of truth — reused by the
 * cron recovery path — the access-token expiry is stamped on the server clock, and
 * the opaque refresh token never reaches the browser. Mirrors Expired_Updates_API's
 * Router wiring; both routes are manage_options + apiFetch nonce protected.
 *
 * Expected client outcomes (bad key, activation failed, …) return HTTP 200 with a
 * `{ status:'failure', error_code }` body — matching the external API's own
 * convention — so the admin UI reads the reason without apiFetch throwing.
 *
 * @version 1.0.0
 * @since 3.1.0
 */
class License_API implements API_Route {
  const REGISTER_ROUTE = '/license/register';

  const DEREGISTER_ROUTE = '/license/deregister';

  const TRANSFER_ROUTE = '/license/transfer';

  const TOKEN_ROUTE = '/license/token';

  public function build_routes() {
    Router::add_route( self::REGISTER_ROUTE, [
      'callback' => [ $this, 'register' ],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      },
      'args' => [
        'licenseKey' => [ 'type' => 'string', 'required' => true ],
        'analytics' => [ 'type' => 'object', 'required' => false ],
      ],
    ] );

    Router::add_route( self::DEREGISTER_ROUTE, [
      'callback' => [ $this, 'deregister' ],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      },
    ] );

    Router::add_route( self::TRANSFER_ROUTE, [
      'callback' => [ $this, 'transfer' ],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      },
    ] );

    Router::add_route( self::TOKEN_ROUTE, [
      'callback' => [ $this, 'token' ],
      'methods' => 'GET',
      'permission_callback' => function() {
        // edit_posts (not manage_options), matching Expired_Updates_API: this is
        // the Template Library's token, and the editor already receives it at page
        // load via wp_localize_script for exactly these users. No new exposure —
        // the route just lets a long-lived editor session re-read a token that was
        // rotated out from under its page-load copy.
        return Access_Utils::can_edit_posts();
      },
    ] );
  }

  /**
   * POST /license/register — activate a license key for this site. Persists the
   * token pair server-side and arms the refresh cron for a V2 token.
   */
  public function register( \WP_REST_Request $request ) {
    $body = $request->get_json_params();
    $license_key = isset( $body['licenseKey'] ) ? sanitize_text_field( (string) $body['licenseKey'] ) : '';
    // The registered hostname doubles as the aud identity for V2 refresh, so it is
    // derived server-side from home_url() — the same source the updater presents —
    // never taken from the request (the browser's host is whatever wp-admin was
    // reached over, which isn't necessarily the site's own).
    $hostname = Token_Service::current_hostname();
    $analytics = ( isset( $body['analytics'] ) && is_array( $body['analytics'] ) ) ? $body['analytics'] : [];

    if ( $license_key === '' || $hostname === '' ) {
      return new \WP_REST_Response( [ 'status' => 'failure', 'error_code' => 'invalid_key' ], 200 );
    }

    return $this->respond( Token_Service::activate( $license_key, $hostname, $analytics ) );
  }

  /**
   * POST /license/transfer — move this site's activation to its current home_url()
   * host after the admin confirmed the domain change (Domain_Changed_Notice). Takes
   * no input: token, key and hostname all come from server-side state.
   */
  public function transfer( \WP_REST_Request $request ) {
    return $this->respond( Token_Service::transfer() );
  }

  /**
   * GET /license/token — the access token currently stored for this site.
   *
   * The editor gets a snapshot of this token at page load; after a rotation (cron
   * or a reactive refresh) that snapshot is stale and the external template API
   * answers 401. This is how the editor picks up the current one without a reload.
   *
   * Returns only the token: `public_auth()` carries the license key too, which this
   * capability must not hand out.
   */
  public function token( \WP_REST_Request $request ) {
    $auth = Token_Service::public_auth();
    $token = (string) ( $auth['token'] ?? '' );

    if ( $token === '' ) {
      return new \WP_REST_Response( [ 'status' => 'failure', 'error_code' => 'no_token' ], 200 );
    }

    return new \WP_REST_Response( [ 'status' => 'success', 'token' => $token ], 200 );
  }

  /**
   * The shared response for the two routes that end in a stored activation. Both
   * report success on exactly the condition Token_Service persisted on, so the body
   * can never claim success over state that was not written — including an HTTP-ok
   * response that carried no storable activation, which reports failure with
   * whatever reason the client parsed (possibly none).
   *
   * @param array $r A License_Client result array.
   */
  private function respond( array $r ): \WP_REST_Response {
    if ( ! Token_Service::is_storable_activation( $r ) ) {
      return new \WP_REST_Response( [ 'status' => 'failure', 'error_code' => $r['error_code'], 'message' => $r['message'] ], 200 );
    }

    return new \WP_REST_Response( [ 'status' => 'success', 'auth' => Token_Service::public_auth() ], 200 );
  }

  /**
   * POST /license/deregister — release this site's activation. Clears licensing
   * state and stops the cron only after the external call confirms success (matching
   * the prior browser behavior).
   */
  public function deregister( \WP_REST_Request $request ) {
    $auth = Token_Service::public_auth();
    $token = (string) ( $auth['token'] ?? '' );
    // Fall back to the current site host for activations that predate the
    // registeredHostname key (it isn't backfilled), so the external deregister
    // still gets a real hostname to match instead of '' — mirrors reregister().
    $hostname = Token_Service::registered_hostname( $auth );

    if ( $token === '' ) {
      // No activation to release upstream; clear any residual state and report success.
      Token_Service::clear_registration();
      return new \WP_REST_Response( [ 'status' => 'success', 'auth' => Token_Service::public_auth() ], 200 );
    }

    $r = License_Client::deregister( $token, $hostname );

    // A 401 here is about the token, not the activation: deregister accepts an
    // expired access token only while its jti is still the activation's current one,
    // so a site whose token rotated since (cron refresh, reactive refresh) is
    // rejected and would be left permanently unable to release its own seat. Rotate
    // once and retry with the fresh token. Deliberately without the re-register
    // fallback — minting a NEW activation for an admin who asked to release this one
    // would be exactly backwards. If the refresh instead learns the seat is already
    // gone server-side (give-up: it clears the stored pair), the admin's desired end
    // state already holds — finish locally and report success, as the backend itself
    // does for a deregister of an INACTIVE activation. Any other refresh failure
    // reports the original failure below with local state intact.
    if ( ! $r['ok'] && $r['code'] === 401 ) {
      $refreshed = Token_Service::refresh( false );
      if ( $refreshed !== null && $refreshed !== '' ) {
        $r = License_Client::deregister( $refreshed, $hostname );
      } elseif ( (string) ( Token_Service::public_auth()['token'] ?? '' ) === '' ) {
        Token_Service::clear_registration();
        return new \WP_REST_Response( [ 'status' => 'success', 'auth' => Token_Service::public_auth() ], 200 );
      }
    }

    if ( ! $r['ok'] ) {
      return new \WP_REST_Response( [ 'status' => 'failure', 'error_code' => $r['error_code'], 'message' => $r['message'] ], 200 );
    }

    Token_Service::clear_registration();

    return new \WP_REST_Response( [ 'status' => 'success', 'auth' => Token_Service::public_auth() ], 200 );
  }
}
