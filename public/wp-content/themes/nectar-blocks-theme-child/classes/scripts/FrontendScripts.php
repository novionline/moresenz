<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Singleton;

/**
 * Class FrontendScripts
 * @package NoviOnline
 */
class FrontendScripts extends Singleton {

    /**
     * FrontendScripts constructor.
     */
    protected function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'initFrontendScripts']);
    }

    /**
     * Init front-end scripts in
     */
    public static function initFrontendScripts() {
        $frontendScripts = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'novi-frontend.js');
        if ($frontendScripts) {
            $handle = Theme::TEXT_DOMAIN . '_novi_frontend';
            wp_enqueue_script($handle, $frontendScripts, [], false, true);
            
            //localize script with translatable strings
            wp_localize_script($handle, 'noviFrontendI18n', [
                'addToOfferList' => __('Add to module list', Theme::TEXT_DOMAIN),
                'addedToOfferList' => __('Added to module list', Theme::TEXT_DOMAIN),
            ]);
        }

        //make full search result items clickable
        if (!is_admin() && is_search()) {
            $searchResultsLinkSnippetJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'search-results-link-snippet.js');
            if ($searchResultsLinkSnippetJs) {
                $handle = Theme::TEXT_DOMAIN . '_search_results_link_snippet';
                wp_enqueue_script($handle, $searchResultsLinkSnippetJs, [], false, true);
            }
        }

        if (!is_admin()) {
            //enqueue Gravity Forms alerts styles
            $gravityFormsAlertsCss = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'novi-gravityforms-alerts.css');
            if ($gravityFormsAlertsCss) {
                wp_enqueue_style(
                    Theme::TEXT_DOMAIN . '_gravityforms_alerts',
                    $gravityFormsAlertsCss,
                    [],
                    false
                );
            }

            //carousel novi mouse follower (non-touch only, enqueued on front)
            $carouselMouseFollowerCss = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'novi-carousel-mouse-follower.css');
            if (!$carouselMouseFollowerCss) {
                $carouselMouseFollowerCss = get_stylesheet_directory_uri() . '/dist/novi-carousel-mouse-follower.css';
            }
            if ($carouselMouseFollowerCss) {
                wp_enqueue_style(
                    Theme::TEXT_DOMAIN . '_carousel_mouse_follower',
                    $carouselMouseFollowerCss,
                    [],
                    false
                );
            }
            $carouselMouseFollowerJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'carousel-mouse-follower.js');
            if (!$carouselMouseFollowerJs) {
                $carouselMouseFollowerJs = get_stylesheet_directory_uri() . '/js/chunk/carousel-mouse-follower.js';
            }
            if ($carouselMouseFollowerJs) {
                wp_enqueue_script(
                    Theme::TEXT_DOMAIN . '_carousel_mouse_follower',
                    $carouselMouseFollowerJs,
                    [],
                    false,
                    true
                );
            }
        }
    }
}