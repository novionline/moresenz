<?php

/**
 * Default template for single post.
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 2.0.0
 * @since 2.0.0
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
  exit;
}

$nectar_options = get_nectar_theme_options();

$auto_masonry_spacing = (! empty($nectar_options['portfolio_auto_masonry_spacing'])) ? $nectar_options['portfolio_auto_masonry_spacing'] : '4px';
$page_header_class_names = 'row page-header-no-bg blog-archive-header';

$term = get_queried_object();
$heading = '';
if ($term && ! is_wp_error($term)) {
  $heading = esc_html($term->name);
}

$count = isset($term->count) ? $term->count : 0;
$category = get_queried_object();

if ( $count !== 0 ) {
?>
<div
  class="<?php echo apply_filters('nectar_archive_header_classes', $page_header_class_names); ?>"
  <?php do_action('nectar_archive_header_attrs'); ?> data-alignment="center">
  <div class="container">
    <div class="col span_12 section-title">
      <?php do_action('nectar_archive_header_before_title'); ?>
      <h1><?php echo wp_kses_post($heading); ?><span
          class="nectar-archive-tax-count nectar-font-label"><?php echo esc_html($count); ?></small>
      </h1>
      <?php do_action('nectar_archive_header_after_title'); ?>
    </div>
  </div>
</div>
<?php } ?>

<div id="nectar-content-wrap" class="container-wrap">
  <div class="container main-content">

    <?php do_action('nectar_before_blog_loop_row');

    $row_class = apply_filters('nectar_blog_row_class', 'row');

    echo '<div class="' . esc_attr($row_class) . ' nectar-archive-wrapper masonry">';
    echo '<div class="post-area col span_12 col_last masonry auto_meta_overlaid_spaced" role="main" data-ams="' . esc_attr($auto_masonry_spacing) . '">';
    echo '<div class="posts-container">';
    add_filter('wp_get_attachment_image_attributes', 'nectar_remove_lazy_load_functionality');

    do_action('nectar_before_blog_loop_start');
    do_action('nectar_before_blog_loop_content');

    // Main post loop.
    if (have_posts()):
      while (have_posts()):
        the_post();

        get_template_part('includes/partials/portfolio/item');

      endwhile;
    endif;

    do_action('nectar_after_blog_loop_content');
    do_action('nectar_before_blog_loop_end');

    echo '</div>';

    nectar_pagination(); ?>

  </div>
</div>
</div>
<?php nectar_hook_before_container_wrap_close(); ?>
</div>