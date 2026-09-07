<?php

namespace Nectar\API;
use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Global_Colors, Global_Typography, Nectar_Plugin_Options};

/**
 * CSS API
 * @version 1.4.0
 * @since 0.0.2
 */
class CSS_API implements API_Route {
  const API_BASE = '/meta/css';

  /**
   * The template types /update/wp_template_part may resolve. Anything else is rejected
   * rather than handed to get_block_template().
   */
  const TEMPLATE_TYPES = [ 'wp_template', 'wp_template_part' ];

  public function build_routes() {
    Router::add_route($this::API_BASE . '/update', [
      'callback' => [$this, 'update'],
      'methods' => 'POST',
      'permission_callback' => function( \WP_REST_Request $request ) {
        return $this->update_permissions_check( $request );
      }
    ]);

    Router::add_route($this::API_BASE . '/update/wp_template_part', [
      'callback' => [$this, 'update_wp_template_part'],
      'methods' => 'POST',
      'permission_callback' => function( \WP_REST_Request $request ) {
        return $this->update_wp_template_part_permissions_check( $request );
      }
    ]);

    Router::add_route($this::API_BASE . '/update/pattern', [
      'callback' => [$this, 'update_pattern'],
      'methods' => 'POST',
      'permission_callback' => function( \WP_REST_Request $request ) {
        return $this->update_pattern_permissions_check( $request );
      }
    ]);

    Router::add_route($this::API_BASE . '/get_global_settings_css_rules', [
      'callback' => [$this, 'get_global_settings_css_rules'],
      'methods' => 'GET',
      // Read-only: returns the site's global color/typography rules, which are already
      // public on the front end. No object is addressed, so edit_posts is the right gate.
      'permission_callback' => function() {
        return Access_Utils::can_edit_posts();
      }
    ]);

  }

  /**
   * Denial with the standard REST authorization status (401 anonymous, 403 authenticated).
   * @return \WP_Error
   */
  private function forbidden( string $code, string $message ) {
    return new \WP_Error( $code, $message, [ 'status' => rest_authorization_required_code() ] );
  }

  /**
   * Rejection of a malformed/unsupported body.
   * @return \WP_Error
   */
  private function invalid_param( string $message ) {
    return new \WP_Error( 'rest_invalid_param', $message, [ 'status' => 400 ] );
  }

  /**
   * Normalizes a body-supplied post id to a positive int, or null when it is absent or is
   * not an id (arrays, floats, "12abc", 0, negatives). JSON bodies are attacker-shaped, so
   * the value is never passed through to a capability check or a meta write untyped.
   * @param mixed $value
   * @return int|null
   */
  private function resolve_post_id( $value ) {
    if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
      $post_id = (int) $value;
      return $post_id > 0 ? $post_id : null;
    }
    return null;
  }

  /**
   * Resolves the "theme//slug" template id used by /update/wp_template_part to the underlying
   * wp_template(_part) post id. Returns a WP_Error when the type is unsupported, the template
   * does not exist, or it is a theme-file template with no database post to attach meta to.
   * @param mixed $id
   * @param mixed $part_type
   * @return int|\WP_Error
   */
  private function resolve_template_post_id( $id, $part_type ) {
    if ( ! is_string( $id ) || '' === $id || ! in_array( $part_type, self::TEMPLATE_TYPES, true ) ) {
      return $this->invalid_param( __( 'A valid template id and part_type are required.', 'nectar-blocks' ) );
    }

    $block_template = get_block_template( $id, $part_type );
    if ( ! $block_template || empty( $block_template->wp_id ) ) {
      return $this->invalid_param( __( 'The requested template could not be found.', 'nectar-blocks' ) );
    }

    return (int) $block_template->wp_id;
  }

  /**
   * Authorizes /update. The body decides which object is written, so the capability has to be
   * resolved per object: post CSS against that post, widget CSS against the site-wide option.
   * @return true|\WP_Error
   */
  public function update_permissions_check( \WP_REST_Request $request ) {
    $json_body = $request->get_json_params();
    $type = isset($json_body['type']) ? $json_body['type'] : '';

    if ( 'widgets' === $type ) {
      // The widget CSS is a site-wide option; WP gates widget editing on edit_theme_options.
      if ( ! Access_Utils::can_edit_theme_options() ) {
        return $this->forbidden( 'rest_forbidden', __( 'Sorry, you are not allowed to edit widget styles.', 'nectar-blocks' ) );
      }
      return true;
    }

    if ( 'regular' === $type ) {
      $post_id = $this->resolve_post_id( isset($json_body['post_id']) ? $json_body['post_id'] : null );
      if ( null === $post_id ) {
        return $this->invalid_param( __( 'A valid post_id is required.', 'nectar-blocks' ) );
      }
      // map_meta_cap denies edit_post for a post that does not exist, so a bogus id fails here.
      if ( ! Access_Utils::can_edit_post( $post_id ) ) {
        return $this->forbidden( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit styles for this post.', 'nectar-blocks' ) );
      }
      return true;
    }

    return $this->invalid_param( __( 'A valid CSS update type is required.', 'nectar-blocks' ) );
  }

  /**
   * Authorizes /update/pattern against the pattern (wp_block) post being written.
   * @return true|\WP_Error
   */
  public function update_pattern_permissions_check( \WP_REST_Request $request ) {
    $json_body = $request->get_json_params();
    $post_id = $this->resolve_post_id( isset($json_body['post_id']) ? $json_body['post_id'] : null );

    if ( null === $post_id ) {
      return $this->invalid_param( __( 'A valid post_id is required.', 'nectar-blocks' ) );
    }
    if ( ! Access_Utils::can_edit_post( $post_id ) ) {
      return $this->forbidden( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit styles for this pattern.', 'nectar-blocks' ) );
    }

    return true;
  }

  /**
   * Authorizes /update/wp_template_part. Mirrors WP core's WP_REST_Templates_Controller, which
   * gates all template editing on edit_theme_options, then additionally checks edit_post against
   * the resolved template post.
   * @return true|\WP_Error
   */
  public function update_wp_template_part_permissions_check( \WP_REST_Request $request ) {
    if ( ! Access_Utils::can_edit_theme_options() ) {
      return $this->forbidden( 'rest_cannot_manage_templates', __( 'Sorry, you are not allowed to edit templates on this site.', 'nectar-blocks' ) );
    }

    $json_body = $request->get_json_params();
    $post_id = $this->resolve_template_post_id(
        isset($json_body['post_id']) ? $json_body['post_id'] : null,
        isset($json_body['part_type']) ? $json_body['part_type'] : ''
    );

    if ( is_wp_error( $post_id ) ) {
      return $post_id;
    }
    if ( ! Access_Utils::can_edit_post( $post_id ) ) {
      return $this->forbidden( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit styles for this template.', 'nectar-blocks' ) );
    }

    return true;
  }

  public function update_wp_template_part(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $css = isset($json_body['css']) ? $json_body['css'] : '';
    $id = isset($json_body['post_id']) ? $json_body['post_id'] : '';
    $autosave = isset($json_body['autosave']) ? $json_body['autosave'] : false;
    $part_type = isset($json_body['part_type']) ? $json_body['part_type'] : '';

    // Log::debug('CSS Update - WP Template Parts', [
    //   'css' => $css,
    //   'post_id' => $id,
    //   'autosave' => $autosave,
    //   'part_type' => $part_type
    // ]);

    // The id in this API is, for some godly reason, in the form of "twentytwentyfour//footer"
    $post_id = $this->resolve_template_post_id( $id, $part_type );
    if ( is_wp_error( $post_id ) ) {
      return $post_id;
    }

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
    $id = $this->resolve_post_id( isset($json_body['post_id']) ? $json_body['post_id'] : null );
    $autosave = isset($json_body['autosave']) ? $json_body['autosave'] : false;

    if ( null === $id ) {
      return $this->invalid_param( __( 'A valid post_id is required.', 'nectar-blocks' ) );
    }

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
    $id = $this->resolve_post_id( isset($json_body['post_id']) ? $json_body['post_id'] : null );
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
    } else {
      // Unknown type, or a 'regular' write with no usable post_id: report it instead of
      // answering "success" for a write that never happened.
      return $this->invalid_param( __( 'A valid CSS update type and post_id are required.', 'nectar-blocks' ) );
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
