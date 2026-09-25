<?php

/**
 * AJAX handler for header search functionality.
 *
 * @package Nectar\Ajax
 */

namespace Nectar\Ajax;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HeaderSearch
 *
 * Handles AJAX search requests for the header search block.
 */
class HeaderSearch {
    /**
     * Initialize the AJAX handlers.
     */
    public function __construct() {
        // Both logged-in and logged-out users can search.
        add_action( 'wp_ajax_nectar_header_search', [ $this, 'handle_search' ] );
        add_action( 'wp_ajax_nopriv_nectar_header_search', [ $this, 'handle_search' ] );
    }

    /**
     * Handle the AJAX search request.
     */
    public function handle_search(): void {
        // Verify nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nectar_header_search' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid security token.' ], 403 );
        }

        // Get and sanitize search query.
        $search_query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( strlen( $search_query ) < 2 ) {
            wp_send_json_success( [ 'results' => [] ] );
        }

        // Get post type filter.
        $post_type_raw = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'any';
        $show_image = isset( $_POST['show_image'] ) && $_POST['show_image'] === 'true';

        // Get all valid searchable post types (public and not excluded from search).
        $valid_post_types = get_post_types( [ 'public' => true, 'exclude_from_search' => false ] );

        // Build query args.
        $args = [
            's' => $search_query,
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'orderby' => 'relevance',
        ];

        // Handle post type - validate against allowed searchable types.
        if ( $post_type_raw !== 'any' && isset( $valid_post_types[$post_type_raw] ) ) {
            $args['post_type'] = $post_type_raw;
        } else {
            // Use all public searchable post types.
            $args['post_type'] = $valid_post_types;
        }

        $query = new \WP_Query( $args );
        $results = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();

                $result = [
                    'id' => get_the_ID(),
                    'title' => html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ),
                    'url' => get_permalink(),
                ];

                // Get excerpt.
                $excerpt = get_the_excerpt();
                if ( $excerpt ) {
                    $excerpt = wp_strip_all_tags( $excerpt );
                    $result['excerpt'] = mb_strlen( $excerpt ) > 80
                        ? mb_substr( $excerpt, 0, 80 ) . '…'
                        : $excerpt;
                }

                // Get thumbnail if requested.
                if ( $show_image && has_post_thumbnail() ) {
                    $thumbnail_id = get_post_thumbnail_id();
                    $thumbnail = wp_get_attachment_image_src( $thumbnail_id, 'thumbnail' );
                    if ( $thumbnail ) {
                        $result['thumbnail'] = $thumbnail[0];
                    }
                }

                // Get post type label for display.
                $post_type_obj = get_post_type_object( get_post_type() );
                if ( $post_type_obj ) {
                    $result['type'] = $post_type_obj->labels->singular_name;
                }

                $results[] = $result;
            }
            wp_reset_postdata();
        }

        wp_send_json_success( [ 'results' => $results ] );
    }
}

