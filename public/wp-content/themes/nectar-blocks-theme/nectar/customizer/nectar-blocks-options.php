<?php

/**
 * NectarBlocks Options.
 *
 * @package Nectar Blocks Theme
 * @since 14.1.0
 * @version 1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'NectarBlocks_Options' ) ) {

  /**
   * NectarBlocksOptionsArray
   *
   * @since 14.1.0
   */
  class NectarBlocksOptionsArray implements ArrayAccess {
    public function __construct() {}

    /**
     * offsetExists
     * Check if offset exists in our different arrays.
     */
    public function offsetExists($offset): bool {

      $value = get_theme_mod( $offset, null );

      if ( $value === null ) {
        // check for default value in Kirki
        if ( class_exists( 'Kirki' ) &&
            isset( Kirki::$all_fields[$offset] ) &&
            isset( Kirki::$all_fields[$offset]['default'] ) &&
            ! empty(Kirki::$all_fields[$offset]['default']) ) {
            return true;
        }
      }
      if ( $value !== null ) {
        return true;
      }

      return false;
    }

    /**
     * offsetGet - get the value of the offset.
     * If not found, get default value from control
     *
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {

      $value = get_theme_mod( $offset, null );
      if ( $value === null ) {
        // Return the default set in Kirki.
        if ( class_exists( 'Kirki' ) &&
            isset( Kirki::$all_fields[$offset] ) &&
            isset( Kirki::$all_fields[$offset]['default'] ) ) {
            return Kirki::$all_fields[$offset]['default'];
        }
      }

      return $value;

    }

    /**
     * Sets the offset but this is a terrible way to do it and should be entirely replaced.
     * TODO: Remove setting from this accessor.
     */
    public function offsetSet($offset, $value): void {
      try {

        if ( strpos($offset, 'tp_') === 0 ) {

          error_log('OptionsSet: should not be setting tp_ option');

        // TODO: I'm not sure if there's a good way to handle this
        // probably should just not be happening
        // } else if ( $this->get_customizer_options() && ! empty( $this->get_customizer_options() ) ) {
        //   $this->get_customizer_options()[$offset] = $value;

        } else {
          error_log('OptionsAccessor offsetUnset: Unable to set option: ' . $offset);
        }
      } catch ( Exception $err ) {
        error_log('OptionsAccesor offsetUnset: error ' . $err);
      }
    }

    /**
     * offsetUnset - unset the value of the offset.
     * Should never be accessed.
     */
    public function offsetUnset($offset): void {
      error_log('OptionsAccesor offsetUnset:' . print_r(debug_backtrace(FALSE, 1), true));
    }
  }

  /**
   * NectarBlocks_Options
   *
   * Manages the nectar_options values.
   * @since 14.1.0
   */
  class NectarBlocks_Options {
    private static $instance = null;

    private $options;

    public function __construct() {
      $this->options = new NectarBlocksOptionsArray();
    }

    /**
     * Creates an instance.
     *
     * @since 14.0.2
     * @return NectarBlocks_Options
     */
    public static function get_instance() {
      if (self::$instance == null) {
        self::$instance = new NectarBlocks_Options();
      }

      return self::$instance;
    }

    /**
     * Returns the options accessor "array" object.
     *
     * @since 14.1.0
     * @return NectarBlocksOptionsArray
     */
    public function get_nectar_theme_options() {
      return $this->options;
    }
  }
};

/**
 * Initialize the NectarBlocks_Options class.
 */
NectarBlocks_Options::get_instance();
