<?php

namespace Nectar\API;

use Nectar\API\Global_Settings\{
  Color_API,
  Options_API,
  Typography_API,
  Admin_API,
  Custom_Fonts_API
};
use Nectar\API\Media\{Image_Upload_API};
use Nectar\API\{
  CSS_API,
  Assets_API,
  Post_Data_API,
  Dynamic_Block_API,
  Import_Export_API,
  Dynamic_Data_API
};

interface API_Route {
  public function build_routes();
}

/**
 * Router
 * @since 0.0.1
 * @version 2.0.0
 */
class Router {
  function __construct() {
    $this->initialize_hooks();
  }

  private function initialize_hooks() {
    add_action( 'rest_api_init', [$this, 'build_all_routes']);
  }

  public function build_all_routes() {
    $apis = [
      new Color_API(),
      new Typography_API(),
      new Options_API(),
      new Admin_API(),
      new Custom_Fonts_API(),

      new Image_Upload_API(),

      new CSS_API(),
      new Assets_API(),
      new Post_Data_API(),
      new Dynamic_Block_API(),
      new Dynamic_Data_API(),
      new Import_Export_API()
    ];

    if ( class_exists( 'Theme_Import_Export_API' ) ) {
      array_push($apis, new \Theme_Import_Export_API());
    }

    if ( class_exists( 'Nectar\API\Core_Import_Export_API' ) ) {
      array_push($apis, new \Nectar\API\Core_Import_Export_API());
    }

    foreach ($apis as &$route_api) {
      $route_api->build_routes();
    }
  }

  /**
   * add_route
   * Registers a rest route in the nectar/v1 namespace.
   * @since 0.0.2
   */
  public static function add_route($route, $args) {
    register_rest_route( 'nectar/v1', $route, $args);
  }
}
