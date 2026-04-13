<?php

/**
 * Media related and image size helper functions
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 9.0.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add theme specific image sizes that are used
 *
 * @since 8.0
 */

if ( ! function_exists( 'nectar_add_image_sizes' ) ) {

    function nectar_add_image_sizes() {

        global $nectar_options;

        add_image_size( 'nectar_small_square', 200, 200, true );
        add_image_size( 'nectar_4_3_aspect_medium', 800, 600, true );

    }
}

add_action( 'after_setup_theme', 'nectar_add_image_sizes' );

/**
 * List the available image sizes
 *
 * @since 8.0
 */
 function nectar_list_thumbnail_sizes() {
     global $_wp_additional_image_sizes;
     $sizes = [];
     foreach ( get_intermediate_image_sizes() as $s ) {
         $sizes[$s] = [ 0, 0 ];
         if ( in_array( $s, [ 'thumbnail', 'medium', 'large' ] ) ) {
             $sizes[$s][0] = get_option( $s . '_size_w' );
             $sizes[$s][1] = get_option( $s . '_size_h' );
         } else {
             if ( isset( $_wp_additional_image_sizes ) && isset( $_wp_additional_image_sizes[$s] ) ) {
                 $sizes[$s] = [ $_wp_additional_image_sizes[$s]['width'], $_wp_additional_image_sizes[$s]['height'] ];
             }
         }
     }

     foreach ( $sizes as $size => $atts ) {
         echo esc_html( $size ) . ' ' . implode( 'x', $atts ) . "\n";
     }
 }

/**
 * Auto lightbox image links theme option.
 *
 * @since 5.0
 */
if ( ! function_exists( 'nectar_auto_gallery_lightbox' ) ) {
    function nectar_auto_gallery_lightbox( $content ) {

        preg_match_all( '/<a(.*?)href=(?:\'|")([^<]*?).(bmp|gif|jpeg|jpg|png)(?:\'|")(.*?)>/i', $content, $links );
        if ( isset( $links[0] ) ) {
            $rel_hash = '[gallery-' . wp_generate_password( 4, false, false ) . ']';

            foreach ( $links[0] as $id => $link ) {

                if ( preg_match( '/<a.*?rel=(?:\'|")(.*?)(?:\'|").*?>/', $link, $result ) === 1 ) {
                    $content = str_replace( $link, preg_replace( '/rel=(?:\'|")(.*?)(?:\'|")/', 'rel="prettyPhoto' . $rel_hash . '"', $link ), $content );
                } else {
                    $content = str_replace( $link, '<a' . $links[1][$id] . 'href="' . $links[2][$id] . '.' . $links[3][$id] . '"' . $links[4][$id] . ' rel="prettyPhoto' . $rel_hash . '">', $content );
                }
            }
        }

        return $content;

    }
}

 global $nectar_options;

if ( ! empty( $nectar_options['default-lightbox'] ) && $nectar_options['default-lightbox'] === '1' ) {
    add_filter( 'the_content', 'nectar_auto_gallery_lightbox' );

    add_filter( 'body_class', 'nectar_auto_gallery_lightbox_class' );
    function nectar_auto_gallery_lightbox_class( $classes ) {
        // add 'class-name' to the $classes array
        $classes[] = 'nectar-auto-lightbox';
        // return the $classes array
        return $classes;
    }
}

 /**
  * Get attachment ID from a given image URL.
  *
  * @since 5.0
  */
if ( ! function_exists( 'fjarrett_get_attachment_id_from_url' ) ) {
    function fjarrett_get_attachment_id_from_url( $url ) {

        // Split the $url into two parts with the wp-content directory as the separator.
        $parse_url = explode( parse_url( WP_CONTENT_URL, PHP_URL_PATH ), $url );

        // Get the host of the current site and the host of the $url, ignoring www.
        $home_host = parse_url( esc_url( home_url() ), PHP_URL_HOST );
        $url_host = parse_url( $url, PHP_URL_HOST );

        $this_host = str_ireplace( 'www.', '', (string) $home_host );
        $file_host = str_ireplace( 'www.', '', (string) $url_host );

        // Return nothing if there aren't any $url parts or if the current host and $url host do not match.
        if ( ! isset( $parse_url[1] ) || empty( $parse_url[1] ) || ( $this_host != $file_host ) ) {
            return;
        }

        // Now we're going to quickly search the DB for any attachment GUID with a partial path match.
        global $wpdb;

        $prefix = $wpdb->prefix;
        $attachment = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM ' . $prefix . 'posts WHERE guid RLIKE %s;', $parse_url[1] ) );

        return ( ! empty( $attachment ) ) ? $attachment[0] : null;
    }
}

/**
 * Returns a lightbox ready URL from youtube/vimeo embed
 *
 * @since 12.2
 */
if ( ! function_exists( 'nectar_extract_video_lightbox_link' ) ) {

 function nectar_extract_video_lightbox_link( $post, $video_embed, $video_mp4 ) {

     global $nectar_options;

     $project_video_src = null;
     $project_video_link = null;
     $using_fancybox = ( isset($nectar_options['lightbox_script']) && $nectar_options['lightbox_script'] === 'fancybox') ? true : false;

     if ( $video_embed ) {

         $project_video_src = $video_embed;

         if ( preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $project_video_src, $video_match ) ) {

             // youtube
             // handle query params.
             $query_args = '';

             // iframe src.
             if(strpos($project_video_src, '<iframe') !== false && $using_fancybox === true ) {
                 preg_match('/src="([^"]+)"/', $project_video_src, $iframe_src_match);
                 $iframe_src = $iframe_src_match[1];

                 $parsed_iframe_src = parse_url($iframe_src);

                 if( isset($parsed_iframe_src['query']) && $parsed_iframe_src['query'] !== null ) {
                     $query_args = '&' . $parsed_iframe_src['query'];
                 }
             }

             $project_video_link = 'https://www.youtube.com/watch?v=' . $video_match[1] . $query_args;

         } elseif ( preg_match( '/player\.vimeo\.com\/video\/([0-9]*)/', $project_video_src, $video_match ) ) {

             // vimeo iframe
             $project_video_link = 'https://vimeo.com/' . $video_match[1] . '?iframe=true';

         } elseif ( preg_match( '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([‌​0-9]{6,11})[?]?.*/', $project_video_src, $video_match ) ) {

             // reg vimeo
             $project_video_link = 'https://vimeo.com/' . $video_match[5] . '?iframe=true';

         }
     } elseif ( $video_mp4 ) {

         $project_video_link = $video_mp4;

     }

     return esc_url($project_video_link);

 }

}

/**
 * Get attachment src from a given image URL.
 *
 * @since 4.0
 */
if ( ! function_exists( 'nectar_options_img' ) ) {

    function nectar_options_img( $image_arr_or_str ) {

        // dummy data import from external
        // TODO: Just leave this as is for
        if ( isset( $image_arr_or_str['thumbnail'] ) && strpos( $image_arr_or_str['thumbnail'], 'http://themenectar.com' ) !== false && strpos( get_site_url(), 'themenectar.com' ) === false ) {
            return $image_arr_or_str['thumbnail'];
        }
        if ( isset( $image_arr_or_str['thumbnail'] ) && strpos( $image_arr_or_str['thumbnail'], 'https://source.unsplash.com' ) !== false ) {
            return $image_arr_or_str['thumbnail'];
        }

        // check if URL or ID is passed
        if ( isset( $image_arr_or_str['id'] ) ) {

            $image_id = apply_filters('wpml_object_id', $image_arr_or_str['id'], 'attachment', TRUE);
            $image = wp_get_attachment_image_src( $image_id, 'full' );

            if( isset($image[0]) ) {
                return $image[0];
            } else {
                return '';
            }

        }
        elseif ( isset( $image_arr_or_str['url'] ) ) {
            return $image_arr_or_str['url'];
        }
        else {

            $image_id = fjarrett_get_attachment_id_from_url( $image_arr_or_str );

            if ( ! is_null( $image_id ) && ! empty( $image_id ) ) {

                $image_id = apply_filters('wpml_object_id', $image_id, 'attachment', TRUE);

                $image = wp_get_attachment_image_src( $image_id, 'full' );
                return $image[0];
            } else {
                return $image_arr_or_str;
            }
        }
    }
}

/**
 * Attempts to locate video ID based on URL and grab the video source
 * through wp_get_attachment_url to allow CDNs to swap the source.
 *
 * @since 12.2.0
 */
 if( ! function_exists('nectar_video_src_from_wp_attachment') ) {

     function nectar_video_src_from_wp_attachment( $url ) {

         if( function_exists('attachment_url_to_postid') && ! empty($url) ) {

             $video_id = attachment_url_to_postid($url);

             // The ID has been found.
             if( 0 !== $video_id ) {

                 $video_source = wp_get_attachment_url($video_id);

                 // An Attachment URL has been found.
                 if( $video_source ) {
                     return $video_source;
                 }

             }

         }

         // Default.
         return $url;

     }

 }

