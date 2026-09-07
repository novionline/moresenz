<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php
    $nectar_options = get_nectar_theme_options();
    nectar_meta_viewport();
    wp_head();
    ?>
</head><?php

$nectar_header_options = nectar_get_header_variables();
$has_header_nav_template = function_exists( 'nectar_has_header_nav_template' ) && nectar_has_header_nav_template();

?><body <?php body_class(); ?> <?php nectar_body_attributes(); ?>>
<?php

nectar_hook_after_body_open();
nectar_hook_before_header_nav();
get_template_part( 'includes/partials/header/nectar-nav-spacer' );

?><div id="nectar-nav" <?php nectar_header_nav_attributes(); ?>>
    <?php
    if ( $has_header_nav_template ) {
        do_action( 'nectar_template__header_navigation' );

        // Ensure the "Simple Dropdown" mobile menu markup still exists when using a header nav template.
        // `classic-mobile-nav.php` provides `#mobile-menu`, which JS toggles via `OCM_simpleDropdownOpen()`.
        $legacy_double_menu = nectar_legacy_mobile_double_menu();
        if ( ( isset( $nectar_header_options['side_widget_class'] ) && 'simple' === $nectar_header_options['side_widget_class'] ) || true === $legacy_double_menu ) {
            get_template_part( 'includes/partials/header/classic-mobile-nav' );
        }
    } else {
        get_template_part( 'includes/partials/header/secondary-navigation' );

        if ( 'left-header' !== $nectar_header_options['header_format']) {
            get_template_part( 'includes/header-search' );
        }

        get_template_part( 'includes/partials/header/header-menu' );
    }
    ?>
</div>
<?php

if ( ! $has_header_nav_template ) {
    if ( ! empty( $nectar_options['enable-cart'] ) && '1' === $nectar_options['enable-cart'] ) {
        get_template_part( 'includes/partials/header/woo-slide-in-cart' );
    }

    if (
           'left-header' === $nectar_header_options['header_format'] &&
           'false' !== $nectar_header_options['header_search'] ) {
        get_template_part( 'includes/header-search' );
    }
}

nectar_hook_after_outer_wrap_open();
