<?php

/**
 * WPML helpers
 *
 * @package Nectar Blocks Theme
 * @subpackage helpers
 * @version 9.0.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'ICL_LANGUAGE_CODE' ) ) {

    add_filter( 'icl_ls_languages', 'nectar_wmpl_duplicate_content_fix' );
    function nectar_wmpl_duplicate_content_fix( $languages ) {
        wp_reset_query();
        return $languages;
    }

    add_filter( 'wpml_pb_shortcode_content_for_translation', 'nectar_wpml_filter_content_for_translation', 10, 2 );

    function nectar_wpml_filter_content_for_translation( $content, $post_id ) {

        if ( 'portfolio' === get_post_type( $post_id ) ) {
            $content = get_post_meta( $post_id, '_nectar_portfolio_extra_content', true );
        }
        return $content;
    }

    add_filter( 'wpml_pb_shortcodes_save_translation', 'nectar_wpml_filter_save_translation', 10, 3 );

    function nectar_wpml_filter_save_translation( $saved, $post_id, $new_content ) {

        if ( 'portfolio' === get_post_type( $post_id ) ) {
            update_post_meta( $post_id, '_nectar_portfolio_extra_content', $new_content );
            $saved = true;
        }
        return $saved;
    }

    /**
     * Customizer theme-mod string keys that hold user-facing text / URLs
     * and should be exposed to WPML / Polylang String Translation.
     */
    function nectar_translatable_theme_mod_keys() {
        return [
            'header-text-widget',
            'secondary-header-text',
            'secondary-header-link',
            'footer-copyright-text',
            'header-search-ph-text',
            'header-slide-out-widget-area-bottom-text',
        ];
    }

    add_action( 'admin_init', 'nectar_register_translatable_theme_mods' );
    add_action( 'customize_save_after', 'nectar_register_translatable_theme_mods' );
    function nectar_register_translatable_theme_mods() {
        // Read raw stored values directly to bypass the theme_mod_<key>
        // translation filter — we must register the SOURCE string, not a
        // value that has already been translated for the current language.
        $mods = get_option( 'theme_mods_' . get_stylesheet() );
        if ( ! is_array( $mods ) ) {
            return;
        }
        $values = [];
        foreach ( nectar_translatable_theme_mod_keys() as $key ) {
            if ( isset( $mods[$key] ) && is_string( $mods[$key] ) && '' !== $mods[$key] ) {
                $values[$key] = $mods[$key];
            }
        }
        // WPML's wpml_register_single_string action writes to icl_strings on
        // every call. Without this gate the theme would hit the database for
        // these keys on every admin page load. customize_save_after picks up
        // value changes naturally because the snapshot hash will differ.
        $hash = md5( wp_json_encode( $values ) );
        if ( get_transient( 'nectar_wpml_strings_hash' ) === $hash ) {
            return;
        }
        foreach ( $values as $key => $value ) {
            do_action( 'wpml_register_single_string', 'Nectar Options', $key, $value );
        }
        set_transient( 'nectar_wpml_strings_hash', $hash, DAY_IN_SECONDS );
    }

    add_action( 'after_setup_theme', 'nectar_add_theme_mod_translation_filters' );
    function nectar_add_theme_mod_translation_filters() {
        foreach ( nectar_translatable_theme_mod_keys() as $key ) {
            add_filter( "theme_mod_{$key}", 'nectar_translate_theme_mod_value', 10, 1 );
        }
    }

    function nectar_translate_theme_mod_value( $value ) {
        if ( ! is_string( $value ) || '' === $value ) {
            return $value;
        }
        $filter_name = current_filter();
        $key = substr( $filter_name, strlen( 'theme_mod_' ) );
        return apply_filters( 'wpml_translate_single_string', $value, 'Nectar Options', $key );
    }
}
