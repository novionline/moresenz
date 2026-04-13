<?php
/**
 * Class GlobalSectionComponent
 *
 * Collects the Global Section posts that will be rendered
 * on the current page and exposes them to other components.
 *
 * @package NoviOnline
 */

namespace NoviOnline;

use Nectar\Global_Sections\Global_Sections;
use Nectar\Global_Sections\Render;
use NoviOnline\Core\Singleton;
use WP_Post;
use WP_Query;

class GlobalSectionComponent extends Singleton {

    /**
     * Cached array with visible Global Section WP_Post objects.
     *
     * @var WP_Post[]|null
     */
    private ?array $visiblePosts = null;

    /**
     * GlobalSectionComponent constructor.
     */
    protected function __construct() {

        // Make Global Section posts available before assets are queued.
        add_action('wp', [$this, 'primeOtherPostsGlobal'], 25);

        // Ensure global sections with block patterns have a tiny CSS entry
        add_action('init', [$this, 'ensurePatternCSSEnqueued'], 20);
    }

    /**
     * Return an array of WP_Post objects for all Global Sections that
     * will actually render on the current request.
     *
     * – Queries only once per request.
     * – Reuses Nectar's own conditional logic and hook-omission checks.
     * – Includes global sections used as mega-menus in navigation.
     * – Result is cached in-memory for subsequent calls.
     *
     * @return WP_Post[]  Zero-indexed array of unique WP_Post objects.
     */
    public function getVisibleGlobalSectionPosts(): array {

        // Serve from cache if we've already run.
        if ($this->visiblePosts !== null) {
            return $this->visiblePosts;
        }

        //check if Render class exists before using it
        if (!class_exists('Nectar\Global_Sections\Render')) {
            return $this->visiblePosts = [];
        }

        $render = Render::get_instance();
        
        //safety check in case get_instance returns null
        if (!$render) {
            return $this->visiblePosts = [];
        }

        $query = new WP_Query([
            'post_type' => Global_Sections::POST_TYPE,
            'post_status' => 'publish',
            'no_found_rows' => true,
            'posts_per_page' => -1,
        ]);

        $posts = [];

        while ($query->have_posts()) {
            $query->the_post();

            $sectionId = get_the_ID();
            $sectionMeta = (array)get_post_meta($sectionId, Global_Sections::META_KEY, true);
            $locations = $sectionMeta['locations'] ?? [];

            if (empty($locations)) {
                continue;
            }

            foreach ($locations as $location) {
                $hook = sanitize_text_field($location['location'] ?? '');

                // Skip hooks Nectar intentionally omits on this template.
                if ($render->omit_global_section_render($hook)) {
                    continue;
                }

                // Respect Nectar's include/exclude conditions.
                if ($render->verify_conditional_display($sectionId)) {
                    $posts[$sectionId] = clone $query->post; // keyed by ID → unique
                    break; // one passing location is enough
                }
            }
        }
        wp_reset_postdata();

        //add global sections used in navigation mega-menus
        $megaMenuSections = $this->getMegaMenuGlobalSections();
        foreach ($megaMenuSections as $sectionId => $post) {
            $posts[$sectionId] = $post;
        }

        return $this->visiblePosts = array_values($posts);
    }

    /**
     * Get all global sections that are attached to navigation menu items
     * as mega-menus (both desktop and mobile).
     *
     * @return WP_Post[]  Array of WP_Post objects keyed by section ID.
     */
    private function getMegaMenuGlobalSections(): array {
        
        $sectionPosts = [];
        $sectionIds = [];

        //get all registered nav menus
        $navMenus = get_nav_menu_locations();
        
        if (empty($navMenus)) {
            return $sectionPosts;
        }

        foreach ($navMenus as $location => $menuId) {
            
            if (!$menuId) {
                continue;
            }

            //get all menu items for this menu
            $menuItems = wp_get_nav_menu_items($menuId);
            
            if (empty($menuItems)) {
                continue;
            }

            foreach ($menuItems as $menuItem) {
                
                $menuItemOptions = maybe_unserialize(get_post_meta($menuItem->ID, 'nectar_menu_options', true));
                
                if (empty($menuItemOptions)) {
                    continue;
                }

                //check if mega menu is enabled
                $usingMegaMenu = isset($menuItemOptions['enable_mega_menu']) && 'on' === $menuItemOptions['enable_mega_menu'];
                
                if (!$usingMegaMenu) {
                    continue;
                }

                //collect desktop mega-menu global section
                if (isset($menuItemOptions['mega_menu_global_section']) && 
                    '-' !== $menuItemOptions['mega_menu_global_section']) {
                    $sectionIds[] = intval($menuItemOptions['mega_menu_global_section']);
                }

                //collect mobile mega-menu global section
                if (isset($menuItemOptions['mega_menu_global_section_mobile']) && 
                    '-' !== $menuItemOptions['mega_menu_global_section_mobile']) {
                    $sectionIds[] = intval($menuItemOptions['mega_menu_global_section_mobile']);
                }
            }
        }

        //remove duplicates and invalid IDs
        $sectionIds = array_unique(array_filter($sectionIds));

        if (empty($sectionIds)) {
            return $sectionPosts;
        }

        //query the global section posts
        $query = new WP_Query([
            'post_type' => Global_Sections::POST_TYPE,
            'post_status' => 'publish',
            'post__in' => $sectionIds,
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ]);

        foreach ($query->posts as $post) {
            $sectionPosts[$post->ID] = clone $post;
        }

        wp_reset_postdata();

        return $sectionPosts;
    }

    /**
     * Add the visible Global Section posts to the global $otherPosts array
     * so that blocks such as NoviMenuBlock can detect them.
     *
     * Runs automatically on the 'wp' action (priority 25).
     *
     * @return void
     */
    public function primeOtherPostsGlobal(): void {

        global $otherPosts;

        if (!isset($otherPosts) || !is_array($otherPosts)) {
            $otherPosts = [];
        }

        foreach ($this->getVisibleGlobalSectionPosts() as $gsPost) {
            $otherPosts[$gsPost->ID] = $gsPost; // deduplicate by ID
        }

        // Re-index numerically to stay compatible with existing foreach loops.
        $otherPosts = array_values($otherPosts);
    }

    /**
     * Ensure global sections with block patterns get their CSS output.
     * 
     * The Nectar Blocks plugin only outputs CSS from block patterns when
     * the global section itself has CSS. This workaround injects the CSS
     * into sections that have patterns but no section CSS.
     * 
     * @return void
     */
    public function ensurePatternCSSEnqueued(): void {

        // Hook into output buffering to prepend CSS before the section content
        add_filter('do_shortcode_tag', function($output, $tag, $atts) {
            if ($tag !== 'nectar_global_section') {
                return $output;
            }
            
            $section_id = isset($atts['id']) ? intval($atts['id']) : 0;
            
            if (!$section_id) {
                return $output;
            }
            
            $section_content = get_post_field('post_content', $section_id);
            if (empty($section_content)) {
                return $output;
            }
            
            $blocks = parse_blocks($section_content);
            $has_patterns = false;
            foreach ($blocks as $block) {
                if (isset($block['blockName']) && $block['blockName'] === 'core/block') {
                    $has_patterns = true;
                    break;
                }
            }
            
            // Only process if section has patterns but no CSS
            $dynamic_css = get_post_meta($section_id, '_nectar_blocks_css', true);
            if (!$has_patterns || !empty($dynamic_css)) {
                return $output;
            }
            
            // Get and inject pattern CSS
            $BLOCKS_RENDER_REFLECTION = new \ReflectionClass(\Nectar\Render\Render::class);
            $BLOCKS_RENDER = $BLOCKS_RENDER_REFLECTION->newInstanceWithoutConstructor();
            $patterns_css = $BLOCKS_RENDER->frontend_pattern_css($blocks);
            
            if (!empty($patterns_css)) {
                $FE_RENDER = new \Nectar\Dynamic_Data\Frontend_Render();
                $patterns_css = $FE_RENDER->render_dynamic_content([], $patterns_css);
                $output = '<style data-type="nectar-global-section-dynamic-css">' . $patterns_css . '</style>' . $output;
            }
            
            return $output;
        }, 10, 3);
    }
}
