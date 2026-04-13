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

      // error_log('Importing: ' . $id . ' ' . $option_value);
      $updated_value = $option_value;

      // Image settings
      if ( array_key_exists($id, $nectar_fields) &&
           array_key_exists('type', $nectar_fields[$id]) &&
           $nectar_fields[$id]['type'] === 'media'
      ){
        $image_data = $this->sideload_image( $updated_value['url'] );
        error_log('Nectar Theme Import: Image data: ' . print_r($image_data, true));
        $updated_value['url'] = $image_data->url;
      }

      set_theme_mod($id, $updated_value);
    }
  }

  /**
   * Taken from the core media_sideload_image function and
   * modified to return an array of data instead of html.
   *
   * @since 0.1
   * @access private
   * @param string $file The image file path.
   * @return class An array of image data.
   */
  private function sideload_image( $file ) {
    $data = new stdClass();

    if ( ! function_exists( 'media_handle_sideload' ) ) {
      require_once( ABSPATH . 'wp-admin/includes/media.php' );
      require_once( ABSPATH . 'wp-admin/includes/file.php' );
      require_once( ABSPATH . 'wp-admin/includes/image.php' );
    }

    if ( ! empty( $file ) ) {

      // Set variables for storage, fix file filename for query strings.
      // TODO: This doesn't seem to really cover the image suffix set well
      preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png|webp)\b/i', $file, $matches );
      $file_array = [];
      $file_array['name'] = basename( $matches[0] );

      // Download file to temp location.
      $file_array['tmp_name'] = download_url( $file );

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

      // TODO: This doesn't seem to really cover the image suffix set well
      if ( preg_match( '/\.(jpg|jpeg|png|gif|webp)/i', $string ) ) {
        return true;
      }
    }

    return false;
  }
}