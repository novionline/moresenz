<?php

/**
 * Theme_IE aka Import Export
 *
 * @since 0.1.5
 */
class Theme_IE {
  private static $instance = null;

  public function __construct() {}

  /**
   * Creates an instance.
   *
   * @since 0.1.5
   * @return Theme_IE
   */
  public static function get_instance() {
    if (self::$instance == null) {
      self::$instance = new Theme_IE();
    }

    return self::$instance;
  }

  /**
   * @since 0.1.5
   */
  function export_options() {
    $data = $this->export_from_thememods();
    return $data;
  }

  /**
   * Imports the theme mods.
   *
   * @since 0.1.5
   * @return array
   */
  function import_options($parsed_import_data) {
    $this->import_thememods( $parsed_import_data );
    // Regenerate CSS
    set_transient( 'nectar_dynamic_css_needs_updating', 'true', DAY_IN_SECONDS);
  }

  /**
   * Export customizer settings.
   *
   * @since 0.1.5
   * @return void
   */
  private function export_from_thememods() {
    $options = get_theme_mods();

    if (! class_exists('Kirki')) {
      require_once NECTAR_THEME_DIRECTORY . '/vendor/kirki-framework/kirki/kirki.php';
    }

    if (! class_exists('NectarBlocks_Panel_Section_Helper')) {
      require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-panel-section-helper.php';
      require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/nectar-blocks-customizer.php';
    }

    $nectar_fields = Kirki::$all_fields;

    $data = [];
    foreach ( $nectar_fields as $id => $value ) {
      if ( ! isset($options[$id]) ) {
        continue;
      }

      if ( isset($value['type']) && $value['type'] === 'custom' ) {
        continue;
      }

      $data[$id] = $options[$id];
    }
    return $data;
  }

  /**
  * Imports all registered Nectar options via thememods
  * @since 0.1.5
  * @return void
  */
  private function import_thememods( array $import_data ) {

    if (! class_exists('Kirki')) {
      require_once NECTAR_THEME_DIRECTORY . '/vendor/kirki-framework/kirki/kirki.php';
    }

    if (! class_exists('NectarBlocks_Panel_Section_Helper')) {
      require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/customizer-panel-section-helper.php';
      require_once NECTAR_THEME_DIRECTORY . '/nectar/customizer/nectar-blocks-customizer.php';
    }

    // These are all the fields registered from Kirki
    $kirki_nectar_fields = Kirki::$all_fields;
    // These are all of our fields mapped id => wp_bakery setting
    $nectar_fields = \NectarBlocks_Panel_Section_Helper::get_instance()->nectar_customizer_settings_mapped_id();

    foreach ($import_data as $id => $option_value) {
      if ( ! isset($kirki_nectar_fields[$id]) ) {
        // error_log('Nectar Theme Import: Unable to find setting: ' . $id);
        continue;
      }

      // Skip display-only controls (info notices) — no real setting value,
      // and older demo exports may have baked in stale notice HTML.
      if ( isset($kirki_nectar_fields[$id]['type']) && $kirki_nectar_fields[$id]['type'] === 'custom' ) {
        continue;
      }

      // error_log('Importing: ' . $id . ' ' . $option_value);
      $updated_value = $option_value;

      // Image settings
      if ( array_key_exists($id, $nectar_fields) &&
           array_key_exists('type', $nectar_fields[$id]) &&
           $nectar_fields[$id]['type'] === 'media'
      ){
        $image_url = isset($updated_value['url']) ? $updated_value['url'] : '';

        // SSRF guard: only sideload remote URLs that resolve to a public host.
        if ( ! $this->is_safe_remote_url( $image_url ) ) {
          error_log('Nectar Theme Import: Skipping unsafe image URL for setting ' . $id);
          set_theme_mod($id, $updated_value);
          continue;
        }

        $image_data = $this->sideload_image( $image_url );
        error_log('Nectar Theme Import: Image data: ' . print_r($image_data, true));
        if ( ! is_wp_error( $image_data ) && isset( $image_data->url ) ) {
          $updated_value['url'] = $image_data->url;
        }
      }

      set_theme_mod($id, $updated_value);
    }

    // Reset any registered global color link fields that were not
    // included in the import data, so stale links don't persist.
    if ( class_exists( 'Nectar_Global_Color_Links' ) ) {
      $suffix = Nectar_Global_Color_Links::LINK_SUFFIX;
      foreach ( $kirki_nectar_fields as $field_id => $field ) {
        if ( substr( $field_id, -strlen( $suffix ) ) === $suffix && ! isset( $import_data[$field_id] ) ) {
          set_theme_mod( $field_id, '' );
        }
      }
    }
  }

  /**
   * Validates that a URL is safe to fetch server-side.
   *
   * Blocks SSRF attempts against loopback, link-local, and RFC1918 private
   * ranges (including AWS metadata at 169.254.169.254). Rejects malformed
   * URLs, non-http(s) schemes, and hostnames that resolve to ANY private IP.
   *
   * @since 2.5.5
   * @access private
   * @param string $url The URL to validate.
   * @return bool True if the URL is safe to fetch, false otherwise.
   */
  private function is_safe_remote_url( $url ) {
    if ( ! is_string( $url ) || $url === '' ) {
      return false;
    }

    // WP-level validation. Returns false on malformed URLs and on disallowed
    // hosts when WP_HTTP_BLOCK_EXTERNAL/WP_ACCESSIBLE_HOSTS are in play.
    if ( false === wp_http_validate_url( $url ) ) {
      return false;
    }

    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
      return false;
    }

    $scheme = strtolower( $parts['scheme'] );
    if ( $scheme !== 'http' && $scheme !== 'https' ) {
      return false;
    }

    $host = $parts['host'];

    // Strip IPv6 brackets if present.
    if ( strlen( $host ) > 1 && $host[0] === '[' && substr( $host, -1 ) === ']' ) {
      $host = substr( $host, 1, -1 );
    }

    // Resolve the host. If it's already a literal IP, gethostbynamel returns
    // [$host]; otherwise we get the A records (it does not return AAAA).
    $ips = [];
    if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
      $ips[] = $host;
    } else {
      $resolved = @gethostbynamel( $host );
      if ( is_array( $resolved ) && ! empty( $resolved ) ) {
        $ips = $resolved;
      }
      // Try AAAA records as well so we don't accept an IPv6-only loopback.
      if ( function_exists( 'dns_get_record' ) ) {
        $aaaa = @dns_get_record( $host, DNS_AAAA );
        if ( is_array( $aaaa ) ) {
          foreach ( $aaaa as $record ) {
            if ( ! empty( $record['ipv6'] ) ) {
              $ips[] = $record['ipv6'];
            }
          }
        }
      }
    }

    if ( empty( $ips ) ) {
      // DNS failure: refuse rather than fetch.
      return false;
    }

    foreach ( $ips as $ip ) {
      $public = filter_var(
          $ip,
          FILTER_VALIDATE_IP,
          FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
      );
      if ( false === $public ) {
        // Belongs to a private/reserved range (10/8, 172.16/12, 192.168/16,
        // 127/8, 169.254/16, ::1, fc00::/7, fe80::/10, etc.).
        return false;
      }
    }

    return true;
  }

  /**
   * Taken from the core media_sideload_image function and
   * modified to return an array of data instead of html.
   *
   * @since 0.1
   * @access private
   * @param string $file The image file path.
   * @return \stdClass|\WP_Error Object of image data on success, WP_Error on failure.
   */
  private function sideload_image( $file ) {
    $data = new stdClass();

    if ( ! function_exists( 'media_handle_sideload' ) ) {
      require_once( ABSPATH . 'wp-admin/includes/media.php' );
      require_once( ABSPATH . 'wp-admin/includes/file.php' );
      require_once( ABSPATH . 'wp-admin/includes/image.php' );
    }

    if ( ! empty( $file ) ) {

      // Defense in depth: re-validate here in case sideload_image is ever
      // called from another path that did not pre-check the URL.
      if ( ! $this->is_safe_remote_url( $file ) ) {
        return new WP_Error( 'nectar_ie_unsafe_url', 'Refusing to download from unsafe URL.' );
      }

      // Pull a sane filename out of the URL path only, ignoring the query
      // string entirely. The path-anchored extension match prevents
      // query-string trickery like `?fake=.jpg`.
      $path = wp_parse_url( $file, PHP_URL_PATH );
      if ( ! is_string( $path ) || $path === '' ) {
        return new WP_Error( 'nectar_ie_bad_url', 'Could not parse URL path.' );
      }

      if ( ! preg_match( '/\.(jpe?g|gif|png|webp)$/i', $path ) ) {
        return new WP_Error( 'nectar_ie_bad_ext', 'URL path does not end with a supported image extension.' );
      }

      $file_array = [];
      $file_array['name'] = basename( $path );

      // Download file to temp location with a bounded timeout so an
      // unreachable host cannot pin a worker.
      $file_array['tmp_name'] = download_url( $file, 30 );

      // If error storing temporarily, return the error.
      if ( is_wp_error( $file_array['tmp_name'] ) ) {
        return $file_array['tmp_name'];
      }

      // Do the validation and storage stuff.
      $id = media_handle_sideload( $file_array, 0 );

      // If error storing permanently, unlink.
      if ( is_wp_error( $id ) ) {
        @unlink( $file_array['tmp_name'] );
        return $id;
      }

      // Build the object to return.
      $meta = wp_get_attachment_metadata( $id );
      $data->attachment_id = $id;
      $data->url = wp_get_attachment_url( $id );
      $data->thumbnail_url = wp_get_attachment_thumb_url( $id );
      $data->height = $meta['height'];
      $data->width = $meta['width'];
    }

    return $data;
  }

  /**
   * Checks to see whether a string is an image url or not.
   *
   * @since 0.1.5
   * @access private
   * @param string $string The string to check.
   * @return bool Whether the string is an image url or not.
   */
  private function is_image_url( $string = '' ) {
    if ( is_string( $string ) ) {
      $path = wp_parse_url( $string, PHP_URL_PATH );
      if ( is_string( $path ) && preg_match( '/\.(jpe?g|gif|png|webp)$/i', $path ) ) {
        return true;
      }
    }

    return false;
  }
}
