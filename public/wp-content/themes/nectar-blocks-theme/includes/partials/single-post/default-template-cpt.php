<?php
/**
 * Slim single template for non-blog custom post types.
 *
 * Page-style wrapper (no sidebar, no post-area grid, no .post-content gutter).
 * The <main> element itself carries the post id + post_class(); the shared post
 * header, featured media, content and tags render directly inside it, minus the
 * blog chrome that is gated to 'post'.
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @since 3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

$nectar_options = get_nectar_theme_options();
$hide_featured_media = apply_filters(
    'nectar_cpt_hide_featured_media',
    ( ! empty( $nectar_options['blog_hide_featured_image'] ) ) ? $nectar_options['blog_hide_featured_image'] : '0',
    get_post_type()
);

// Post header (banner when a header bg is set).
if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        nectar_page_header( $post->ID );
    endwhile;
endif;
?>
<div id="nectar-content-wrap" class="container-wrap no-sidebar" data-midnight="<?php echo esc_attr( apply_filters( 'nectar_single_post_container_midnight', 'dark' ) ); ?>">
    <main id="post-<?php echo esc_attr( get_the_ID() ); ?>" <?php post_class( 'container main-content' ); ?>>

        <?php
        // Title heading when no page-header banner is in use (mirrors the banner's
        // bg gate and its "Hide Title" check in nectar_page_header()).
        $hide_title = get_post_meta( $post->ID, '_nectar_blocks_hide_post_title', true );
        $header_bg = apply_filters( 'nectar_page_header_bg_val', get_post_meta( $post->ID, '_nectar_header_bg', true ) );
        $header_bg_color = apply_filters( 'nectar_page_header_bg_color_val', get_post_meta( $post->ID, '_nectar_header_bg_color', true ) );
        if ( '1' !== $hide_title && empty( $header_bg ) && empty( $header_bg_color ) ) :
            ?>
            <h1 class="entry-title"><?php echo esc_html( get_the_title() ); ?></h1>
            <?php
        endif;

        nectar_hook_before_content();

        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();

                if ( '1' !== $hide_featured_media && has_post_thumbnail() ) {
                    echo '<span class="post-featured-img">' . get_the_post_thumbnail( get_the_ID(), 'full', [ 'title' => '' ] ) . '</span>';
                }

                the_content();
                wp_link_pages();

                if ( ! empty( $nectar_options['display_tags'] ) && '1' === $nectar_options['display_tags'] && has_tag() ) {
                    echo '<div class="nectar-post-tags">';
                    the_tags( '', '', '' );
                    echo '</div>';
                }

            endwhile;
        endif;

        nectar_hook_after_content();
        ?>

        <?php get_template_part( 'includes/partials/single-post/post-after-content' ); ?>
    </main>
    <?php nectar_hook_before_container_wrap_close(); ?>
</div>
