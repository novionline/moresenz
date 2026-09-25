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

    Router::add_route($this::API_BASE . '/reorder', [
      'callback' => [$this, 'reorder_colors'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    // Custom palette preset routes
    Router::add_route($this::API_BASE . '/palettes', [
      'callback' => [$this, 'get_palettes'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/palettes/add', [
      'callback' => [$this, 'add_palette'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/palettes/update', [
      'callback' => [$this, 'update_palette_preset'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/palettes/delete', [
      'callback' => [$this, 'delete_palette'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/palettes/reorder', [
      'callback' => [$this, 'reorder_palettes'],
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

    // Use provided gradients if available, otherwise regenerate.
    if (isset($json_body['gradients']) && is_array($json_body['gradients'])) {
      $colors['coreGradients'] = $json_body['gradients'];
    } else {
      $colors['coreGradients'] = Global_Colors::create_gradients($coreSolids);
    }

    Global_Colors::update_options($colors);
    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }

  /**
   * Reorder user color items.
   * Receives a color_type and an ordered array of slugs, then reconstructs the array in that order.
   */
  public function reorder_colors(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();

    if ( ! isset( $json_body['color_type'] ) || ! isset( $json_body['orderedSlugs'] ) ) {
      return new \WP_REST_Response( [ 'error' => 'Missing color_type or orderedSlugs' ], 400 );
    }

    $color_type = $json_body['color_type'];
    $ordered_slugs = $json_body['orderedSlugs'];

    if ( ! is_array( $ordered_slugs ) || ! in_array( $color_type, [ 'userSolids', 'userGradients' ], true ) ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid parameters' ], 400 );
    }

    $colors = Global_Colors::get_options();
    $current_items = $colors[$color_type];

    // Index current items by slug for quick lookup
    $items_by_slug = [];
    foreach ($current_items as $item) {
      $items_by_slug[$item['slug']] = $item;
    }

    // Rebuild array in the new order
    $reordered = [];
    foreach ($ordered_slugs as $slug) {
      if ( isset($items_by_slug[$slug]) ) {
        $reordered[] = $items_by_slug[$slug];
        unset($items_by_slug[$slug]);
      }
    }

    // Append any remaining items not in orderedSlugs (safety fallback)
    foreach ($items_by_slug as $item) {
      $reordered[] = $item;
    }

    $colors[$color_type] = $reordered;

    Global_Colors::update_options($colors);
    $colors = Global_Colors::get_options();
    $response = new \WP_REST_Response($colors, 200);
    return $response;
  }

  // ── Custom Palette Preset Handlers ─────────────────────────

  public function get_palettes() {
    $palettes = Global_Colors::get_custom_palettes();
    return new \WP_REST_Response($palettes, 200);
  }

  private static $REQUIRED_COLOR_KEYS = ['light', 'accentLight', 'accentPrimary', 'accentDark', 'dark'];

  /**
   * @return array|false Sanitized colors or false if invalid.
   */
  private static function sanitize_colors(array $colors) {
    $sanitized = [];
    foreach (self::$REQUIRED_COLOR_KEYS as $key) {
      if (! isset($colors[$key])) {
        return false;
      }
      $hex = sanitize_hex_color($colors[$key]);
      if (! $hex) {
        return false;
      }
      $sanitized[$key] = $hex;
    }
    return $sanitized;
  }

  public function add_palette(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();

    if (! isset($json_body['slug']) || ! isset($json_body['label']) || ! isset($json_body['colors'])) {
      return new \WP_REST_Response(['error' => 'Missing required fields'], 400);
    }

    $colors = self::sanitize_colors($json_body['colors']);
    if ($colors === false) {
      return new \WP_REST_Response(['error' => 'Invalid or incomplete colors'], 400);
    }

    $palette = [
      'slug' => sanitize_text_field($json_body['slug']),
      'label' => sanitize_text_field($json_body['label']),
      'colors' => $colors,
      'gradients' => $json_body['gradients'] ?? []
    ];

    $palettes = Global_Colors::get_custom_palettes();
    $palettes[] = $palette;

    Global_Colors::update_custom_palettes($palettes);
    return new \WP_REST_Response($palettes, 200);
  }

  public function update_palette_preset(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();

    if (! isset($json_body['slug'])) {
      return new \WP_REST_Response(['error' => 'Missing slug'], 400);
    }

    $slug = sanitize_text_field($json_body['slug']);
    $palettes = Global_Colors::get_custom_palettes();

    foreach ($palettes as &$palette) {
      if ($palette['slug'] === $slug) {
        if (isset($json_body['label'])) {
          $palette['label'] = sanitize_text_field($json_body['label']);
        }
        if (isset($json_body['colors'])) {
          $colors = self::sanitize_colors($json_body['colors']);
          if ($colors !== false) {
            $palette['colors'] = $colors;
          }
        }
        if (isset($json_body['gradients'])) {
          $palette['gradients'] = $json_body['gradients'];
        }
        break;
      }
    }

    Global_Colors::update_custom_palettes($palettes);
    return new \WP_REST_Response($palettes, 200);
  }

  public function delete_palette(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();

    if (! isset($json_body['slug'])) {
      return new \WP_REST_Response(['error' => 'Missing slug'], 400);
    }

    $slug = sanitize_text_field($json_body['slug']);
    $palettes = Global_Colors::get_custom_palettes();
    $palettes = array_values(array_filter($palettes, function($p) use ($slug) {
      return $p['slug'] !== $slug;
    }));

    Global_Colors::update_custom_palettes($palettes);
    return new \WP_REST_Response($palettes, 200);
  }

  public function reorder_palettes(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();

    if (! isset($json_body['orderedSlugs']) || ! is_array($json_body['orderedSlugs'])) {
      return new \WP_REST_Response(['error' => 'Missing orderedSlugs'], 400);
    }

    $palettes = Global_Colors::get_custom_palettes();
    $by_slug = [];
    foreach ($palettes as $p) {
      $by_slug[$p['slug']] = $p;
    }

    $reordered = [];
    foreach ($json_body['orderedSlugs'] as $slug) {
      if (isset($by_slug[$slug])) {
        $reordered[] = $by_slug[$slug];
        unset($by_slug[$slug]);
      }
    }
    foreach ($by_slug as $p) {
      $reordered[] = $p;
    }

    Global_Colors::update_custom_palettes($reordered);
    return new \WP_REST_Response($reordered, 200);
  }
}
