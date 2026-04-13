<?php

namespace Nectar;

/**
 * Welcome page when activating the plugin
 * @version 0.0.1
 * @since 0.1.1
 */
class Welcome {
  function __construct() {
    $this->initialize_hooks();
  }

  private function initialize_hooks() {

    add_action('admin_init', [$this, 'redirect_to_options_page']);
    // redirect to options page after activation
    register_activation_hook( NECTAR_BLOCKS_FILE, [$this, 'nectar_blocks_activate']);

  }

  function nectar_blocks_activate() {
    set_transient('nectar_blocks_do_activation_redirect', true, 60); // 60 seconds expiration time
  }

  function redirect_to_options_page() {
    if ( ! get_transient( 'nectar_blocks_do_activation_redirect' ) ) {
      return;
    }

    delete_transient( 'nectar_blocks_do_activation_redirect' );

    if ( ! isset($_GET['activate-multi']) ) {
      wp_safe_redirect(admin_url('admin.php?page=nectar-blocks'));
      exit;
    }

  }
}