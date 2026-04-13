<?php
/**
* Header nav space
*
* @package    Nectar WordPress Theme
* @subpackage Partials
* @version    10.5
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

$nectar_header_options = nectar_get_header_variables();
$nectar_options = get_nectar_theme_options();
$header_format = ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default';
$using_secondary = ( ! empty( $nectar_options['header_layout'] ) && $header_format !== 'left-header' ) ? $nectar_options['header_layout'] : ' ';
$header_secondary_m_display = ( ! empty( $nectar_options['secondary-header-mobile-display'] ) ) ? $nectar_options['secondary-header-mobile-display'] : 'default';
$header_secondary_m_attr = ( $using_secondary === 'header_with_secondary' && $header_secondary_m_display === 'display_full' ) ? true : false;
$using_img_logo = ( isset($nectar_options['use-logo']) && $nectar_options['use-logo'] === '1' ) ? true : false;

$perma_forced = nectar_is_perma_trans_header_forced();
if (
    $perma_forced ||
    $nectar_header_options['bg_header'] == 'false' ||
    $nectar_header_options['page_full_screen_rows'] === 'on'
) { ?>

    <div id="nectar-nav-spacer" <?php echo (esc_html($header_secondary_m_attr)) ? 'data-secondary-header-display="full"' : ''; ?> data-header-mobile-fixed='<?php echo esc_attr( $nectar_header_options['mobile_fixed'] ); ?>'>
        <?php if ( ! $using_img_logo ) { ?>
            <span class="logo">
                <?php echo apply_filters('nectar_logo_text', get_bloginfo( 'name' )); ?>
            </span>
        <?php } ?>
    </div>

    <?php

}