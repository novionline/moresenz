<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( class_exists( 'WP_Customize_Panel' ) ) {

  /**
   * Custom Panel for nested panels / sections.
   *
   * @since 13.0.8
   * @see WP_Customize_Panel
   */
  class NectarBlocks_WP_Customize_Panel extends WP_Customize_Panel {
    /**
     * Panel
     *
     * @since 13.0.8
     * @var string
     */
    public $panel;

    /**
     * Control type.
     *
     * @since  13.0.8
     * @var string
     */
    public $type = 'nectar_panel';

    /**
     * Get section parameters for JS.
     *
     * @since 13.0.8
     * @return array Exported parameters.
     */
    public function json() {
      $array = wp_array_slice_assoc( (array) $this, [
        'id',
        'description',
        'priority',
        'type',
        'panel'
      ] );
      $array['title'] = html_entity_decode( $this->title, ENT_QUOTES, get_bloginfo( 'charset' ) );
      $array['content'] = $this->get_content();
      $array['active'] = $this->active();
      $array['instanceNumber'] = $this->instance_number;

      return $array;
    }
  }

}
