<?php

namespace Nectar\Menu_Options;

use Nectar\Menu_Options\Modal;
use Nectar\Menu_Options\Style_Manager;

class Menu_Options_Register {
  private static $instance;

  function __construct() {

    // Frontend.
    add_action( 'init', [$this, 'init'] );

  }

  public function init() {

    // Skip if Salient Core is active.
    if( defined('SALIENT_CORE_VERSION') ) {
      return;
    }
    // Skip if not using Nectarblocks theme.
    if ( ! defined('NB_THEME_VERSION') ) {
      return;
    }

    // Init classes.
    Modal::get_instance();
    Style_Manager::get_instance();
  }

  /**
  * Initiator.
  */
  public static function get_instance() {
    if ( ! self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }
}

// Init class.
Menu_Options_Register::get_instance();

