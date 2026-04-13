<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route};

/**
 * Assets API
 * @version 1.3.1
 * @since 0.0.5
 */
class Assets_API implements API_Route {
  const API_BASE = '/assets';

  public function build_routes() {
    Router::add_route($this::API_BASE . '/icons/remix', [
      'callback' => [$this, 'get_remix_icons'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/icons/lucide', [
      'callback' => [$this, 'get_lucide_icons'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/icons/simple-icons', [
      'callback' => [$this, 'get_simple_icons_icons'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);
  }

  public function get_remix_icons() {
    $icons = wp_json_file_decode(NECTAR_BLOCKS_ROOT_DIR_PATH . '/assets/build/remix-icons/icons.json');
    $response = new \WP_REST_Response($icons, 200);
    return $response;
  }

  public function get_lucide_icons() {
    $icons = wp_json_file_decode(NECTAR_BLOCKS_ROOT_DIR_PATH . '/assets/build/lucide-icons/icons.json');
    $response = new \WP_REST_Response($icons, 200);
    return $response;
  }

  public function get_simple_icons_icons() {
    $icons = wp_json_file_decode(NECTAR_BLOCKS_ROOT_DIR_PATH . '/assets/build/simple-icons/icons.json');
    $response = new \WP_REST_Response($icons, 200);
    return $response;
  }
}