<?php

namespace Nectar\API\Global_Settings;
use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Utilities\Log;
use Nectar\Global_Settings\Nectar_Custom_Fonts;

/**
 * Custom_Fonts_API
 * @version 1.1.0
 * @since 1.1.0
 */
class Custom_Fonts_API implements API_Route {
  const API_BASE = '/settings/custom_fonts';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_custom_fonts'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/create_font', [
      'callback' => [$this, 'create_font'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options() && Access_Utils::can_upload_files();
      }
    ]);

    Router::add_route($this::API_BASE . '/add', [
      'callback' => [$this, 'add_custom_font'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options() && Access_Utils::can_upload_files();
      }
    ]);

    Router::add_route($this::API_BASE . '/delete', [
      'callback' => [$this, 'delete_custom_font'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/update/variation', [
      'callback' => [$this, 'update_custom_fonts'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE . '/delete/variation', [
      'callback' => [$this, 'delete_font_variation'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);
  }

  public function get_custom_fonts() {
    $fonts = apply_filters('nectar_custom_font_list', Nectar_Custom_Fonts::get_options());
    $fonts = array_values($fonts);
    $response = new \WP_REST_Response($fonts, 200);
    return $response;
  }

  public function create_font(\WP_REST_Request $request) {
    $font_slug = $request->get_param( 'slug' );
    $font_name = $request->get_param( 'name' );
    $custom_fonts = Nectar_Custom_Fonts::get_options();

    // TODO: Make sure there isn't a font already named the new name?

    $new_font = [
      'name' => sanitize_text_field($font_name),
      'slug' => $font_slug,
      'variations' => []
    ];

    $custom_fonts[$font_slug] = $new_font;
    Nectar_Custom_Fonts::update_options($custom_fonts);

    $response = new \WP_REST_Response(['status' => 'success'], 200);
    return $response;
  }

  public function add_custom_font(\WP_REST_Request $request) {
    $files = $request->get_file_params();
    $font_slug = $request->get_param( 'slug' );

    if ( ! empty( $files ) && ! empty( $files['file'] ) ) {
      $file = $files['file'];
    }

    // Call our upload function
    $result = $this->upload_custom_font($file, $file['name'], $font_slug);

    if (is_wp_error($result)) {
      return new \WP_Error( 100, __( 'Font upload failure, please try again. Errors: ', 'nectar-blocks' ) . $result->get_error_message() );
    }

    return new \WP_REST_Response($result, 200);
  }

  function upload_custom_font($font_file, $original_filename, $font_slug) {

    // Check if the file is valid
    if (! isset($font_file['tmp_name']) || ! is_uploaded_file($font_file['tmp_name'])) {
        return new \WP_Error('invalid_file', 'Invalid font file.', $font_file);
    }

    // Get WordPress upload directory
    $upload_dir = wp_upload_dir();
    $font_dir = $upload_dir['basedir'] . '/nectar-blocks/custom-fonts';
    // Create the custom fonts directory if it doesn't exist
    if (! is_dir($font_dir)) {
      $created = wp_mkdir_p($font_dir);
      if (! $created) {
        return new \WP_Error('bad_permissions', 'Unable to create folder');
      }
    }  

    // Get file info
    $file_ext = pathinfo($original_filename, PATHINFO_EXTENSION);

    // Check if the file is a valid font type
    // $allowed_types = ['ttf', 'otf', 'woff', 'woff2'];
    $allowed_types = ['ttf', 'woff', 'woff2'];
    if (! in_array(strtolower($file_ext), $allowed_types)) {
      return new \WP_Error('invalid_type', 'Invalid font file type. Allowed types: ' . implode(', ', $allowed_types));
    }

    // Generate a unique filename
    $new_file_name = wp_unique_filename($font_dir, $original_filename);
    $new_file_path = $font_dir . '/' . $new_file_name;

    // Move the uploaded file to the custom fonts directory
    if (! move_uploaded_file($font_file['tmp_name'], $new_file_path)) {
        return new \WP_Error('move_failed', 'Failed to move the uploaded font file.', $new_file_path );
    }

    // Update custom fonts
    $custom_fonts = Nectar_Custom_Fonts::get_options();
    $variation = [
      'file_name' => $new_file_name,
      'url' => $upload_dir['baseurl'] . '/nectar-blocks/custom-fonts/' . $new_file_name,
      'fontData' => [
        'fontStyle' => 'normal',
        'weight' => '400'
      ]
      ];
    array_push($custom_fonts[$font_slug]['variations'], $variation);

    // $custom_fonts = array_values($custom_fonts);
    Nectar_Custom_Fonts::update_options($custom_fonts);

    return [
      'status' => 'success'
    ];
  }

  public function update_custom_fonts(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $slug = $json_body['slug'];
    $index = $json_body['index'];
    $font_weight = $json_body['fontWeight'];
    $font_style = $json_body['fontStyle'];

    $custom_fonts = Nectar_Custom_Fonts::get_options();

    $custom_fonts[$slug]['variations'][$index]['fontData'] = [
      'fontStyle' => $font_style,
      'weight' => $font_weight
    ];

    // $custom_fonts = array_values($custom_fonts);
    Nectar_Custom_Fonts::update_options($custom_fonts);

    $response = new \WP_REST_Response([
      'status' => 'success'
    ], 200);
    return $response;
  }

  public function delete_custom_font(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $slug = $json_body['slug'];

    $custom_fonts = Nectar_Custom_Fonts::get_options();
    $font = $custom_fonts[$slug];
    $variations = $font['variations'];

    log::debug('Deleting Font Vars', [
      'variations' => $variations,
      'slug' => $slug
    ]);

    foreach ($variations as $variation) {
      log::debug('Deleting variation', [
        'variation' => $variation,
      ]);
      $delete_results = $this->delete_font($variation);
    }

    unset($custom_fonts[$slug]);

    Nectar_Custom_Fonts::update_options($custom_fonts);

    return new \WP_REST_Response([
      'status' => 'success'
    ], 200);
  }

  public function delete_font_variation(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $slug = $json_body['slug'];
    $index = $json_body['index'];

    $custom_fonts = Nectar_Custom_Fonts::get_options();

    $font = $custom_fonts[$slug];
    $variations = $font['variations'];

    log::debug('Deleting Variation', [
      'font' => $font,
      'slug' => $slug,
      'variation' => $variations
    ]);
    $delete_results = $this->delete_font($variations[$index]);

    unset($font['variations'][$index]);
    $variations = array_values($font['variations']);
    $font['variations'] = $variations;
    $custom_fonts[$slug] = $font;

    Nectar_Custom_Fonts::update_options($custom_fonts);

    return new \WP_REST_Response([
      'status' => 'success'
    ], 200);
  }

  public function delete_font($font_to_delete) {
    $upload_dir = wp_upload_dir();
    $font_dir = $upload_dir['basedir'] . '/nectar-blocks/custom-fonts/';
    $file_path = $font_dir . $font_to_delete['file_name'];

    if (! $file_path || ! file_exists($file_path)) {
      Log::debug('Font file not found.');
      return true;
    }

    $delete_results = wp_delete_file_from_directory($file_path, $font_dir);
    Log::debug('$delete_results', [$delete_results]);

    return $delete_results;
  }
}
