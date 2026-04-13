<?php
/**
* The template for global sections.
*
* @package Nectar Blocks Theme
* @version 1.0
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nectar_template_meta = get_post_meta( get_the_ID(), '_nectar_template_part_options', true );
if ( isset( $nectar_template_meta['templatePart'] ) && ! empty( $nectar_template_meta['templatePart'] ) ) {
    $location = esc_attr( $nectar_template_meta['templatePart'] );
    $located_template = explode( '__', $location )[1];

    if ( strpos( $location, 'archive' ) !== false ) {
      $archive_link = get_post_type_archive_link( $located_template );
      wp_redirect( $archive_link );
      exit;
    } else if ( strpos( $location, 'single' ) !== false ) {
      // get the permalink for the first post found in the cpt located_template
      $args = [
        'post_type' => $located_template,
        'posts_per_page' => 1,
      ];
      $query = new WP_Query( $args );
      $single_link = get_permalink( $query->posts[0]->ID );
      if ( $single_link ) {
        wp_redirect( $single_link );
        exit;
      }
    } else if ( strpos( $location, '404' ) !== false ) {
      wp_redirect( home_url( '/404-template' ) );
      exit;
    }
}

get_header();

?>
<div id="nectar-content-wrap" class="container-wrap">
    <div class="container main-content">
        <?php

            nectar_hook_before_content();

            if ( have_posts() ) :
                while ( have_posts() ) :

                    the_post();
                    the_content();

                endwhile;
            endif;

            nectar_hook_after_content();

        ?>
    </div>
</div>
<?php get_footer(); ?>
