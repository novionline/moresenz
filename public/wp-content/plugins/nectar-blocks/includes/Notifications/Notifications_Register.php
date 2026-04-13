<?php

namespace Nectar\Notifications;

use Nectar\Notifications\NotificationManager;

class Notifications_Register {
  private static $instance;

  function __construct() {
    add_action( 'init', [ $this, 'register_notifications' ] );
  }

  public function register_notifications() {

    // Gutenberg plugin
    if (defined('GUTENBERG_VERSION')) {
      NotificationManager::create_notification(
          'gutenberg_plugin',
          sprintf(
              '<strong>%1$s</strong> %2$s',
              esc_html__('Nectarblocks Alert: We detected that the Gutenberg plugin is currently active.', 'nectar-blocks'),
              esc_html__('Please note that the Gutenberg plugin is intended for testing new Block Editor features, and Nectarblocks may not be fully compatible with it.', 'nectar-blocks')
          )
      );
    }

    // CoBlocks plugin
    if (defined('COBLOCKS_VERSION')) {
      NotificationManager::create_notification(
          'coblocks',
          sprintf(
              '<strong>%1$s</strong> %2$s',
              esc_html__('Nectarblocks Alert: We detected that the CoBlocks plugin is currently active.', 'nectar-blocks'),
              esc_html__('This may cause conflicts when used alongside other block plugins, including Nectarblocks. To ensure the best performance and compatibility, we recommend deactivating the CoBlocks plugin while using Nectarblocks.', 'nectar-blocks')
          )
      );
    }

  }

  public static function get_instance() {
    if( is_null( self::$instance ) ) {
      self::$instance = new self();
    }

    return self::$instance;
  }
}