<?php

/**
 * Nectarblocks Theme Updater.
 *
 * @since 0.1.6
 * @version 1.2.3
 *
 * https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/
 * https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
 * https://rudrastyh.com/wordpress/theme-updates-from-custom-server.html
 * https://github.com/afragen/git-updater
 *
 */
class NectarThemeUpdater {
  public static $instance;

  private $cache_allowed;

  public $theme_slug;

  public $version;

  const UPDATE_KEY = 'nectar_theme_update';

  const UPDATE_URL = 'https://api.' . NB_THEME_HOST_URL . '/v1/upgrade/theme';

  public function __construct() {
    self::$instance = $this;
    $this->cache_allowed = true;
    // For local copy will be theme folder
    $this->theme_slug = 'nectar-blocks-theme';
    $this->version = nectar_get_theme_version();

    if ( ! class_exists('Nectar\Global_Settings\Nectar_Blocks_Options') ) {
      // error_log('Nectar Theme Updater:  Unable to find NB Options in constructor');
      return;
    }

    $nb_options = Nectar\Global_Settings\Nectar_Blocks_Options::get_options();
    $token = $nb_options['token'] ?? '';
    $auto_update = $nb_options['autoUpdate'] ?? false;

    if ( $token !== '' && $auto_update ) {
      $this->add_filters();
    }
  }

  private function add_filters() {
    // https://developer.wordpress.org/reference/hooks/site_transient_transient/
    add_filter( 'site_transient_update_themes', [ $this, 'update' ] );
    add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
  }

  /**
   * get_current_versions
   * Retrieves the current versions from endpoint.
   * @since 0.1.6
   */
  public function get_current_version() {
    if ( ! class_exists('Nectar\Global_Settings\Nectar_Blocks_Options') ) {
      error_log('Nectar Theme Updater: Unable to find Nectar_Blocks_Options');
      return false;
    }

    $remote = get_transient( self::UPDATE_KEY );
    $nb_options = Nectar\Global_Settings\Nectar_Blocks_Options::get_options();
    $token = $nb_options['token'] ?? '';
    if ($token === '') {
      return false;
    }

    if ( 'error' === $remote ) {
      return false;
    }

    if ( false === $remote || ! $this->cache_allowed ) {
      $remote = wp_safe_remote_post( self::UPDATE_URL, [
        'method' => 'POST',
        'timeout' => 10,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode( [
          'token' => $token
        ])
      ]);

      if (
        is_wp_error( $remote )
        || 200 !== wp_remote_retrieve_response_code( $remote )
        || empty( wp_remote_retrieve_body( $remote ) )
      ) {
        error_log('Nectar Theme Updater: Unable to connect to update api.');
        set_transient( self::UPDATE_KEY, 'error', MINUTE_IN_SECONDS * 10 );
        return false;
      }

      $json_data = json_decode( wp_remote_retrieve_body( $remote ) );
      if ($json_data->status === 'failure') {
        error_log('Nectar Theme Updater: Server response was unsuccessful.');
        set_transient( self::UPDATE_KEY, 'error', MINUTE_IN_SECONDS * 10 );
        return false;
      }

      set_transient( self::UPDATE_KEY, $json_data->data, 4 * HOUR_IN_SECONDS );
      return $json_data->data;
    }

    return $remote;
  }

  public function update( $transient ) {
    if ( empty($transient->checked ) ) {
      return $transient;
    }

    $remote = $this->get_current_version();
    if ($remote) {

      $theme_upgrade_data = [
        'theme' => $this->theme_slug,
        'url' => 'http://nectarblocks.com',
        'requires' => $remote->requires,
        'requires_php' => $remote->requires_php,
        'new_version' => $remote->version,
        'package' => $remote->download_url,
      ];

      if( version_compare( $this->version, $remote->version, '<' )
        && version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' )
        && version_compare( $remote->requires_php, PHP_VERSION, '<' )
      ) {
        $transient->response[$this->theme_slug] = $theme_upgrade_data;
      } else {
        $transient->no_update[$this->theme_slug] = $theme_upgrade_data;
      }

    } else {
      // Unsure if we need this, provides empty values if our remote fails
      $empty_response = [
        'theme' => $this->theme_slug,
        'new_version' => $this->get_current_version(),
        'url' => 'http://nectarblocks.com',
        'package' => '',
        'requires' => '',
        'requires_php' => '',
      ];
      $transient->no_update[$this->theme_slug] = $empty_response;
    }

    return $transient;
  }

  public function purge( $upgrader, $options ){
    if (
      $this->cache_allowed
      && 'update' === $options['action']
      && 'theme' === $options['type']
    ) {
      // just clean the cache when new theme version is installed
      delete_transient( self::UPDATE_KEY );
    }

  }

  public static function get_instance() {
    if ( ! self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }
}

NectarThemeUpdater::get_instance();
