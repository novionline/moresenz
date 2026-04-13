<?php

/**
 * Customizer Control: panel.
 *
 * @since 13.0.8
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( class_exists( 'WP_Customize_Section' ) ) {

  /**
   * Custom Customize Section for nested sections.
   *
   * @since 13.0.8
   * @see WP_Customize_Section
   */
  class NectarBlocks_WP_Customize_Section extends WP_Customize_Section {
    /**
     * Section
     *
     * @since 13.0.8
     * @var string
     */
    public $section;

    /**
     * Control type.
     *
     * @since  13.0.8
     * @var string
     */
    public $type = 'nectar_section';

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
        'panel',
        'type',
        'description_hidden',
        'section'
      ] );
      $array['title'] = html_entity_decode( $this->title, ENT_QUOTES, get_bloginfo( 'charset' ) );
      $array['content'] = $this->get_content();
      $array['active'] = $this->active();
      $array['instanceNumber'] = $this->instance_number;

      if ( $this->panel ) {
        $array['customizeAction'] = sprintf( 'Customizing &#9656; %s', esc_html( $this->manager->get_panel( $this->panel )->title ) );
      } else {
        $array['customizeAction'] = 'Customizing';
      }

      return $array;
    }
  }
}
