<?php
/**
* Single Post Content
*
* @version 13.1
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $nectar_options;

$nectar_post_format = get_post_format();
$hide_featrued_image = ( ! empty( $nectar_options['blog_hide_featured_image'] ) ) ? $nectar_options['blog_hide_featured_image'] : '0';
$blog_post_type_list = ['post'];
if( has_filter('nectar_metabox_post_types_post_header') ) {
    $blog_post_type_list = apply_filters('nectar_metabox_post_types_post_header', $blog_post_type_list);
}
$is_blog_header_post_type = ( isset($post->post_type) && in_array($post->post_type, $blog_post_type_list) && is_single()) ? true : false;
$single_post_header_inherit_fi = ( ! empty( $nectar_options['blog_post_header_inherit_featured_image'] ) && $is_blog_header_post_type ) ? $nectar_options['blog_post_header_inherit_featured_image'] : '0';
$blog_header_type = ( ! empty( $nectar_options['blog_header_type'] ) ) ? $nectar_options['blog_header_type'] : 'default';
$blog_social_style = ( get_option( 'salient_social_button_style' ) ) ? get_option( 'salient_social_button_style' ) : 'fixed';

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
  
  <div class="post-content" data-hide-featured-media="<?php echo esc_attr( $hide_featrued_image ); ?>">
    
      <?php

      if( function_exists('nectar_social_sharing_output') && 'default' == $blog_social_style && 'image_under' === $blog_header_type) {
        nectar_social_sharing_output('vertical');
        echo '<div class="post-content__inner">';
      }

      if( '1' !== $hide_featrued_image && 'image_under' !== $blog_header_type ) {

        // Featured Image.
        if( null === $nectar_post_format || false === $nectar_post_format || 'image' === $nectar_post_format) {
          if ( has_post_thumbnail() && '1' !== $single_post_header_inherit_fi ) {
            echo '<span class="post-featured-img">' . get_the_post_thumbnail( $post->ID, 'full', [ 'title' => '' ] ) . '</span>';
          }
        }

        // Video.
        else if( 'video' === $nectar_post_format ) {
          get_template_part( 'includes/partials/blog/media/video-player' );
        }
        // Audio.
        else if( 'audio' === $nectar_post_format ) {
          get_template_part( 'includes/partials/blog/media/audio-player' );
        }

      }

      // Quote.
      if( 'quote' === $nectar_post_format ) {
        get_template_part( 'includes/partials/blog/media/quote' );
      }

      // Link.
      else if( 'link' === $nectar_post_format ) {
        get_template_part( 'includes/partials/blog/media/link' );
      }

      // Post content.
      if( 'link' !== $nectar_post_format ) {
        the_content( '<span class="continue-reading">' . esc_html__( 'Read More', 'nectar-blocks-theme' ) . '</span>' );
      }

      // Tags.
      if ( '1' === $nectar_options['display_tags'] && has_tag() ) {
        echo '<div class="nectar-post-tags">';
        the_tags( '', '', '' );
        echo '</div> ';
      }

      if( function_exists('nectar_social_sharing_output') && 'default' == $blog_social_style && 'image_under' === $blog_header_type) {
        echo '</div>'; //  class="post-content__inner"
      }

      ?>
      
    </div><!--/post-content-->
    
</article>