<?php
/**
 * The template for search results.
 *
 * @package Nectar Blocks Theme
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

global $nectar_options;
$header_format = ( ! empty( $nectar_options['header_format'] ) ) ? $nectar_options['header_format'] : 'default';

$search_results_layout = ( ! empty( $nectar_options['search-results-layout'] ) ) ? $nectar_options['search-results-layout'] : 'default';
$search_results_header_bg_image = ( ! empty( $nectar_options['search-results-header-bg-image'] ) && isset( $nectar_options['search-results-header-bg-image'] ) ) ? nectar_options_img( $nectar_options['search-results-header-bg-image'] ) : null;

$using_sidebar = ( $search_results_layout === 'default' || $search_results_layout === 'list-with-sidebar' ) ? true : false;
$using_excerpt = ( $search_results_layout === 'list-no-sidebar' || $search_results_layout === 'list-with-sidebar' ) ? true : false;

?>

<div id="page-header-bg" data-midnight="light" data-text-effect="none" data-bg-pos="center" data-alignment="center" data-alignment-v="middle" data-height="250">

    <?php if ( $search_results_header_bg_image ) { ?>
        <div class="page-header-bg-image-wrap" id="nectar-page-header-p-wrap">
            <div class="page-header-bg-image" style="background-image: url(<?php echo esc_url( $search_results_header_bg_image ); ?>);"></div>
        </div>

        <div class="page-header-overlay-color"></div>
    <?php } ?>

    <div class="container">
        <div class="row">
            <div class="col span_6 ">
                <div class="inner-wrap">
                    <h1><?php echo esc_html__( 'Results For', 'nectar-blocks-theme' ); ?> <span>"<?php echo esc_html( get_search_query( false ) ); ?>"</span></h1>
                    <?php
                    if ( $wp_query->found_posts ) {
                        echo '<span class="result-num">' . esc_html( $wp_query->found_posts ) . ' ' . esc_html__( 'results found', 'nectar-blocks-theme' ) . '</span>'; }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


<div id="nectar-content-wrap" class="container-wrap" data-layout="<?php echo esc_attr( $search_results_layout ); ?>">

    <div class="container main-content">

        <div class="row">

            <?php $search_col_span = ( $using_sidebar === true ) ? '9' : '12'; ?>
            <div class="col span_<?php echo esc_attr( $search_col_span ); // WPCS: XSS ok. ?>">

                <div id="search-results" data-layout="<?php echo esc_attr( $search_results_layout ); ?>">

                    <?php

                    add_filter( 'wp_get_attachment_image_attributes', 'nectar_remove_lazy_load_functionality' );

                    if ( have_posts() ) :
                        while ( have_posts() ) :

                            the_post();

                            $using_post_thumb = has_post_thumbnail( $post->ID );

                            if ( get_post_type( $post->ID ) === 'post' ) {
                                ?>
                                <article class="result" data-post-thumb="<?php echo esc_attr( $using_post_thumb ); ?>">
                                    <div class="inner-wrap">
                                        <?php
                                        $post_perma = get_permalink();
                                        $post_format_text = esc_html__( 'Blog Post', 'nectar-blocks-theme' );
                                        $post_target = '_self';

                                        if( get_post_format() === 'link' ) {

                                            $post_link_url = get_post_meta( $post->ID, '_nectar_link', true );
                                            $post_link_text = get_the_content();

                                            if ( empty($post_link_text) && ! empty($post_link_url) ) {
                                                    $post_perma = $post_link_url;
                                                    $post_target = '_blank';
                                            }

                                        }

                                        if ( has_post_thumbnail( $post->ID ) ) {
                                            echo '<a href="' . esc_url( $post_perma ) . '">' . get_the_post_thumbnail( $post->ID, 'nectar_4_3_aspect_medium', [ 'title' => '' ] ) . '</a>';
                                        }

                                        ?>
                                        <h2 class="title"><a href="<?php echo esc_url($post_perma); ?>" target="<?php echo esc_html($post_target); ?>"><?php the_title(); ?></a> <span><?php echo esc_html($post_format_text); ?></span></h2>
                                        <?php
                                        if ( true === $using_excerpt ) {
                                            the_excerpt(); }
                                        ?>
                                    </div>
                                </article>

                                <?php
                            } elseif ( get_post_type( $post->ID ) === 'page' ) {
                                ?>
                                <article class="result" data-post-thumb="<?php echo esc_attr( $using_post_thumb ); ?>">
                                    <div class="inner-wrap">
                                        <?php
                                        if ( has_post_thumbnail( $post->ID ) ) {
                                            echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( $post->ID, 'nectar_4_3_aspect_medium', [ 'title' => '' ] ) . '</a>';
                                        }
                                        ?>
                                        <h2 class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> <span><?php echo esc_html__( 'Page', 'nectar-blocks-theme' ); ?></span></h2>
                                        <?php
                                        if ( true === $using_excerpt ) {
                                            the_excerpt(); }
                                        ?>
                                    </div>
                                </article>

                                <?php
                            } elseif ( get_post_type( $post->ID ) === 'product' ) {
                                ?>
                                <article class="result" data-post-thumb="<?php echo esc_attr( $using_post_thumb ); ?>">
                                    <div class="inner-wrap">
                                        <?php
                                        if ( has_post_thumbnail( $post->ID ) ) {
                                            echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( $post->ID, 'nectar_4_3_aspect_medium', [ 'title' => '' ] ) . '</a>';
                                        }
                                        ?>
                                        <h2 class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> <span><?php echo esc_html__( 'Product', 'nectar-blocks-theme' ); ?></span></h2>
                                        <?php
                                        if ( true === $using_excerpt ) {
                                            the_excerpt(); }
                                        ?>
                                    </div>
                                </article>

                            <?php } else { ?>
                                <article class="result" data-post-thumb="<?php echo esc_attr( $using_post_thumb ); ?>">
                                    <div class="inner-wrap">
                                        <?php
                                        if ( has_post_thumbnail( $post->ID ) ) {
                                            echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( $post->ID, 'nectar_4_3_aspect_medium', [ 'title' => '' ] ) . '</a>';
                                        }
                                        ?>
                                        <h2 class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                                        <?php
                                        if ( true === $using_excerpt ) {
                                            the_excerpt(); }
                                        ?>
                                    </div>
                                </article>

                            <?php }

                    endwhile;

                    else :

                        echo '<h3>' . esc_html__( 'Sorry, no results were found.', 'nectar-blocks-theme' ) . '</h3>';
                        echo '<p>' . esc_html__( 'Please try again with different keywords.', 'nectar-blocks-theme' ) . '</p>';
                        get_search_form();

                  endif;

                    ?>

                </div>

                <div class="search-result-pagination" data-layout="<?php echo esc_attr( $search_results_layout ); ?>">
                    <?php nectar_pagination(); ?>
                </div>

            </div>

            <?php if ( $using_sidebar === true ) { ?>

                <div id="sidebar" class="col span_3 col_last">
                    <?php
                        nectar_hook_sidebar_top();
                        get_sidebar();
                        nectar_hook_sidebar_bottom();
                    ?>
                </div>

            <?php } ?>

        </div>

    </div>
    <?php nectar_hook_before_container_wrap_close(); ?>
</div>

<?php get_footer(); ?>
