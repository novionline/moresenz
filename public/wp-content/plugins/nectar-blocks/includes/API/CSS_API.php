<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Global_Colors, Global_Typography, Nectar_Plugin_Options};

/**
 * CSS API
 * @version 1.3.0
 * @since 0.0.2
 */
class CSS_API implements API_Route {
  const API_BASE = '/meta/css';

  public function build_routes() {
    Router::add_route($this::API_BASE . '/update', [
      'callback' => [$this, 'update'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/update/wp_template_part', [
      'callback' => [$this, 'update_wp_template_part'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/update/pattern', [
      'callback' => [$this, 'update_pattern'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

    Router::add_route($this::API_BASE . '/get_global_settings_css_rules', [
      'callback' => [$this, 'get_global_settings_css_rules'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

  }

  public function update_wp_template_part(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $css = isset($json_body['css']) ? $json_body['css'] : '';
    $id = isset($json_body['post_id']) ? $json_body['post_id'] : '';
    $autosave = isset($json_body['autosave']) ? $json_body['autosave'] : false;
    // TODO: $part_type should only be 'wp_template' or 'wp_template_part'.
    $part_type = isset($json_body['part_type']) ? $json_body['part_type'] : '';

    // Log::debug('CSS Update - WP Template Parts', [
    //   'css' => $css,
    //   'post_id' => $id,
    //   'autosave' => $autosave,
    //   'part_type' => $part_type
    // ]);

    // The id in this API is, for some godly reason, in the form of "twentytwentyfour//footer"
    $block_template = get_block_template($id, $part_type);
    $post_id = $block_template->wp_id;

    if ($autosave) {
      update_post_meta($post_id, '_nectar_blocks_css_preview', $css );
    } else {
      update_post_meta($post_id, '_nectar_blocks_css', $css );
    }

    $response_data = [ 'status' => 'success' ];
    $response = new \WP_REST_Response($response_data, 200);
    return $response;
  }

  public function update_pattern(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $css = isset($json_body['css']) ? $json_body['css'] : '';
    $id = isset($json_body['post_id']) ? $json_body['post_id'] : '';
    $autosave = isset($json_body['autosave']) ? $json_body['autosave'] : false;

    if ($autosave) {
      update_post_meta($id, '_nectar_blocks_css_preview', $css );
    } else {
      update_post_meta($id, '_nectar_blocks_css', $css );
    }

    $response_data = [ 'status' => 'success' ];
    $response = new \WP_REST_Response($response_data, 200);
    return $response;
  }

  /**
   * Update Post Meta CSS
   */
  public function update(\WP_REST_Request $request) {

    $json_body = $request->get_json_params();
    $type = isset($json_body['type']) ? $json_body['type'] : '';
    $css = isset($json_body['css']) ? $json_body['css'] : '';
    $id = isset($json_body['post_id']) ? $json_body['post_id'] : '';
    $autosave = isset($json_body['autosave']) ? $json_body['autosave'] : false;
    // post_type is only used by fse === $type
    // $post_type = isset($json_body['post_type']) ? $json_body['post_type'] : '';

    // Log::debug('CSS Update', [
    //   'type' => $type,
    //   'css' => $css,
    //   'post_id' => $id,
    //   'post_type' => $post_type
    // ]);

    // Save the "post" data to a meta field
    if ( $type === 'regular' && $id ) {
      if ($autosave) {
        update_post_meta($id, '_nectar_blocks_css_preview', $css );
      } else {
        update_post_meta($id, '_nectar_blocks_css', $css );
      }
    } else if ( $type === 'widgets' ) {
      update_option('nectar_blocks_widgets_css', $css);
    }

    $response_data = [ 'status' => 'success' ];
    $response = new \WP_REST_Response($response_data, 200);
    return $response;
  }

  /**
   * Gets color and typography CSS rules from the global settings.
   * Utilized in the editor to render the CSS rules in the editor.
   */
  public function get_global_settings_css_rules() {
    // NB Plugin Options
    $nb_plugin_options = Nectar_Plugin_Options::get_options();

    $css = '';
    $css = Global_Typography::css_output('editor', $nb_plugin_options['shouldDisableNectarGlobalTypography']);
    $css .= Global_Colors::css_output();

    $response = new \WP_REST_Response($css, 200);
    return $response;
  }
}