<?php

/**
 * Default template for single post.
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 1.0
 * @since 2.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nectar_options = get_nectar_theme_options();

// Pagination/Related Posts.
nectar_next_post_display();
nectar_related_post_display();

if ( isset( $nectar_options['author_bio'] ) && $nectar_options['author_bio'] === '1' ) {
    $author_bio = 'true';
} else {
    $author_bio = 'false';
}
?>
<div class="nectar-blocks__post-section">
    <div class="comments-section" data-author-bio="<?php echo esc_attr($author_bio); ?>">
        <?php comments_template(); ?>
    </div>
</div>