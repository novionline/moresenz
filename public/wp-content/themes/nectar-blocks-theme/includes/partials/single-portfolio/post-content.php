<?php
/**
* Single Post Content
*
* @version 2.0.0
* @since 2.0.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $nectar_options;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
  <div class="post-content">
      <?php the_content( '<span class="continue-reading">' . esc_html__( 'Read More', 'nectar-blocks-theme' ) . '</span>' ); ?>
    </div>
</article>