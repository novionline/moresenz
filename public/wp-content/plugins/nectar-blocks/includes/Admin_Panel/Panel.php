<?php

namespace Nectar\Admin_Panel;
use Nectar\Global_Settings\{Global_Typography};

/**
 * Admin Panel creation
 * @version 0.0.3
 * @since 0.0.3
 */
class Panel {
  function __construct() {
    add_action( 'admin_menu', [$this, 'register_admin_panel'] );
    add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts' ] );
  }

  public function register_admin_panel() {
    add_menu_page(
        __('Nectarblocks Options', 'nectar-blocks'),
        'Nectarblocks',
        'manage_options',
        'nectar-blocks',
        [$this, 'theme_panel_output'],
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 109.42 101.08">
        <path fill="#ffffff" d="M41.81,101.08c-10.68,0-21.35-4.12-29.57-12.34C4.35,80.84,0,70.34,0,59.17s4.35-21.67,12.25-29.57L41.81,0l28.87,28.9c3.1,3.11,3.1,8.14,0,11.25-3.11,3.11-8.14,3.1-11.25,0l-17.62-17.64-18.32,18.34c-4.9,4.9-7.59,11.4-7.59,18.33s2.7,13.43,7.59,18.32c9.99,9.99,25.85,10.21,36.1.53,3.19-3.01,8.22-2.87,11.24.32,3.02,3.19,2.87,8.22-.32,11.24-8.12,7.67-18.42,11.5-28.7,11.5Z" stroke-width="0"/>
        <path fill="#ffffff" d="M108.55,60.66c-8.74,4.01-12.67,7.94-16.69,16.69-.53,1.16-2.19,1.16-2.72,0-4.01-8.74-7.94-12.67-16.69-16.69-1.16-.53-1.16-2.19,0-2.72,8.74-4.01,12.67-7.94,16.69-16.69.53-1.16,2.19-1.16,2.72,0,4.01,8.74,7.94,12.67,16.69,16.69,1.16.53,1.16,2.19,0,2.72Z" stroke-width="0"/>
      </svg>'),
        2
    );
  }

  public function theme_panel_output() {
    ?>
    <div id="nectar-admin-panel"></div>
    <?php
  }

  public function enqueue_scripts($screen) {

    if ( 'toplevel_page_nectar-blocks' !== $screen || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $asset_file = include NECTAR_BLOCKS_ROOT_DIR_PATH . 'build/adminPanel.asset.php';

    wp_enqueue_script(
        'nectar-admin-panel',
        NECTAR_BLOCKS_BUILD_PATH . '/adminPanel.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );
    wp_set_script_translations( 'nectar-admin-panel', 'nectar-blocks', NECTAR_BLOCKS_ROOT_DIR_PATH . '/languages'  );
    wp_enqueue_style( 'nectar-admin-panel', NECTAR_BLOCKS_BUILD_PATH . '/adminPanel.css', [], '1.0');

    $uploaded_fonts = Global_Typography::create_uploaded_fonts_style('editor');
    if ( $uploaded_fonts ) {
      wp_add_inline_style( 'nectar-admin-panel', $uploaded_fonts);
    }
  }
}

