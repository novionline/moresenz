<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route};
use Nectar\Utilities\Log;
use Nectar\Import_Export\Plugin_IE;

/**
 * Import Export  API
 * @version 0.1.5
 * @since 0.1.5
 */
class Import_Export_API implements API_Route {
  const API_BASE = '/import-export';

  public function build_routes() {
    Router::add_route($this::API_BASE . '/plugin/import', [
      'callback' => [$this, 'plugin_import'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/plugin/export', [
      'callback' => [$this, 'plugin_export'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);
  }

  public function plugin_import(\WP_REST_Request $request) {
    $files = $request->get_file_params();
    Log::debug('plugin files', $files);

    if ( ! empty( $files ) && ! empty( $files['file'] ) ) {
      $file = $files['file'];
    }
    $raw = file_get_contents( $file['tmp_name'] );
    $parsed_json = json_decode($raw, true);

    if ( $parsed_json === null ) {
      return new \WP_REST_Response([ 'status' => 'false'], 200);  
    }

    $instance = Plugin_IE::get_instance();
    $instance->import_options($parsed_json);

    $response = new \WP_REST_Response([ 'status' => 'success'], 200);
    return $response;
  }

  public function plugin_export(\WP_REST_Request $request) {
    $instance = Plugin_IE::get_instance();
    $data = $instance->export_options();
    $response = new \WP_REST_Response([
      'status' => 'success',
      'data' => $data
    ], 200);
    return $response;
  }
}