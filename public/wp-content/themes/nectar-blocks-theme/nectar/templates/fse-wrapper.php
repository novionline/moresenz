<?php
/**
 * Theme Builder Template Wrapper.
 *
 * Renders the theme's standard page shell (header, nav, container, footer)
 * with the Theme Builder template content for the active WooCommerce page.
 *
 * Used when a Theme Builder template is assigned to a WooCommerce page type,
 * replacing the default WooCommerce template while keeping the theme's
 * page structure.
 *
 * @package Nectar Blocks Theme
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $nectar_wc_active_hook;

get_header();
?>

<div id="nectar-content-wrap" class="container-wrap" data-midnight="dark">
    <div class="container main-content">
        <div class="row">
            <?php do_action( $nectar_wc_active_hook ); ?>
        </div>
    </div>
</div>

<?php
get_footer();
