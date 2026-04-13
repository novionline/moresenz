<?php
/**
* Audio Post Format Template
*
* Used when "Featured Image Left" standard style is selected.
*
* @version 11.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $post;
global $nectar_options;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>  
  <div class="inner-wrap">
    <div class="post-content">
      <div class="article-content-wrap">
        <div class="post-featured-img-wrap">
          <?php
          // Featured image.
          get_template_part( 'includes/partials/blog/styles/standard-featured-img-left/post-image' );
          ?>
          
        </div>
        <div class="post-content-wrap">
          <a class="entire-meta-link" href="<?php the_permalink(); ?>" aria-label="<?php the_title(); ?>"></a>
          <?php

          // Output categories.
          get_template_part( 'includes/partials/blog/styles/standard-featured-img-left/post-categories' );

          ?>
          
          <div class="post-header">
            <h3 class="title"><a href="<?php the_permalink(); ?>"> <?php the_title(); ?></a></h3>
          </div>
          
          <?php

          // Excerpt.
          get_template_part( 'includes/partials/blog/styles/standard-featured-img-left/post-excerpt' );

          do_action('nectar_after_archive_post_item_content');

          // Bottom author link & date.
          get_template_part( 'includes/partials/blog/styles/standard-featured-img-left/post-bottom-meta' );

          ?>
        </div>
      </div>
    </div>
  </div>
</article>