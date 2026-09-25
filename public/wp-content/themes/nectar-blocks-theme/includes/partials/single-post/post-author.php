<?php

/**
 * Post author link
 *
 * @package Nectar Blocks Theme
 * @subpackage Partials
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $nectar_options;

$author_link = ( isset( $nectar_options['blog_header_author_link'] ) ) ? $nectar_options['blog_header_author_link'] : 'default';

if ( $author_link === 'default' ) {
    echo get_the_author_posts_link();
} else if ( $author_link === 'website_url' ) {
    $author_website_url = get_the_author_meta('url');
    echo '<a href="' . esc_attr($author_website_url) . '" target="_blank" rel="noopener noreferrer">' . get_the_author() . '</a>';
} else {
    echo get_the_author();
}
