<?php

namespace Nectar\API\Global_Settings;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Code_Options, Nectar_Blocks_Options, Nectar_Plugin_Options, Nectar_Modules};
use Nectar\Utilities\Log;
use Nectar\Update\{NectarBlocksUpdater};

/**
 * Admin_API
 * @version 1.3.0
 * @since 0.0.9
 */
class Admin_API implements API_Route {
  const API_BASE = '/settings/admin-panel';

  public function build_routes() {
    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'get_options'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);

    Router::add_route($this::API_BASE, [
      'callback' => [$this, 'set_options'],
      'methods' => 'POST',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      },
      'args' => [
        'panel' => [
          'type' => 'string',
          'required' => true,
          'description' => 'Panel we are operating on.'
        ],
        'data' => [
          'type' => 'object',
          'required' => true,
          'description' => 'Update data.'
        ]
      ]
    ]);

    Router::add_route($this::API_BASE . '/reset-updater-transients', [
      'callback' => [$this, 'reset_updater_transients'],
      'methods' => 'GET',
      'permission_callback' => function() {
        return Access_Utils::can_manage_options();
      }
    ]);
  }

  /**
   * Returns the Nectar Code dict.
   */
  public function get_options() {
    $code = Code_Options::get_options();
    $nectar_options = Nectar_Blocks_Options::get_options();
    $nectar_plugin_options = Nectar_Plugin_Options::get_options();
    $modules = Nectar_Modules::get_options();
    $options = [
      'code' => $code,
      'auth' => $nectar_options,
      'pluginOptions' => $nectar_plugin_options,
      'modules' => $modules
    ];

    $response = new \WP_REST_Response($options, 200);
    return $response;
  }

  /**
   * Sets the Nectar Code dict.
   */
  public function set_options(\WP_REST_Request $request) {
    $json_body = $request->get_json_params();
    $panel = $json_body['panel'];
    $data = $json_body['data'];

    if ($panel === 'auth') {
      $this->set_auth($data);
    } else if ($panel === 'code') {
      $this->set_code($data);
    }  else if ($panel === 'pluginOptions') {
      $this->set_plugin_options($data);
    } else if ($panel === 'modules') {
      $this->set_modules($data);
    } else {
      error_log('Unable to get correct admin_panel tab name.');
      return new \WP_REST_Response([
        'status' => 'failure'
      ], 400);
    }

    $response = new \WP_REST_Response([
      'status' => 'success'
    ], 200);
    return $response;
  }

  private function set_code($data) {
    Code_Options::update_options($data);
  }

  private function set_auth($data) {
    Nectar_Blocks_Options::update_options($data);
  }

  private function set_plugin_options($data) {
    Nectar_Plugin_Options::update_options($data);
  }

  private function set_modules($data) {
    Nectar_Modules::update_options($data);
  }

  /**
   * Reset Updater Transients
   */
  public function reset_updater_transients() {
    if ( class_exists('NectarThemeUpdater') ) {
      delete_transient( \NectarThemeUpdater::UPDATE_KEY );
      Log::info('NB_Theme transient purged.');
    }

    if ( class_exists('Nectar\Update\NectarBlocksIEUpdater') ) {
      delete_transient( \Nectar\Update\NectarBlocksIEUpdater::UPDATE_KEY );
      Log::info('NB_IE transient purged.');
    }

    delete_transient( NectarBlocksUpdater::UPDATE_KEY );
    Log::info('NB_Plugin transient purged.');

    $status = [ 'status' => 'success' ];
    $response = new \WP_REST_Response($status, 200);
    return $response;
  }
}
