<?php
/**
 * Footer copyright bar
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 13.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nectar_options = get_nectar_theme_options();

$disable_footer_copyright = ( ! empty( $nectar_options['disable-copyright-footer-area'] ) && $nectar_options['disable-copyright-footer-area'] === '1' ) ? 'true' : 'false';
$copyright_footer_layout = ( ! empty( $nectar_options['footer-copyright-layout'] ) ) ? $nectar_options['footer-copyright-layout'] : 'default';
$footer_columns = ( ! empty( $nectar_options['footer_columns'] ) ) ? $nectar_options['footer_columns'] : '4';

if ( 'false' === $disable_footer_copyright ) {
    ?>

  <div class="row" id="copyright" data-layout="<?php echo esc_attr( $copyright_footer_layout ); ?>">
    <div class="container">
      <div class="col span_5">
        <?php
          nectar_footer_copyright_text();
        ?>
      </div>
      <div class="col span_7 col_last">
        <?php
        if ( function_exists( 'dynamic_sidebar' ) && dynamic_sidebar( 'Footer Copyright' ) ) :
        else :
        ?>
        <div class="widget"></div>         
        <?php endif; ?>
      </div>

      </div>
  </div>
    <?php }
