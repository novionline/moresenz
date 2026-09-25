<?php

/**
 * Custom post type single-page helpers.
 *
 * Shared by the per-CPT customizer panel and the front-end post navigation so
 * the option keys and post-type list stay in sync between admin and front end.
 *
 * @package Nectar Blocks Theme
 * @subpackage Helpers
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'nectar_get_single_option_post_types' ) ) {
    /**
     * Public custom post types that receive single-page options.
     *
     * Mirrors the theme builder's available-post-type gating (the
     * `nectar_available_post_types` transient, populated on `wp_loaded`) so the
     * list matches Nectar Templates and is available even though the customizer
     * registers panels on `after_setup_theme` — before CPTs register on `init`.
     * Additionally drops the post types that already have their own panel.
     *
     * Returns post type objects (keyed by slug) rather than names because the
     * customizer registers before `init` — at which point get_post_type_object()
     * is empty but the cached objects still carry their labels.
     *
     * @return array<string,object> Post type objects keyed by slug.
     */
    function nectar_get_single_option_post_types() {

        // Types with their own panel (post = Blog, portfolio) or not single-nav
        // candidates. Aligns with NectarThemeManager::set_available_post_types().
        $excluded = apply_filters( 'nectar_cpt_options_excluded_types', [
            'post',
            'page',
            'portfolio',
            'product',
            'attachment',
            'elementor_library',
            'salient_g_sections',
            'nectar_sections',
            'nectar_templates',
            'nectar_slider',
            'home_slider',
            'wp_block',
        ] );

        // Cross-request cache from the theme manager; fall back to a live query
        // for callers that run after `init`.
        $available = get_transient( 'nectar_available_post_types' );
        if ( ! is_array( $available ) || empty( $available ) ) {
            $available = get_post_types( [ 'public' => true ], 'objects' );
        }

        $types = [];
        foreach ( $available as $post_type ) {
            $object = is_object( $post_type ) ? $post_type : get_post_type_object( $post_type );
            if ( null === $object || in_array( $object->name, $excluded, true ) ) {
                continue;
            }
            $types[$object->name] = $object;
        }

        return apply_filters( 'nectar_single_option_post_types', $types );
    }
}

if ( ! function_exists( 'nectar_cpt_option_prefix' ) ) {
    /**
     * Option-key prefix for a custom post type's single options.
     *
     * @param string $post_type Post type slug.
     * @return string e.g. "cpt_events_".
     */
    function nectar_cpt_option_prefix( $post_type ) {
        return 'cpt_' . $post_type . '_';
    }
}

if ( ! function_exists( 'nectar_cpt_primary_taxonomy' ) ) {
    /**
     * The taxonomy used for "same term" post navigation on a CPT.
     *
     * Prefers a public hierarchical taxonomy, falling back to any public one.
     *
     * @param string $post_type Post type slug.
     * @return string Taxonomy slug, or '' when the CPT has none.
     */
    function nectar_cpt_primary_taxonomy( $post_type ) {
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        $public = array_filter( $taxonomies, function ( $tax ) {
            return ! empty( $tax->public );
        } );

        foreach ( $public as $tax ) {
            if ( $tax->hierarchical ) {
                return $tax->name;
            }
        }
        foreach ( $public as $tax ) {
            return $tax->name;
        }

        return '';
    }
}

if ( ! function_exists( 'nectar_single_post_nav_settings' ) ) {
    /**
     * Resolves post navigation settings for the current single, reading the blog
     * options for blog post types and the per-CPT options for everything else.
     *
     * @return array{enabled:bool,style:string,order:string,limit:bool,taxonomy:string}
     */
    function nectar_single_post_nav_settings() {
        global $nectar_options;

        $post_type = get_post_type();
        $blog_types = apply_filters( 'nectar_blog_single_post_types', [ 'post' ] );

        if ( in_array( $post_type, $blog_types, true ) ) {
            // 'post' uses categories; a CPT routed through the blog template via the
            // filter falls back to its primary taxonomy (and disables the limit when
            // it has none) so adjacent-post lookups don't silently return nothing.
            $taxonomy = ( 'post' === $post_type ) ? 'category' : nectar_cpt_primary_taxonomy( $post_type );

            return [
                'enabled' => ( isset( $nectar_options['blog_next_post_link'] ) && '1' === $nectar_options['blog_next_post_link'] ),
                'style' => ( ! empty( $nectar_options['blog_next_post_link_style'] ) ) ? $nectar_options['blog_next_post_link_style'] : 'fullwidth_next_only',
                'order' => ( ! empty( $nectar_options['blog_next_post_link_order'] ) ) ? $nectar_options['blog_next_post_link_order'] : 'default',
                'limit' => ( '' !== $taxonomy && isset( $nectar_options['blog_next_post_limit_cat'] ) && '1' === $nectar_options['blog_next_post_limit_cat'] ),
                'taxonomy' => ( '' !== $taxonomy ) ? $taxonomy : 'category',
            ];
        }

        $prefix = nectar_cpt_option_prefix( $post_type );
        $taxonomy = nectar_cpt_primary_taxonomy( $post_type );

        return [
            'enabled' => ( isset( $nectar_options[$prefix . 'next_post_link'] ) && '1' === $nectar_options[$prefix . 'next_post_link'] ),
            'style' => ( ! empty( $nectar_options[$prefix . 'next_post_link_style'] ) ) ? $nectar_options[$prefix . 'next_post_link_style'] : 'fullwidth_next_only',
            'order' => ( ! empty( $nectar_options[$prefix . 'next_post_link_order'] ) ) ? $nectar_options[$prefix . 'next_post_link_order'] : 'default',
            'limit' => ( '' !== $taxonomy && isset( $nectar_options[$prefix . 'next_post_limit_cat'] ) && '1' === $nectar_options[$prefix . 'next_post_limit_cat'] ),
            'taxonomy' => ( '' !== $taxonomy ) ? $taxonomy : 'category',
        ];
    }
}
