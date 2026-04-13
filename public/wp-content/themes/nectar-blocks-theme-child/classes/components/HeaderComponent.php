<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Singleton;

/**
 * Class HeaderComponent
 * 
 * Handles header-related functionality and customizations
 * 
 * @package NoviOnline
 */
class HeaderComponent extends Singleton {

    /**
     * HeaderComponent constructor.
     */
    protected function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueTransparencyFiles']);
    }

    /**
     * Enqueue the CSS and JS files for mega menu transparency fix
     */
    public function enqueueTransparencyFiles(): void {
        
        // Only enqueue on pages that might have transparent headers
        if ($this->shouldEnqueueTransparencyAssets()) {

            // Enqueue CSS using webpack manifest
            $transparentHeaderMegamenuCss = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'transparent-header-megamenu.css');
            if ($transparentHeaderMegamenuCss) {
                wp_enqueue_style(Theme::TEXT_DOMAIN . '_transparent_header_megamenu', $transparentHeaderMegamenuCss, [], false);
            }

            // Enqueue JS using webpack manifest
            $transparentHeaderMegamenuJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'transparent-header-megamenu-fix.js');
            if ($transparentHeaderMegamenuJs) {
                wp_enqueue_script(Theme::TEXT_DOMAIN . '_transparent_header_megamenu_fix', $transparentHeaderMegamenuJs, [], false, true);
            }
        }
    }

    /**
     * Determine if transparency assets should be enqueued
     * 
     * @return bool
     */
    private function shouldEnqueueTransparencyAssets(): bool {
        
        //don't enqueue on admin pages
        if (is_admin()) {
            return false;
        }

        //check if transparent header is globally enabled in theme options
        $nectarOptions = get_nectar_theme_options();
        
        //check if transparent header is enabled globally
        $globalTransparentHeader = !empty($nectarOptions['transparent-header']) && $nectarOptions['transparent-header'] === '1';
        
        //check if permanent transparent header is enabled
        $permanentTransparent = !empty($nectarOptions['permanent-transparent-header']) && $nectarOptions['permanent-transparent-header'] === '1';
        
        //check for page-specific transparent header settings
        $pageTransparentHeader = false;
        
        if (is_singular()) {
            $postId = get_the_ID();
            
            //check for page header background image (usually enables transparency)
            $pageHeaderBg = get_post_meta($postId, '_nectar_header_bg', true);
            $pageHeaderBgColor = get_post_meta($postId, '_nectar_header_bg_color', true);
            
            //check for explicit transparent header setting on the page
            $pageTransparentSetting = get_post_meta($postId, '_nectar_header_transparent', true);
            
            //page has transparent header if explicitly set to transparent, has a header background image, or has a header background color
            $pageTransparentHeader = (
                $pageTransparentSetting === 'true' ||
                !empty($pageHeaderBg) ||
                !empty($pageHeaderBgColor)
            );
        }
        
        //check if current page is using a page header (common trigger for transparency)
        $usingPageHeader = false;
        if (function_exists('nectar_page_header_display_check')) {
            $usingPageHeader = nectar_page_header_display_check();
        }
        
        //enqueue if any transparent header condition is met
        return (
            $globalTransparentHeader ||
            $permanentTransparent ||
            $pageTransparentHeader ||
            $usingPageHeader
        );
    }
}
