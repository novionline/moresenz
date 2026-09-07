<?php

// namespace Nectar\API;

use Nectar\API\{Router};
// use Nectar\Import_Export\{Theme_IE};

require_once NECTAR_THEME_DIRECTORY . '/nectar/theme-import-export.php';

/**
 * Import Export  API
 * @version 0.1.5
 * @since 0.1.5
 */
class Theme_Import_Export_API {
  const API_BASE = '/import-export';

  public function build_routes() {
    if ( ! class_exists('Nectar\API\Router')) {
      return;
    }

    // No explicit nonce check below, by design: core's rest_cookie_check_errors()
    // runs on `rest_authentication_errors` before dispatch. A cookie-authenticated
    // request carrying a bad `wp_rest` nonce is rejected there outright, and one
    // carrying no nonce at all has its current user reset to 0 — which is what
    // is_user_logged_in() then sees. Repeating the check here would guard nothing
    // extra and would break the auth methods core deliberately exempts
    // (application passwords and the like send no nonce at all).
    // Remote URLs the import sideloads are vetted separately, in
    // theme-import-export.php::is_safe_remote_url().
    Router::add_route($this::API_BASE . '/theme/import', [
      'callback' => [$this, 'theme_import'],
      'methods' => 'POST',
      'permission_callback' => function() {
        if ( is_user_logged_in() ) {
          return current_user_can('manage_options');
        }
        return false;
      }
    ]);

    Router::add_route($this::API_BASE . '/theme/export', [
      'callback' => [$this, 'theme_export'],
      'methods' => 'POST',
      'permission_callback' => function() {
        if ( is_user_logged_in() ) {
          return current_user_can('manage_options');
        }
        return false;
      }
    ]);
  }

  public function theme_import(\WP_REST_Request $request) {
    $files = $request->get_file_params();

    if ( ! empty( $files ) && ! empty( $files['file'] ) ) {
      $file = $files['file'];
    }
    $raw = file_get_contents( $file['tmp_name'] );

    $parsed_json = json_decode($raw, true);
    if ( $parsed_json === null ) {
      return new \WP_REST_Response([ 'status' => 'false'], 200);
    }

    $instance = Theme_IE::get_instance();
    $instance->import_options($parsed_json);

    // Send response
    $response = new \WP_REST_Response([ 'status' => 'success'], 200);
    return $response;
  }

  public function theme_export(\WP_REST_Request $request) {
    $instance = Theme_IE::get_instance();
    $data = $instance->export_options();

    $response = new \WP_REST_Response([
      'status' => 'success',
      'data' => $data
    ], 200);
    return $response;
  }
}
