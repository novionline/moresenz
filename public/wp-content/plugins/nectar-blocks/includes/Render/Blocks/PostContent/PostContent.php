<?php

namespace Nectar\Render\Blocks\PostContent;

/**
 * PostContent Rendering
 * @version 2.0.0
 * @since 2.0.0
 */
class PostContent {
  private $block_attributes;

  function __construct($block_attributes, $content) {
    $this->block_attributes = $block_attributes;
  }

  function render() {
    $rest_context = defined( 'REST_REQUEST' ) && REST_REQUEST;

    // If the block is being rendered in the editor, or if we're previewing a template, return the placeholder.
    if ( $rest_context || is_admin() || get_post_type() === 'nectar_sections' ) {
      return '<p>' . __('This is the Content block, it will display all the blocks in any single post or page.', 'nectar-blocks') . '</p>' .
       '<p>' . __('That might be a simple arrangement like consecutive paragraphs in a blog post, or a more elaborate composition that includes image galleries, videos, tables, columns, and any other block types.', 'nectar-blocks') . '</p>' .
       '<p>' . __('If there are any Custom Post Types registered at your site, the Content block can display the contents of those entries as well.', 'nectar-blocks') . '</p>';
    } else if ( ! is_single() ) {
      return '';
    }

    ob_start();
    the_content();
    return ob_get_clean();
  }
}