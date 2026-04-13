<?php

/**
* Post Media
*
* @version 2.0.0
* @since 2.0.0
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $post;
global $nectar_options;

// Featured image.
$image_attrs = [
  'title' => '',
  'sizes' => '(min-width: 690px) 50vw, 100vw',
];

if( has_post_thumbnail() ) {
  echo '<span class="post-featured-img">';
  echo get_the_post_thumbnail( $post->ID, 'medium_featured', $image_attrs ) . '</span>';
}
else {
  echo '<span class="post-featured-img no-img"></span>';
}

// Video.
$portfolio_video = get_post_meta( $post->ID, '_nectar_portfolio_video', true );
$video_url = '';

if (isset($portfolio_video['source']['id'])) {
  $video_url = wp_get_attachment_url($portfolio_video['source']['id']);
} else {
  if (isset($portfolio_video['source']['url'])) {
    $video_url = $portfolio_video['source']['url'];
  }
}
$videoType = 'video/mp4';
if (str_ends_with($video_url, '.webm')) {
    $videoType = 'video/webm';
} elseif (str_ends_with($video_url, '.ogg')) {
    $videoType = 'video/ogg';
}
if ( ! empty( $video_url ) ) {
  echo '<span class="post-featured-img video">';
  echo '<video autoplay muted playsinline loop>';
  echo '<source src="' . esc_url( $video_url ) . '" type="' . esc_attr( $videoType ) . '">';
  echo '</video></span>';
}