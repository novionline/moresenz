<?php
/**
* The template for template parts.
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
    // Not validated against that list directly: it is built by the *plugin*
    // (Nectar\Nectar_Templates), and this template has to keep working with the
    // plugin inactive. Each branch below re-validates instead — post_type_exists()
    // and get_post_type_archive_link() reject anything unregistered, and every
    // redirect goes through wp_safe_redirect(), so the target stays on this site.
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

// OCM template preview: apply OCM background/text color to the page.
$nectar_ocm_preview_style = '';
if ( isset( $location ) && 'nectar_template__ocm' === $location && function_exists( 'get_nectar_theme_options' ) ) {
    $opts = get_nectar_theme_options();
    $ocm_bg = $opts['header-slide-out-widget-area-background-color'] ?? '';
    $ocm_text = $opts['header-slide-out-widget-area-color'] ?? '';
    $color_parts = [];
    if ( ! empty( $ocm_bg ) ) {
        $color_parts[] = 'background-color:' . esc_attr( $ocm_bg );
    }
    if ( ! empty( $ocm_text ) ) {
        $color_parts[] = 'color:' . esc_attr( $ocm_text );
    }
    $nectar_ocm_color_style = ! empty( $color_parts ) ? ' style="' . implode( ';', $color_parts ) . '"' : '';
    $nectar_ocm_height_style = ' style="height:100vh"';
}

?>
<div id="nectar-content-wrap" class="container-wrap"<?php echo $nectar_ocm_color_style ?? ''; ?>>
    <div class="container main-content"<?php echo $nectar_ocm_height_style ?? ''; ?>>
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
