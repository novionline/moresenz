<?php

namespace Nectar\API\Media;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Utilities\Log;

// Required for wp_update_attachment_metadata
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Image_Upload_API
 * @version 0.0.4
 * @since 0.0.4
 */
class Image_Upload_API implements API_Route {
  const API_BASE = '/media/image';

  public function build_routes() {
    Router::add_route($this::API_BASE . '/upload_image_from_url', [
      'callback' => [$this, 'nectar_media_library_upload'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_upload_files();
      }
    ]);
  }

  /**
   * Checks if a remote file exists via head request.
   * https://kellenmace.com/check-if-a-remote-image-file-exists-in-wordpress/
   *
   * @since 0.0.4
   * @return {Boolean}
   */
  public static function remote_file_exists( $url ) {
    $response = wp_remote_head( $url );
    return 200 === wp_remote_retrieve_response_code( $response );
  }

  /**
   * Downloads an image from a remote url and adds it to the media library.
   *
   * @since 0.0.4
   * @return
   */
  public function nectar_media_library_upload( \WP_REST_Request $request ) {
    // Check JSON
    $data = json_decode( $request->get_body(), true );
    if ( ! $data ) {
      $response = [
        'success' => false,
        'msg' => __( 'There was an error with image data.'),
      ];

      wp_send_json( $response );
      return;
    }

    $id = sanitize_text_field( $data['id'] );
    $image_url = sanitize_text_field( $data['image_url'] );
    $filename = sanitize_text_field( $data['filename'] );
    $alt = sanitize_text_field( $data['alt'] );
    $caption = sanitize_text_field( $data['caption'] );
    $post_id = sanitize_text_field( $data['post_id'] );

    // Check if remote file exists.
    if ( ! $this->remote_file_exists( $image_url ) ) {
      $response = [
        'success' => false,
        'msg' => __( 'Error accessing unsplash image', 'nectar-blocks' ),
        'id' => $id,
        'data' => [
          'image_url' => $image_url
        ]
      ];
      wp_send_json( $response );
      return;
    }

    // Download image data
    $response = wp_remote_get( $image_url );
    if ( is_wp_error( $response ) ) {
      return new \WP_Error( 100, __( 'Image upload failure, please try again. Errors: ', 'nectar-blocks' ) . $response->get_error_message() );
    }
    // Get Headers.
    $type = wp_remote_retrieve_header( $response, 'content-type' );
    if ( ! $type ) {
      return new \WP_Error( 100, __( 'Image type could not be determined', 'nectar-blocks' ) );
    }

    // Upload remote file.
    $upload_resp = wp_upload_bits( $filename, null, wp_remote_retrieve_body( $response ) );

    // Build Attachment Data Array.
    $attachment = [
      'post_excerpt' => $caption,
      'post_content' => '',
      'post_status' => 'inherit',
      'post_mime_type' => $type,
    ];
    // Create attachment and add metadata
    $image_id = wp_insert_attachment( $attachment, $upload_resp['file'], $post_id );
    if ( is_wp_error( $image_id ) ) {
      Log::error( 'Unable to insert attachment in unsplash media upload.' );
      return new \WP_Error( 100, __( 'Unable to insert attachment.', 'nectar-blocks' ) );
    }
    update_post_meta( $image_id, '_wp_attachment_image_alt', $alt );
    $attach_data = wp_generate_attachment_metadata( $image_id, $upload_resp['file'] );
    wp_update_attachment_metadata( $image_id, $attach_data );

    // Success.
    $response = [
      'success' => true,
      'msg' => __( 'Image uploaded successfully. ', 'nectar-blocks' ),
      'id' => $id,
      'imageId' => $image_id
    ];

    wp_send_json( $response );
  }
}
