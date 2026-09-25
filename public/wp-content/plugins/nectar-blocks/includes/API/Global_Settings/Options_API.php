<?php

namespace Nectar\API\Global_Settings;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Nectar_Blocks_Options};

/**
 * Options_API
 * @version 0.0.4
 * @since 0.0.4
 */
class Options_API implements API_Route {
  const API_BASE = '/settings/options';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_options'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);
  }

  /**
   * Returns the Nectar Options dict.
   */
  public function get_options() {
    $options = Nectar_Blocks_Options::get_options();
    $response = new \WP_REST_Response($options, 200);
    return $response;
  }
}
