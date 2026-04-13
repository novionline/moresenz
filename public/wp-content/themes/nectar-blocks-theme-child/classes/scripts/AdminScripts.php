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
    }
}