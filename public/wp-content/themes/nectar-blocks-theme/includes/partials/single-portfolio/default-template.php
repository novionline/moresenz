<?php

/**
 * Default template for single portfolio.
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @since 2.0.0
 * @version 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>

<div id="nectar-content-wrap" class="container-wrap no-sidebar" data-midnight="<?php echo apply_filters('nectar_single_post_container_midnight', 'dark') ?>">
    <div class="container main-content">

        <?php get_template_part( 'includes/partials/single-portfolio/post-header-featured-media-under' ); ?>

        <div class="nectar-blocks__post-section">

            <?php nectar_hook_before_content(); ?>

            <div class="post-area col span_12 col_last" role="main">

                <?php
                // Main content loop.
                if ( have_posts() ) :
                    while ( have_posts() ) :

                        the_post();
                        get_template_part( 'includes/partials/single-portfolio/post-content' );

                    endwhile;
                endif;

                wp_link_pages();
                nectar_hook_after_content();
                ?>

            </div>
        </div>
    </div>
    <?php nectar_hook_before_container_wrap_close(); ?>
</div>
