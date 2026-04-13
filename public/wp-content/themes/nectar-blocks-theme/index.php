<?php

/**
 * The template for displaying the Blog index.
 *
 * @package Nectar Blocks Theme
 * @version 2.0.0
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

get_header();

$post_type = get_post_type();

if ( ! has_action('nectar_template_archive__' . $post_type) ) {
    if ( $post_type === 'nectar_portfolio' ) {
        get_template_part( 'includes/partials/portfolio/default-template' );
    } else {
        get_template_part( 'includes/partials/blog/default-template' );
    }
} else { ?>
    <div id="nectar-content-wrap" class="container-wrap">
        <div class="container main-content">
            <?php do_action('nectar_before_blog_loop_row'); ?>
            <?php nectar_template_archive(); ?>
        </div>
        <?php nectar_hook_before_container_wrap_close(); ?>
    </div>
<?php }
get_footer();