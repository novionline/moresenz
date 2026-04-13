<?php

namespace Nectar\API\Global_Settings;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Global_Colors};

/**
 * Color_API
 * @version 0.0.2
 * @since 0.0.4
 */
class Color_API implements API_Route {
  const API_BASE = '/settings/colors';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_colors'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/add', [
      'callback' => [$this, 'add_color'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/delete', [
      'callback' => [$this, 'delete_color'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/update', [
      'callback' => [$this, 'update_color'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/update-palette', [
      'callback' => [$this, 'update_palette'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);
  }

  /**
   *
   */
  public function get_colors() {
    // TODO: Figure out theme support colors
    // $theme_colors = Global_Colors::get_theme_support_colors();
    // $theme_gradients = Global_Colors::get_theme_support_gradients();

    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }

  /**
   * add_color
   */
  public function add_color(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $color_type = $json_body['color_type'];
    $slug = $json_body['slug'];
    $label = $json_body['label'];
    $value = $json_body['value'];

    $colors = Global_Colors::get_options();
    array_push($colors[$color_type], [
      'slug' => $slug,
      'label' => $label,
      'value' => $value
    ]);

    Global_Colors::update_options($colors);
    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }

  /**
   * remove_color
   */
  public function delete_color(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $color_type = $json_body['color_type'];
    $slug = $json_body['slug'];
    $reassignment = $json_body['reassignment'];

    $colors = Global_Colors::get_options();

    // Reassigning color.
    if (! empty($reassignment)) {

      $updated_color_array = array_map(function ($e) use ($reassignment, $slug) {
        if ($e['slug'] === $slug) {
          $e['reassigned'] = $reassignment;
        }
        return $e;
      }, $colors[$color_type]);
      $colors[$color_type] = array_values($updated_color_array);

    }
    // Deleting color without reassignment
    else {
      $updated_color_array = array_filter($colors[$color_type], fn ($e) => $e['slug'] !== $slug);
      $colors[$color_type] = array_values($updated_color_array);
    }

    Global_Colors::update_options($colors);
    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }

  /**
   * update_color
   */
  public function update_color(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $color_type = $json_body['color_type'];
    $slug = $json_body['slug'];
    $label = $json_body['label'];
    $value = $json_body['value'];

    $colors = Global_Colors::get_options();
    $found_key = array_search($slug, array_column($colors[$color_type], 'slug'));
    $colors[$color_type][$found_key] = [
      'slug' => $slug,
      'label' => $label,
      'value' => $value
    ];

    Global_Colors::update_options($colors);
    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }

  /**
   * update color palette
   */
  public function update_palette(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $selectedPalette = $json_body['palette'];
    $colors = Global_Colors::get_options();

    foreach ($selectedPalette as $slug => $value) {
      $found_key = array_search($slug, array_column($colors['coreSolids'], 'slug'));
      $colors['coreSolids'][$found_key]['value'] = $value;
    }

    $coreSolids = [];
    foreach ($colors['coreSolids'] as $solid) {
      $coreSolids[$solid['slug']] = $solid;
    }

    // Create gradients
    $colors['coreGradients'] = Global_Colors::create_gradients($coreSolids);

    Global_Colors::update_options($colors);
    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }
}
