<?php

namespace Nectar\Render;

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
    'text',
    'tabs',
    'accordion',
    'carousel',
    'post-grid',
    'image-grid',
    'taxonomy-grid'
  ];

  function __construct() {
    $this->initialize_hooks();
  }

  public function initialize_hooks() {
    if ( ! is_admin() ) {
        add_action('wp_enqueue_scripts', [$this, 'register_block_scripts']);
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

        // The handles for each block script will be enqueued via the block.json viewScript.
        wp_register_script( 'nectar-blocks-' . $block_name, $frontend_JS_script_path, [
            ...$deps
        ], time(), true );
    }

  }
}
