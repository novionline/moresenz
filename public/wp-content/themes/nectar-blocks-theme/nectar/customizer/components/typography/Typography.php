<?php

/**
 * Customizer Control: Gradient
 *
 * @package
 * @copyright
 * @since
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists('WP_Customize_Control') ) {

  /**
   * Gradient
   */
  class Typography extends Kirki\Control\Base {
    /**
     * The control type.
     *
     * @access public
     * @since 1.0
     * @var string
     */
    public $type = 'nectar-typography';

    /**
     * The control version.
     *
     * @static
     * @access public
     * @since 1.0
     * @var string
     */
    public static $control_ver = '1.0';

    /**
     * Enqueue control related scripts/styles.
     *
     * @access public
     * @since 1.0
     * @return void
     */
    public function enqueue() {
      parent::enqueue();
    }

    /**
     * Refresh the parameters passed to the JavaScript via JSON.
     *
     * @access public
     * @since 1.0
     * @see WP_Customize_Control::to_json()
     * @return void
     */
    public function to_json() {
      // Get the basics from the parent class.
      parent::to_json();
    }

    /**
     * An Underscore (JS) template for this control's content (but not its container).
     *
     * Class variables for this control class are available in the `data` JS object;
     * export custom variables by overriding {@see WP_Customize_Control::to_json()}.
     *
     * @see WP_Customize_Control::print_template()
     *
     * @access protected
     * @since 1.0
     * @return void
     */
    protected function content_template() {}
  }

}
