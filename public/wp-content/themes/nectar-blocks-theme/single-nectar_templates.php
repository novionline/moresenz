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
    // Sanitize the templatePart meta value before using it for routing decisions.
    // Valid values follow the pattern nectar_template_single__{post_type},
    // nectar_template_archive__{post_type}, nectar_template__404, or
    // nectar_template__ocm (see Nectar_Templates::get_template_parts()).
    // TODO: tighten templatePart allowlist by validating against the registered
    // list returned by Nectar_Templates::get_template_parts().
    $location = sanitize_key( $nectar_template_meta['templatePart'] );

    $location_parts = explode( '__', $location );
    $located_template = isset( $location_parts[1] ) ? sanitize_key( $location_parts[1] ) : '';

    if ( '' !== $location && strpos( $location, 'archive' ) !== false ) {
      $archive_link = get_post_type_archive_link( $located_template );
      if ( $archive_link ) {
        wp_safe_redirect( $archive_link );
        exit;
      }
    } else if ( '' !== $location && strpos( $location, 'single' ) !== false ) {
      // get the permalink for the first post found in the cpt located_template
      if ( '' !== $located_template && post_type_exists( $located_template ) ) {
        $args = [
          'post_type' => $located_template,
          'posts_per_page' => 1,
        ];
        $query = new WP_Query( $args );
        // Guard against empty results to avoid a null dereference on PHP 8.
        if ( ! empty( $query->posts ) ) {
          $single_link = get_permalink( $query->posts[0]->ID );
          if ( $single_link ) {
            wp_safe_redirect( $single_link );
            exit;
          }
        }
      }
    } else if ( '' !== $location && strpos( $location, '404' ) !== false ) {
      wp_safe_redirect( home_url( '/404-template' ) );
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
