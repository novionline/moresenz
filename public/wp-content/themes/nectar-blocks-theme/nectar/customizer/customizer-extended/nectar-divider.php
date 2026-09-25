<?php

if ( class_exists( 'WP_Customize_Section' ) ) {

class NectarBlocks_Customizer_Divider extends WP_Customize_Section {
  /**
   * Control type.
   *
   * @since  13.0.8
   * @var string
   */
  public $type = 'nectar-divider';

  /**
   * Special categorization for the section.
   *
   * @var string
   */
  public $kind = 'default';

  /**
   * Output
   */
  public function render() {
    ?>
    <li
      id="accordion-section-<?php echo esc_attr( $this->id ); ?>"
      class="accordion-section nectar-customizer-divider">
      <div class="nectar-customizer-divider__line"></div>
    </li>
    <?php
  }
}

}
