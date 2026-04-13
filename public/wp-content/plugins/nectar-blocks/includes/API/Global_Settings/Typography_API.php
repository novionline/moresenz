<?php

namespace Nectar\API\Global_Settings;
use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Global_Typography};

/**
 * Typography_API
 * @version 0.0.2
 * @since 0.0.2
 */
class Typography_API implements API_Route {
  const API_BASE = '/settings/typography';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_typographys'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/add', [
      'callback' => [$this, 'add_typography'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/delete', [
      'callback' => [$this, 'delete_typography'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/update', [
      'callback' => [$this, 'update_typography'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/google_fonts', [
      'callback' => [$this, 'get_google_fonts'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

  }

  public function get_typographys() {
    $typographys = Global_Typography::get_options();
    $response = new \WP_REST_Response($typographys, 200);
    return $response;
  }

  public function add_typography(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $class_name = $json_body['className'];

    // Only can add user typographys
    $typographys = Global_Typography::get_options();
    $new_typo = Global_Typography::default_typography();
    $new_typo_index = count($typographys['userTypography']) + 1;
    $new_typo['label'] = "{$json_body['label']} #{$new_typo_index}";
    $typographys['userTypography'][$class_name] = $new_typo;

    Global_Typography::update_options($typographys);
    $typographys = Global_Typography::get_options();
    $response = new \WP_REST_Response($typographys, 200);
    return $response;
  }

  public function delete_typography(\WP_REST_Request $request) {

    $json_body = $request->get_json_params();
    $slug = $json_body['slug'];
    $reassignment = $json_body['reassignment'];

    $typographys = Global_Typography::get_options();

    // Reassigning font.
    if ( ! empty($reassignment) ) {
      $typographys['userTypography'][$slug]['reassigned'] = $reassignment;
    } else {
      unset($typographys['userTypography'][$slug]);
    }

    Global_Typography::update_options($typographys);

    $typographys = Global_Typography::get_options();
    $response = new \WP_REST_Response($typographys, 200);
    return $response;
  }

  public function update_typography(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $className = $json_body['className'];
    $typography_type = $json_body['typographyType'];
    $typography_data = $json_body['typographyData'];

    $typographys = Global_Typography::get_options();
    $typographys[$typography_type][$className] = $typography_data;

    Global_Typography::update_options($typographys);

    $typographys = Global_Typography::get_options();
    $response = new \WP_REST_Response($typographys, 200);
    return $response;
  }

  public function get_google_fonts() {
    $fonts = wp_json_file_decode(NECTAR_BLOCKS_ROOT_DIR_PATH . '/assets/build/google-fonts/google-fonts-1747078311.json');
    $response = new \WP_REST_Response($fonts, 200);
    return $response;
  }
}

