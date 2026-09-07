<?php

declare(strict_types=1);

namespace Nectar\API\Licensing;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Licensing\Expired_Updates_Service;

/**
 * Expired_Updates_API
 *
 * Exposes the cached "expired license" summary to the block editor (the
 * Template Library "new templates" banner) so the editor doesn't ping the
 * external API itself. The once-a-day fetch + cache lives in
 * Expired_Updates_Service, shared with the admin "resubscribe" notice.
 *
 * @version 1.0.0
 * @since 3.0.0
 */
class Expired_Updates_API implements API_Route {
  const API_BASE = '/license/expired-updates';

  public function build_routes() {
    Router::add_route( $this::API_BASE, [
      'callback' => [ $this, 'get_expired_updates' ],
      'methods' => 'GET',
      'permission_callback' => function() {
        // edit_posts (not manage_options): the editor "renew" banner is shown to
        // non-admin editors too. Payload is non-sensitive — counts, version,
        // dismissal signature, and renewUrl, which carries only the numeric key id
        // (.../licenses?key=<id>), never the secret license key.
        return Access_Utils::can_edit_posts();
      }
    ] );
  }

  /**
   * Returns the cached endpoint data, or null when there is no license token
   * or the upstream request failed.
   */
  public function get_expired_updates() {
    return new \WP_REST_Response( Expired_Updates_Service::get_data(), 200 );
  }
}
