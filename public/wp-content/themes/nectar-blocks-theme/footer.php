<?php
/**
* The template for displaying the footer.
*
* @package Nectar Blocks Theme
* @version 1.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nectar_options = get_nectar_theme_options();
$header_format = ( ! empty($nectar_options['header_format']) ) ? $nectar_options['header_format'] : 'default';

$using_footer_area = true;
$using_footer_widget_area = ( isset( $nectar_options['enable-main-footer-area'] ) && $nectar_options['enable-main-footer-area'] === '1' ) ? true : false;
$using_footer_copyright = ( isset( $nectar_options['disable-copyright-footer-area'] ) && $nectar_options['disable-copyright-footer-area'] === '1' ) ? false : true;
if ( $using_footer_widget_area === false && $using_footer_copyright === false ) {
    $using_footer_area = false;
}
nectar_hook_before_footer_open();

if ( $using_footer_area ) {
    ?>

    <div id="footer-outer" <?php nectar_footer_attributes(); ?>>
        
        <?php

        nectar_hook_after_footer_open();

        get_template_part( 'includes/partials/footer/main-widgets' );

        get_template_part( 'includes/partials/footer/copyright-bar' );

        ?>
        
    </div>

    <?php
}
nectar_hook_before_outer_wrap_close();

get_template_part( 'includes/partials/footer/off-canvas-navigation' );

?>

<?php

    get_template_part( 'includes/partials/footer/back-to-top' );

    nectar_hook_after_wp_footer();
    nectar_hook_before_body_close();

    wp_footer();
?>
</body>
</html>