<?php

/**
* Post categories partial
*
* Used when "Featured Image Left" standard style is selected.
*
* @version 10.5
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $post;
global $nectar_options;

$use_categories = ( isset($nectar_options['blog_remove_post_categories']) && '1' !== $nectar_options['blog_remove_post_categories'] ) ? true : false;

if ( $use_categories === false ) {
  return;
}

echo '<span class="meta-category">';

$categories = get_the_category();

if ( ! empty( $categories ) ) {
  $output = null;
  foreach ( $categories as $category ) {
    $output .= '<a class="' . esc_attr( $category->slug ) . '" href="' . esc_url( get_category_link( $category->term_id ) ) . '">' . esc_html( $category->name ) . '</a>';
  }
  echo trim( $output ); // WPCS: XSS ok.
}

echo '</span>';
