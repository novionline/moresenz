<?php

namespace Nectar\Dynamic_Data\Sources;

/**
 * Current Post
 * @since 2.0.0
 * @version 2.0.0
 */
class Current_Post {
  private static $instance;

  public static function get_instance() {
    if (! self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  function __construct() {
    add_filter( 'nectar_blocks_dynamic_data/currentPost/content', [  new Other_Posts(), 'get_content' ], 10, 3 );
  }
}

