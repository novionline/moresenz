<?php
/**
 * The template for displaying single posts.
 *
 * @package Nectar Blocks Theme
 * @version 2.0.0
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$blog_social_style = ( get_option( 'salient_social_button_style' ) ) ? get_option( 'salient_social_button_style' ) : 'fixed';

get_header();

$post_type = get_post_type();

if ( ! has_action('nectar_template_single__' . $post_type) ) {
    get_template_part( 'includes/partials/single-post/default-template' );
} else { ?>
    <div id="nectar-content-wrap" class="container-wrap">
        <div class="container main-content">
            <?php nectar_template_single(); ?>
            <?php get_template_part('includes/partials/single-post/post-after-content'); ?>
        </div>
        <?php nectar_hook_before_container_wrap_close(); ?>
    </div>
    <?php
}

if ( 'fixed' === $blog_social_style ) {
    // Social sharing buttons.
    if( function_exists('nectar_social_sharing_output') ) {
        nectar_social_sharing_output('fixed');
    }
}

get_footer(); ?>
