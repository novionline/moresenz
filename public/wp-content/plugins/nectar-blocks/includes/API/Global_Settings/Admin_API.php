<?php

namespace Nectar\API\Global_Settings;

use Nectar\API\{Router, API_Route, Access_Utils};
use Nectar\Global_Settings\{Code_Options, Nectar_Blocks_Options, Nectar_Plugin_Options, Nectar_Modules};
use Nectar\Licensing\{Expired_Updates_Service, Token_Service};
use Nectar\Utilities\Log;
use Nectar\Update\{Updaters};
use Nectar\Render\Local_Google_Fonts;

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
    $nectar_plugin_options = Nectar_Plugin_Options::get_options();
    $modules = Nectar_Modules::get_options();
    $options = [
      'code' => $code,
      // public_auth() strips the opaque refreshToken — it must never reach the browser.
      'auth' => Token_Service::public_auth(),
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
    if ( ! is_array($data) ) {
      return;
    }

    // Only these keys are browser-writable. The license/token identity fields
    // (token, refreshToken, tokenExpiresAt, tokenVersion, registeredHostname,
    // licenseKey, isLicenseActive) are owned by the register/deregister routes and
    // the server-side refresh lifecycle. Because update_options() is a full-array
    // replace, a settings save that carried those fields would wipe the
    // server-owned token — so whitelist-merge onto the current options instead.
    $writable = [ 'autoUpdate', 'analytics', 'bugReports' ];

    $opts = Nectar_Blocks_Options::get_options();
    if ( ! is_array($opts) ) {
      $opts = [];
    }
    foreach ( $writable as $key ) {
      if ( array_key_exists($key, $data) ) {
        $opts[$key] = $data[$key];
      }
    }
    Nectar_Blocks_Options::update_options($opts);

    // Settings changed — drop the cached expired-updates payload so the
    // "resubscribe" notice/banner reflects the change immediately instead of
    // lingering for the full 24h cache.
    Expired_Updates_Service::purge();
  }

  private function set_plugin_options($data) {
    Nectar_Plugin_Options::update_options($data);

    // Clear local Google fonts cache when plugin options change
    // This ensures fonts regenerate if the option is toggled
    if ( class_exists( Local_Google_Fonts::class ) ) {
      Local_Google_Fonts::clear_cache();
    }
  }

  private function set_modules($data) {
    Nectar_Modules::update_options($data);
  }

  /**
   * Reset Updater Transients
   *
   * Clears the cached version check for every updater on this install plus the
   * expired-updates payload, and — unlike the plain delete_transient() this used to
   * do — the per-key throttle reservation, which would otherwise suppress the very
   * re-check the admin asked for for up to 15 minutes.
   */
  public function reset_updater_transients() {
    Updaters::purge_all();
    Log::info('Updater caches purged: ' . implode(', ', Updaters::update_keys()));

    $status = [ 'status' => 'success' ];
    $response = new \WP_REST_Response($status, 200);
    return $response;
  }
}
