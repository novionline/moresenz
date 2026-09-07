<?php

/**
 * Ignores leftover Salient page metabox settings.
 *
 * Sites that previously ran the Salient theme can carry page header,
 * transparent header, and full screen row post meta that has no UI in
 * Nectar Blocks. Short-circuit those keyed reads so stale values can't
 * alter the header or page layout.
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'nectar_ignore_salient_post_meta' ) ) {

    function nectar_ignore_salient_post_meta( $value, $object_id, $meta_key, $single ) {

        static $salient_meta_keys = [
            '_force_transparent_header' => true,
            '_disable_transparent_header' => true,
            '_force_transparent_header_color' => true,
            '_nectar_header_title' => true,
            '_nectar_header_subtitle' => true,
            '_nectar_header_bg' => true,
            '_nectar_header_bg_color' => true,
            '_nectar_header_bg_height' => true,
            '_nectar_header_bg_overlay_color' => true,
            '_nectar_header_bg_overlay_opacity' => true,
            '_nectar_header_font_color' => true,
            '_nectar_header_parallax' => true,
            '_nectar_header_overlay' => true,
            '_nectar_header_bottom_shadow' => true,
            '_nectar_header_box_roll' => true,
            '_nectar_header_fullscreen' => true,
            '_nectar_page_header_alignment' => true,
            '_nectar_page_header_alignment_v' => true,
            '_nectar_page_header_bg_alignment' => true,
            '_nectar_page_header_text-effect' => true,
            '_nectar_slider_bg_type' => true,
            '_nectar_canvas_shapes' => true,
            '_nectar_particle_rotation_timing' => true,
            '_nectar_particle_disable_explosion' => true,
            '_nectar_media_upload_webm' => true,
            '_nectar_media_upload_mp4' => true,
            '_nectar_media_upload_ogv' => true,
            '_nectar_full_screen_rows' => true,
            '_nectar_full_screen_rows_animation' => true,
            '_nectar_full_screen_rows_animation_speed' => true,
            '_nectar_full_screen_rows_anchors' => true,
            '_nectar_full_screen_rows_dot_navigation' => true,
            '_nectar_full_screen_rows_footer' => true,
            '_nectar_full_screen_rows_content_overflow' => true,
            '_nectar_full_screen_rows_row_bg_animation' => true,
            '_nectar_full_screen_rows_mobile_disable' => true,
            '_nectar_full_screen_rows_overall_bg_color' => true,
        ];

        // Bulk reads arrive with an empty key and are left to core; only named-key reads are blocked.
        if ( '' === $meta_key ) {
            return $value;
        }

        if ( isset( $salient_meta_keys[$meta_key] ) ) {

            // Slide posts are exempt for every key, not just _nectar_slider_bg_type: the
            // CPT belongs to the Nectar Slider plugin and the theme can't see which of
            // these it reads per-slide, so don't narrow this.
            if ( in_array( get_post_type( $object_id ), [ 'nectar_slider', 'home_slider' ], true ) ) {
                return $value;
            }

            /**
             * Whether to ignore a leftover Salient metabox value. Return false to let a
             * site read its legacy value again, per key and per post.
             *
             * @since 3.3.0
             */
            if ( ! apply_filters( 'nectar_ignore_salient_meta', true, $meta_key, $object_id ) ) {
                return $value;
            }

            return $single ? '' : [];
        }

        return $value;

    }
}
add_filter( 'get_post_metadata', 'nectar_ignore_salient_post_meta', 10, 4 );
