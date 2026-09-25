<?php

/**
 * Customizer Control: IconSelect
 *
 * @package
 * @copyright
 * @since
 */

// use Kirki\Control\Base;
// use Kirki\URL;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists('WP_Customize_Control') ) {

  /**
   * IconSelect ( modified Radio Image control (modified radio)).
   */
  class IconSelect extends Kirki\Control\Base {
    // public function enqueue() {
    //   wp_enqueue_script( 'input-slider', WPBF_THEME_URI . '/inc/customizer/controls/input-slider/js/input-slider.js', array( 'jquery' ), WPBF_VERSION, true );
    //   wp_enqueue_style( 'input-slider', WPBF_THEME_URI . '/inc/customizer/controls/input-slider/css/input-slider.css', '', WPBF_VERSION );
    // }

    /**
     * The control type.
     *
     * @access public
     * @since 1.0
     * @var string
     */
    public $type = 'nectar-icon-select';

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
      // var_dump( parent::to_json() );
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
