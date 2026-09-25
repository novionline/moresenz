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
// Site-wide transparency master switch. The customizer toggle is the master: if
// it's off, transparency is off site-wide even when a header-builder template is
// active or a per-post setting requests it. (`nectar_is_contained_header()` is its
// own opt-in layout mode that brings its own clearance handling, so it counts as
// transparency-capable independently.)
$transparency_in_play = nectar_customizer_trans_header_enabled() || nectar_is_contained_header();

// `bg_header` is the verdict from nectar_using_page_header() — it's true when the
// transparent-header effect activates for this page (per-post page-header config /
// _force_transparent_header / applicable shortcode) and false when
// _disable_transparent_header is set. Alias it locally so the gate below reads as
// "transparency activated for this page" instead of bouncing off the historical name.
$page_activates_transparency = ( $nectar_header_options['bg_header'] == 'true' );

// Render the spacer unless transparency is both activated for this page AND the
// site-wide transparency switch is on to actually render it. Equivalent (by
// De Morgan) to: render if perma-forced, OR this page doesn't activate transparency,
// OR site-wide transparency is off. Suppression only happens when the two line up —
// the nav will be visually transparent over the hero, so the content underneath is
// intended to sit beneath it.
if (
    $perma_forced ||
    ! ( $page_activates_transparency && $transparency_in_play )
) {
    // Mirror #nectar-nav's marker so cached legacy CSS that paints the spacer a
    // legacy header background can scope itself out via :not([data-header-builder]).
    $nectar_spacer_is_builder = ( function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template() );
    ?><div id="nectar-nav-spacer" <?php echo (esc_html($header_secondary_m_attr)) ? 'data-secondary-header-display="full"' : ''; ?> <?php echo $nectar_spacer_is_builder ? 'data-header-builder="true" ' : ''; ?>data-header-mobile-fixed='<?php echo esc_attr( $nectar_header_options['mobile_fixed'] ); ?>'></div><?php
}
