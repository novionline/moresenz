<?php

global $nectar_options;

$use_excerpt = ( isset($nectar_options['blog_auto_excerpt']) && $nectar_options['blog_auto_excerpt'] === '1' ) ? true : false;
$excerpt_length = ( isset($nectar_options['blog_excerpt_length'] ) && ! empty( $nectar_options['blog_excerpt_length'] ) ) ? intval( $nectar_options['blog_excerpt_length'] ) : 15;

 if ( $use_excerpt ) {
    echo '<div class="excerpt">';
    echo nectar_excerpt( $excerpt_length );
    echo '</div>';
 }