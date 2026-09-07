<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Singleton;

/**
 * Class AdminScripts
 * @package NoviOnline
 */
class AdminScripts extends Singleton {

    /**
     * AdminScripts constructor.
     */
    protected function __construct() {
        if (is_admin()) {
            add_action('enqueue_block_editor_assets', [$this, 'initGutenbergScripts']);
        }
    }

    /**
     * Init block editor scripts
     */
    public static function initGutenbergScripts() {
        $fixGutenbergDuplicateIdsJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'fix-gutenberg-duplicate-ids.js');
        if ($fixGutenbergDuplicateIdsJs) {
            wp_enqueue_script(Theme::TEXT_DOMAIN . '_fix_gutenberg_duplicate_ids', $fixGutenbergDuplicateIdsJs, [], false, true);
        }

        //mark the Gravity Forms block as iframe-compatible (api version 3)
        self::forceIframeCompatibleThirdPartyBlocks();

        $carouselMouseFollowerEditorJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'carousel-mouse-follower-editor.js');
        if (!$carouselMouseFollowerEditorJs) {
            $carouselMouseFollowerEditorJs = get_stylesheet_directory_uri() . '/js/chunk/carousel-mouse-follower-editor.js';
        }
        if ($carouselMouseFollowerEditorJs) {
            wp_enqueue_script(
                Theme::TEXT_DOMAIN . '_carousel_mouse_follower_editor',
                $carouselMouseFollowerEditorJs,
                ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-hooks', 'wp-compose', 'wp-i18n'],
                false,
                true
            );
        }

        $accordionFaqStructuredDataEditorJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'accordion-faq-structured-data-editor.js');
        if (!$accordionFaqStructuredDataEditorJs) {
            $accordionFaqStructuredDataEditorJs = get_stylesheet_directory_uri() . '/js/chunk/accordion-faq-structured-data-editor.js';
        }
        if ($accordionFaqStructuredDataEditorJs) {
            wp_enqueue_script(
                Theme::TEXT_DOMAIN . '_accordion_faq_structured_data_editor',
                $accordionFaqStructuredDataEditorJs,
                ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-hooks', 'wp-compose', 'wp-i18n'],
                false,
                true
            );
        }

        $imagePriorityEditorJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'image-priority-editor.js');
        if (!$imagePriorityEditorJs) {
            $imagePriorityEditorJs = get_stylesheet_directory_uri() . '/js/chunk/image-priority-editor.js';
        }
        if ($imagePriorityEditorJs) {
            wp_enqueue_script(
                Theme::TEXT_DOMAIN . '_image_priority_editor',
                $imagePriorityEditorJs,
                ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-hooks', 'wp-compose', 'wp-i18n'],
                false,
                true
            );
        }
    }

    /**
     * Mark third party blocks that are still registered with Block API version 2 as
     * iframe-compatible (version 3).
     *
     * WordPress only keeps the editor canvas inside the iframe while every block in the
     * content is api version 3. Inserting a version 2 block (e.g. gravityforms/form, often
     * nested inside a synced pattern) flips the editor out of the iframe and tears the iframe
     * document down. Core then reads the previously selected block element from that destroyed
     * document and calls blockView.ResizeObserver without a null guard, which throws
     * "Cannot read properties of null (reading 'ResizeObserver')" and breaks the editor.
     *
     * The Gravity Forms block already uses useBlockProps and enqueues its styles via
     * wp_enqueue_block_style (which load inside the iframe), so it is safe to flag as version 3.
     *
     * The filter is attached to wp-blocks so it is guaranteed to run before the block is
     * registered by the Gravity Forms editor bundle.
     *
     * @return void
     */
    public static function forceIframeCompatibleThirdPartyBlocks(): void {
        if (!wp_script_is('wp-blocks', 'registered') && !wp_script_is('wp-blocks', 'enqueued')) {
            return;
        }

        $blockNames = apply_filters('novi_iframe_compatible_block_names', ['gravityforms/form']);
        if (empty($blockNames)) {
            return;
        }

        $js = sprintf(
            '( function ( wp ) {
    if ( ! wp || ! wp.hooks || ! wp.hooks.addFilter ) { return; }
    var noviIframeBlocks = %s;
    wp.hooks.addFilter(
        "blocks.registerBlockType",
        "novionline/iframe-compatible-blocks",
        function ( settings, name ) {
            if ( noviIframeBlocks.indexOf( name ) !== -1 && ( ! settings.apiVersion || settings.apiVersion < 3 ) ) {
                return Object.assign( {}, settings, { apiVersion: 3 } );
            }
            return settings;
        }
    );
} )( window.wp );',
            wp_json_encode(array_values($blockNames))
        );

        wp_add_inline_script('wp-blocks', $js, 'after');
    }
}