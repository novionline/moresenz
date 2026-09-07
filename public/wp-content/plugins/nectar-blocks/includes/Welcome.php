<?php

namespace Nectar;

use Nectar\Global_Settings\Nectar_Blocks_Options;
use Nectar\Licensing\Token_Service;

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
    add_action('admin_init', [$this, 'redirect_to_whats_new_page']);
    // redirect to options page after activation
    register_activation_hook( NECTAR_BLOCKS_FILE, [$this, 'nectar_blocks_activate']);

  }

  function nectar_blocks_activate() {
    set_transient('nectar_blocks_do_activation_redirect', true, 60); // 60 seconds expiration time

    // Re-arm the token-refresh cron when reactivating an already-licensed V2 site
    // (deactivation cleared it). A fresh install has no token yet — the register
    // route arms the cron when the first V2 pair lands.
    $opts = Nectar_Blocks_Options::get_options();
    if (
      is_array($opts)
      && (int) ( $opts['tokenVersion'] ?? 1 ) === 2
      && ( $opts['refreshToken'] ?? '' ) !== ''
    ) {
      Token_Service::schedule_cron();
    }
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

  function redirect_to_whats_new_page() {
    if ( ! get_option( 'nectar_blocks_show_whats_new' ) ) {
      return;
    }

    // Don't intercept background admin requests.
    if ( wp_doing_ajax() || ( defined('DOING_CRON') && DOING_CRON ) ) {
      return;
    }

    // Skip on demo sites — DEMO_ROOT_DIR_PATH is defined by the demo
    // theme's functions.php. The flag is consumed so it doesn't linger.
    if ( defined('DEMO_ROOT_DIR_PATH') ) {
      delete_option('nectar_blocks_show_whats_new');
      return;
    }

    // Only admins can view the panel — leave the flag in place for them.
    if ( ! current_user_can('manage_options') ) {
      return;
    }

    if ( isset($_GET['activate-multi']) ) {
      return;
    }

    // Already on the What's New tab — consume the flag without redirecting.
    $on_panel = isset($_GET['page']) && $_GET['page'] === 'nectar-blocks';
    $on_tab = isset($_GET['tab']) && $_GET['tab'] === 'whats-new';
    if ( $on_panel && $on_tab ) {
      delete_option('nectar_blocks_show_whats_new');
      return;
    }

    delete_option('nectar_blocks_show_whats_new');
    wp_safe_redirect(admin_url('admin.php?page=nectar-blocks&tab=whats-new'));
    exit;
  }
}