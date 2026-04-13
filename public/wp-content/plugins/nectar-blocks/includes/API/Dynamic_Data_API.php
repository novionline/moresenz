<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route};
use Nectar\Dynamic_Data\{Dynamic_Helpers, Frontend_Render};
use Nectar\Utilities\Log;
/**
 * Dynamic Data API
 * @version 2.0.0
 * @since 2.0.0
 */
class Dynamic_Data_API implements API_Route {
  const API_BASE = '/dynamic-data';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_dynamic_data'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);
  }

  public function get_dynamic_data(\WP_REST_Request $request) {
    # Should be an array of dynamic data strings
    $dynamic_data = $request->get_param( 'dynamicData' );

    Log::info(json_encode($dynamic_data));

    // if ( empty($dynamicData) ) {
    //   Log::info('Empty dynamic data');
    //   $response = new \WP_REST_Response(['status' => 'failure'], 200);
    //   return $response;
    // }

    $values = [];
    foreach ($dynamic_data as $dynamic_path) {
      $dynamic_data_parsed = Dynamic_Helpers::parse_dynamic_field($dynamic_path);
      $content = Frontend_Render::get_dynamic_content($dynamic_data_parsed, true);
      $values[$dynamic_path] = $content;
    }

    Log::info('get_dynamic_data resp values', $values);

    $response = new \WP_REST_Response([
      'status' => 'success',
      'values' => (object) $values
    ], 200);
    return $response;
  }
}