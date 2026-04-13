<?php
/**
 * Post header featured media under title
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 2.0.0
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// Check if post title is disabled via nectar blocks
$post_title_vis = get_post_meta( $post->ID, '_nectar_blocks_hide_post_title', true );
if ( $post_title_vis === '1' && defined('NECTAR_BLOCKS_ROOT_DIR_PATH') ) {
  return;
}

global $nectar_options;

$display_categories = (isset($nectar_options['portfolio_single_project_categories'])) ? $nectar_options['portfolio_single_project_categories'] : '1';
$display_description = (isset($nectar_options['portfolio_single_project_description'])) ? $nectar_options['portfolio_single_project_description'] : '1';
$load_animation = (isset($nectar_options['portfolio_header_load_in_animation']) ) ? $nectar_options['portfolio_header_load_in_animation'] : 'none';
$parallax_bg = (isset($nectar_options['portfolio_header_scroll_effect']) && 'parallax' === $nectar_options['portfolio_header_scroll_effect'] ) ? true : false;
?>

<div class="hentry featured-media-under-header" data-animate="<?php echo esc_attr($load_animation); ?>">
  <div class="featured-media-under-header__content">

  <?php
    $above_title_content = '';

    if ($display_categories === '1') {
      $above_title_content .= '<div class="featured-media-under-header__cat-wrap">';
      $above_title_content .= get_template_part('includes/partials/single-portfolio/post-categories');
      $above_title_content .= '</div>';
    }

    $above_title_content .= '<h1 class="entry-title">' . esc_html(get_the_title()) . '</h1>';

    $nectar_portfolio_desc = get_post_meta(get_the_ID(), '_nectar_portfolio_description', true);
    if ($display_description === '1' && ! empty($nectar_portfolio_desc)) {
      $above_title_content .= '<div class="featured-media-under-header__excerpt">' . $nectar_portfolio_desc . '</div>';
    }

    echo wp_kses_post( $above_title_content );

    ?>

    <div class="featured-media-under-header__meta-wrap nectar-link-underline-effect">
    <?php get_template_part('includes/partials/single-portfolio/post-meta-fields'); ?>
    </div>

    <?php
    $parallax_attrs = '';
    $bg_classname = ['post-featured-img','page-header-bg-image'];

    if( $parallax_bg  ) {
      $parallax_attrs = 'data-n-parallax-bg="true" data-parallax-speed="subtle"';
      $bg_classname[] = 'parallax-layer';
    }

    $custom_bg = apply_filters('nectar_page_header_bg_val', get_post_meta($post->ID, '_nectar_header_bg', true));
    $has_image = (empty($custom_bg) && ! has_post_thumbnail($post->ID)) ? 'false' : 'true';

    ?>
  </div>
  <div class="featured-media-under-header__featured-media"<?php echo ' ' . $parallax_attrs; ?> data-has-img="<?php echo esc_attr($has_image); ?>" data-align="" data-format="default">
  <?php
    $selector = '.featured-media-under-header__featured-media .post-featured-img';

    if( ! empty($custom_bg) ) {
      echo nectar_responsive_page_header_css($custom_bg, $selector);

      $custom_bg_id = attachment_url_to_postid($custom_bg);
      $img_meta = wp_get_attachment_metadata($custom_bg_id);
      $width = ( ! empty($img_meta['width']) ) ? $img_meta['width'] : '1000';
      $height = ( ! empty($img_meta['height']) ) ? $img_meta['height'] : '600';
      $custom_bg_image_markup = '<img src="' . esc_attr($custom_bg) . '" alt="' . get_the_title() . '" width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" />';

      echo '<span class="' . esc_attr(implode(' ', $bg_classname)) . '">' . $custom_bg_image_markup . '</span>';
    }
    else {
      $image_id = get_post_thumbnail_id($post->ID);
      if( $image_id ) {
        echo nectar_responsive_page_header_css($image_id, $selector);
        echo '<span class="' . esc_attr(implode(' ', $bg_classname)) . '">' . get_the_post_thumbnail($post->ID, 'full') . '</span>';
      }
    }
  ?>
  </div>
  <?php do_action('nectar_after_single_post_featured_media'); ?>
</div>