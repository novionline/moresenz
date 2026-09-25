<?php

/**
 * Default template for single post.
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 1.0
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nectar_options = get_nectar_theme_options();

// Post navigation — enabled per post type via nectar_single_post_nav_settings()
// (Blog options for blog post types, the per-CPT options otherwise).
nectar_next_post_display();

// Related posts remain a blog-only module.
$is_blog_single = in_array( get_post_type(), apply_filters( 'nectar_blog_single_post_types', [ 'post' ] ), true );
if ( apply_filters( 'nectar_single_related_posts', $is_blog_single, get_post_type() ) ) {
    nectar_related_post_display();
}

// Blog singles always render the comments wrapper (it has dedicated closed-state
// styling); other post types only render it when comments are open or present.
$show_comments = $is_blog_single || comments_open() || get_comments_number() > 0;
$show_comments = apply_filters( 'nectar_single_show_comments', $show_comments, get_post_type() );

if ( $show_comments ) :
    $author_bio = ( isset( $nectar_options['author_bio'] ) && $nectar_options['author_bio'] === '1' ) ? 'true' : 'false';
    ?>
    <div class="nectar-blocks__post-section">
        <div class="comments-section" data-author-bio="<?php echo esc_attr( $author_bio ); ?>">
            <?php comments_template(); ?>
        </div>
    </div>
    <?php
endif;