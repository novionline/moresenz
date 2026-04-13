<?php
/**
 * The template for 404 not found.
 *
 * @package Nectar Blocks Theme
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$page_404_font_color = ( ! empty( $nectar_options['page-404-font-color'] ) ) ? $nectar_options['page-404-font-color'] : '';
$page_404_bg_image = ( ! empty( $nectar_options['page-404-bg-image'] ) && isset( $nectar_options['page-404-bg-image'] ) ) ? nectar_options_img( $nectar_options['page-404-bg-image'] ) : null;
$page_404_bg_image_overlay = ( ! empty( $nectar_options['page-404-bg-image-overlay-color'] ) ) ? $nectar_options['page-404-bg-image-overlay-color'] : '';
$page_404_home_button = ( ! empty( $nectar_options['page-404-home-button'] ) ) ? $nectar_options['page-404-home-button'] : '';

?>

<div id="nectar-content-wrap" class="container-wrap">

    <?php
    if ( ! empty( $page_404_bg_image ) ) {
        echo '<div class="error-404-bg-img" style="background-image: url(' . esc_url( $page_404_bg_image ) . ');"></div>';

        if ( ! empty( $page_404_bg_image_overlay ) ) {
             echo '<div class="error-404-bg-img-overlay"></div>';
        }
    }
    ?>

    <div class="container main-content">

        <div class="row">

            <div class="col span_12">

                <?php nectar_hook_404_content(); ?>
                <?php if ( ! has_action('nectar_template__404') ) { ?>
                    <div id="error-404"
                    <?php
                    if ( ! empty( $page_404_font_color ) ) {
                        echo 'data-cc="true"'; }
                    ?>
                    >
                        <h1><?php echo esc_html__( '404', 'nectar-blocks-theme' ); ?></h1>
                        <h2><?php echo esc_html__( 'Page Not Found', 'nectar-blocks-theme' ); ?></h2>

                        <?php if ( $page_404_home_button === '1' ) { ?>
                            <a class="nectar-button large regular-button accent-color has-icon" data-color-override="false" data-hover-color-override="false" href="<?php echo esc_url( home_url() ); ?>"><span><?php echo esc_html__( 'Back Home', 'nectar-blocks-theme' ); ?> </span><i class="icon-button-arrow"></i></a>
                            <?php } ?>
                    </div>
                <?php } ?>

            </div>

        </div>

    </div>
    <?php nectar_hook_before_container_wrap_close(); ?>
</div>
<?php get_footer(); ?>
