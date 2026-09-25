<?php

namespace Nectar\Update;

use Nectar\Global_Settings\Nectar_Blocks_Options;
use Nectar\Licensing\Token_Service;
use Nectar\Shared\RemoteVersionCheck;

/**
 * Nectar Blocks Updater.
 *
 * @since 0.1.1
 * @version 1.2.3
 *
 * Sources:
 * https://rudrastyh.com/wordpress/self-hosted-plugin-update.html
 * https://github.com/rudrastyh/misha-update-checker/tree/main
 * https://rudrastyh.com/wp-content/uploads/updater/info.json
 *
 */
class NectarBlocksUpdater {
  private $cache_allowed;

  public $plugin_slug;

  public $version;

  const UPDATE_KEY = 'nectar_plugin_update';

  const UPDATE_URL = 'https://api.' . NECTAR_HOST_URL . '/v1/upgrade/plugin';

  public function __construct() {
    $this->cache_allowed = true;
    $this->plugin_slug = 'nectar-blocks';
    $this->version = NECTAR_BLOCKS_VERSION;

    $nb_options = Nectar_Blocks_Options::get_options();
    $token = $nb_options['token'] ?? '';
    $auto_update = $nb_options['autoUpdate'] ?? false;

    // TODO: Get version value from json or something
    // Log::debug(NECTAR_BLOCKS_ROOT_DIR_PATH . 'nectar-blocks.php');
    // $temp = get_plugin_data(NECTAR_BLOCKS_ROOT_DIR_PATH . 'nectar-blocks.php');

    // if( ! function_exists('get_plugin_data') ){
    //   require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    // }
    // $this->version = get_plugin_data(NECTAR_BLOCKS_ROOT_DIR_PATH . 'nectar-blocks.php')['Version'];

    if ( $token !== '' && $auto_update ) {
      $this->add_filters();
    }
  }

  private function add_filters() {
    add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
    add_filter( 'site_transient_update_plugins', [ $this, 'update' ] );
    add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
  }

  /**
   * get_current_versions
   * Retrieves the current versions from endpoint.
   * @since 0.1.1
   */
  public function get_current_version() {
    $nb_options = Nectar_Blocks_Options::get_options();
    $token = $nb_options['token'] ?? '';
    return RemoteVersionCheck::fetch(
        self::UPDATE_URL,
        $token,
        self::UPDATE_KEY,
        $this->cache_allowed,
        // JWT V2: on a 401 the shared helper refreshes the token and retries once.
        [ Token_Service::class, 'reauthorize' ]
    );
  }

  /**
   * info - This generate the data shown in the popover when you click the 'View version 0.1.1 details', or some
   * variant of that, on the plugins page.
   * @since 0.1.1
   */
  public function info( $res, $action, $args ) {

    // do nothing if you're not getting plugin information right now
    if( 'plugin_information' !== $action ) {
      return $res;
    }

    // do nothing if it is not our plugin
    if( $this->plugin_slug !== $args->slug ) {
      return $res;
    }

    // get updates
    $remote = $this->get_current_version();

    // Log::debug(json_encode($remote));

    if( ! $remote ) {
      return $res;
    }

    $res = new \stdClass();

    $res->name = $remote->name;
    $res->slug = $remote->slug;
    $res->version = $remote->version;
    $res->tested = $remote->tested;
    $res->requires = $remote->requires;
    $res->author = $remote->author;
    $res->author_profile = $remote->author_profile;
    $res->download_link = $remote->download_url;
    $res->trunk = $remote->download_url;
    $res->requires_php = $remote->requires_php;
    $res->last_updated = $remote->last_updated;

    $res->sections = [
      'description' => $remote->sections->description,
      'installation' => $remote->sections->installation,
      'changelog' => $remote->sections->changelog
    ];

    if( ! empty( $remote->banners ) ) {
      $res->banners = [
        'low' => $remote->banners->low,
        'high' => $remote->banners->high
      ];
    }

    if( ! empty( $remote->icons ) ) {
      $res->icons = [
        '1x' => $remote->icons->{"1x"},
        '2x' => $remote->icons->{"2x"},
        'svg' => $remote->icons->{"svg"},
      ];
    }

    return $res;

  }

  public function update( $transient ) {
    if ( empty($transient->checked ) ) {
      return $transient;
    }

    $remote = $this->get_current_version();

    // Log::debug('in update: ' . json_encode($remote));

    if(
      $remote
      && version_compare( $this->version, $remote->version, '<' )
      && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' )
      && version_compare( $remote->requires_php, PHP_VERSION, '<' )
    ) {
      $res = new \stdClass();
      $res->slug = $this->plugin_slug;
      $res->plugin = NECTAR_BLOCKS_FOLDER_NAME . '/nectar-blocks.php';
      $res->new_version = $remote->version;
      $res->tested = $remote->tested;
      $res->package = $remote->download_url;
      $res->requires_php = $remote->requires_php;
      $res->icons = [
        '1x' => $remote->icons->{"1x"},
        '2x' => $remote->icons->{"2x"},
        'svg' => $remote->icons->{"svg"},
      ];

      $transient->response[$res->plugin] = $res;
      // Log::debug(json_encode($transient));
    }

    return $transient;
  }

  public function purge( $upgrader, $options ) {
    if (
      $this->cache_allowed
      && isset( $options['action'] ) && 'update' === $options['action']
      && isset( $options['type'] ) && 'plugin' === $options['type']
    ) {
      RemoteVersionCheck::purge( self::UPDATE_KEY );
    }
  }
}
