<?php

/**
 * Customizer Control: Responsive Slider.
 *
 * @package Nectar Blocks Theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'WP_Customize_Control' ) ) {

  class ResponsiveSlider extends Kirki\Control\Base {
    public $type = 'nectar-responsive-slider';

    public static $control_ver = '1.0';

    public function enqueue() {
      parent::enqueue();
    }

    public function to_json() {
      parent::to_json();

      // Mirror Kirki's stock slider: decode entities so apostrophes / em-dashes
      // render correctly when JSX inserts the strings as text nodes.
      if ( isset( $this->json['label'] ) ) {
        $this->json['label'] = html_entity_decode( $this->json['label'] );
      }
      if ( isset( $this->json['description'] ) ) {
        $this->json['description'] = html_entity_decode( $this->json['description'] );
      }
    }

    protected function content_template() {}
  }

}
