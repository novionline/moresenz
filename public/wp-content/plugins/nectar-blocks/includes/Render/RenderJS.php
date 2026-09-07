<?php

namespace Nectar\Render;

use Nectar\Ajax\HeaderSearch;

/**
 * Controls the JS for the block output.
 * @version 0.0.4
 * @since 0.0.2
 */
class RenderJS {
  const FRONTEND_BLOCKS = [
    'image-gallery',
    'video-player',
    'video-lightbox',
    'milestone',
    'button',
    'search',
    'tabs',
    'accordion',
    'carousel',
    'post-grid',
    'image-grid',
    'taxonomy-grid',
    'megamenu',
    'ticker',
    'world-time',
    'table-of-contents'
  ];

  function __construct() {
    $this->initialize_hooks();
  }

  public function initialize_hooks() {
    // AJAX handlers run on all requests (admin-ajax.php).
    new HeaderSearch();

    if ( ! is_admin() ) {
        add_action( 'wp_enqueue_scripts', [ $this, 'register_block_scripts' ] );
    }
  }

  public function register_block_scripts() {

    foreach( self::FRONTEND_BLOCKS as $block_name ) {
        $frontend_JS_script_path = NECTAR_BLOCKS_BUILD_PATH . '/blocks/' . $block_name . '/frontend-script.js';
        $frontend_JS_asset_path = NECTAR_BLOCKS_ROOT_DIR_PATH . '/build/blocks/' . $block_name . '/frontend-script.asset.php';
        $args_array = file_exists($frontend_JS_asset_path) ? include($frontend_JS_asset_path) : [];

        $deps = isset($args_array['dependencies']) ? $args_array['dependencies'] : [];

        // Ensure that the block scripts are loaded after the nectar-blocks-frontend script.
        $deps = array_merge($deps, ['nectar-blocks-frontend']);

        $handle = 'nectar-blocks-' . $block_name;

        // The handles for each block script will be enqueued via the block.json viewScript.
        wp_register_script( $handle, $frontend_JS_script_path, [
            ...$deps
        ], time(), true );

        // Localize translatable strings for video player custom controls.
        if ( $block_name === 'video-player' ) {
            wp_localize_script( $handle, 'nectarVideoPlayer', [
                'i18n' => [
                    'play' => __( 'Play', 'nectar-blocks' ),
                    'pause' => __( 'Pause', 'nectar-blocks' ),
                    'mute' => __( 'Mute', 'nectar-blocks' ),
                    'unmute' => __( 'Unmute', 'nectar-blocks' ),
                    'fullscreen' => __( 'Fullscreen', 'nectar-blocks' ),
                    'exitFullscreen' => __( 'Exit fullscreen', 'nectar-blocks' ),
                    'seekOf' => __( '%current% of %duration%', 'nectar-blocks' ),
                ],
            ] );
        }

        // Localize AJAX data and translatable strings for search block.
        if ( $block_name === 'search' ) {
            wp_localize_script( $handle, 'nectarHeaderSearch', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'nectar_header_search' ),
                'i18n' => [
                    'noResults' => __( 'No results found', 'nectar-blocks' ),
                    'searchError' => __( 'Search unavailable. Please try again.', 'nectar-blocks' ),
                ],
            ] );
        }
    }

  }
}
