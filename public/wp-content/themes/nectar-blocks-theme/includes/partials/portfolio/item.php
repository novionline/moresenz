<?php
/**
 * Project Archive Item Template
 *
 * @version 2.0.0
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;
$nectar_options = get_nectar_theme_options();

$display_categories = (isset($nectar_options['portfolio_archive_categories'])) ? $nectar_options['portfolio_archive_categories'] : '1';
$display_description = (isset($nectar_options['portfolio_archive_description'])) ? $nectar_options['portfolio_archive_description'] : '1';
$description = get_post_meta(get_the_ID(), '_nectar_portfolio_description', true);
$description_length = (isset($nectar_options['portfolio_archive_description_length'])) ? $nectar_options['portfolio_archive_description_length'] : '20';
$nectar_post_class_additions = ' masonry-blog-item';

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( $nectar_post_class_additions ); ?>>
  <div class="inner-wrap">
    <div class="post-content">
      <div class="content-inner">
        <a class="entire-meta-link" href="<?php the_permalink(); ?>" aria-label="<?php the_title(); ?>"></a>

        <?php
          // Featured image.
          get_template_part( 'includes/partials/portfolio/components/post-media' );
        ?>

        <div class="article-content-wrap">

          <?php
          if( $display_categories === '1') {
            get_template_part( 'includes/partials/portfolio/components/categories' );
          }
          ?>

          <div class="post-header">
            <h3 class="title"><a href="<?php the_permalink(); ?>"> <?php the_title(); ?></a></h3>
            <?php
            if ( $display_description === '1' ) {
              $description = get_post_meta(get_the_ID(), '_nectar_portfolio_description', true);
              if ( ! empty( $description ) ) {
                echo '<span class="nectar-post-excerpt">' . wp_kses_post( do_shortcode( wp_trim_words( $description, $description_length, '...' ) ) ) . '</span>';
              }
            }
            do_action('nectar_after_archive_post_item_content');
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>