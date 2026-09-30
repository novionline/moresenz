<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;
use WP_Post;

/**
 * Adds Popup Maker popup posts referenced on the current page to global $otherPosts.
 *
 * Downstream block scanning (e.g. Gravity Forms reCAPTCHA) only inspects $post + $otherPosts;
 * GF forms embedded in popups opened via NB buttons or nav items are otherwise missed.
 *
 * @package NoviOnline
 */
class PopupMakerOtherPostsComponent extends Singleton {

    private const POST_TYPE_POPUP = 'popup';

    /**
     * PopupMakerOtherPostsComponent constructor.
     */
    protected function __construct() {
        //run after global sections (wp@25) and nectar templates (wp@35) prime $otherPosts
        add_action('wp', [$this, 'primeOtherPostsWithReferencedPopups'], 36);
    }

    /**
     * Merge popup posts referenced on this request into global $otherPosts.
     *
     * @return void
     */
    public function primeOtherPostsWithReferencedPopups(): void {
        if (is_admin()) {
            return;
        }

        $popupIds = $this->collectReferencedPopupIdsForRequest();
        if (empty($popupIds)) {
            return;
        }

        global $otherPosts;

        if (!isset($otherPosts) || !is_array($otherPosts)) {
            $otherPosts = [];
        }

        foreach ($popupIds as $popupId) {
            $popupPost = get_post($popupId);
            if (!$popupPost instanceof WP_Post) {
                continue;
            }

            if ($popupPost->post_type !== self::POST_TYPE_POPUP) {
                continue;
            }

            if (!in_array($popupPost->post_status, ['publish', 'private'], true)) {
                continue;
            }

            $otherPosts[$popupPost->ID] = $popupPost;
        }

        $otherPosts = array_values($otherPosts);
    }

    /**
     * Collect unique popup IDs referenced in page content, $otherPosts, and nav menus.
     *
     * @return int[]
     */
    private function collectReferencedPopupIdsForRequest(): array {
        $popupIds = [];

        foreach ($this->getPostsToScanForPopupReferences() as $post) {
            $popupIds = array_merge($popupIds, $this->collectPopupIdsFromPost($post));
        }

        $popupIds = array_merge($popupIds, $this->collectPopupIdsFromNavMenus());

        $popupIds = array_values(array_unique(array_filter(array_map('intval', $popupIds))));

        return $popupIds;
    }

    /**
     * @return WP_Post[]
     */
    private function getPostsToScanForPopupReferences(): array {
        $postsToScan = [];

        global $post;
        if ((is_singular() || is_home()) && $post instanceof WP_Post) {
            $postsToScan[$post->ID] = $post;
        }

        global $otherPosts;
        if (is_array($otherPosts)) {
            foreach ($otherPosts as $otherPost) {
                if ($otherPost instanceof WP_Post) {
                    $postsToScan[$otherPost->ID] = $otherPost;
                }
            }
        }

        return array_values($postsToScan);
    }

    /**
     * @param WP_Post $post
     * @return int[]
     */
    private function collectPopupIdsFromPost(WP_Post $post): array {
        if ($post->post_content === '') {
            return [];
        }

        $blocks = parse_blocks($post->post_content);
        $visitedReusableBlocks = [];

        return $this->collectPopupIdsFromBlocks($blocks, $visitedReusableBlocks);
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, bool> $visitedReusableBlocks
     * @return int[]
     */
    private function collectPopupIdsFromBlocks(array $blocks, array &$visitedReusableBlocks): array {
        $popupIds = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $popupIds = array_merge($popupIds, $this->collectPopupIdsFromBlock($block));

            if (($block['blockName'] ?? '') === 'core/block') {
                $ref = $block['attrs']['ref'] ?? null;
                $refId = is_numeric($ref) ? (int) $ref : 0;

                if ($refId > 0 && empty($visitedReusableBlocks[$refId])) {
                    $visitedReusableBlocks[$refId] = true;
                    $reusablePost = get_post($refId);

                    if ($reusablePost instanceof WP_Post && $reusablePost->post_content !== '') {
                        $reusableBlocks = parse_blocks($reusablePost->post_content);
                        $popupIds = array_merge(
                            $popupIds,
                            $this->collectPopupIdsFromBlocks($reusableBlocks, $visitedReusableBlocks)
                        );
                    }
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $popupIds = array_merge(
                    $popupIds,
                    $this->collectPopupIdsFromBlocks($block['innerBlocks'], $visitedReusableBlocks)
                );
            }
        }

        return $popupIds;
    }

    /**
     * @param array<string, mixed> $block
     * @return int[]
     */
    private function collectPopupIdsFromBlock(array $block): array {
        $popupIds = [];

        $attrs = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : [];

        if (isset($attrs['openPopupId']) && is_numeric($attrs['openPopupId'])) {
            $popupId = (int) $attrs['openPopupId'];
            if ($popupId > 0) {
                $popupIds[] = $popupId;
            }
        }

        $haystacks = [];

        if (!empty($attrs['className']) && is_string($attrs['className'])) {
            $haystacks[] = $attrs['className'];
        }

        if (!empty($block['innerHTML']) && is_string($block['innerHTML'])) {
            $haystacks[] = $block['innerHTML'];
        }

        if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
            foreach ($block['innerContent'] as $chunk) {
                if (is_string($chunk)) {
                    $haystacks[] = $chunk;
                }
            }
        }

        foreach ($haystacks as $haystack) {
            if (preg_match_all('/\bpopmake-(\d+)\b/', $haystack, $matches)) {
                foreach ($matches[1] as $matchedId) {
                    $popupId = (int) $matchedId;
                    if ($popupId > 0) {
                        $popupIds[] = $popupId;
                    }
                }
            }
        }

        return $popupIds;
    }

    /**
     * Popup Maker nav menu items store popup_id in meta and inject popmake-{id} classes.
     *
     * @return int[]
     */
    private function collectPopupIdsFromNavMenus(): array {
        $popupIds = [];
        $menuLocations = get_nav_menu_locations();

        if (empty($menuLocations) || !is_array($menuLocations)) {
            return $popupIds;
        }

        foreach ($menuLocations as $menuId) {
            $menuId = (int) $menuId;
            if ($menuId <= 0) {
                continue;
            }

            $menuItems = wp_get_nav_menu_items($menuId);
            if (empty($menuItems) || !is_array($menuItems)) {
                continue;
            }

            foreach ($menuItems as $menuItem) {
                if (!is_object($menuItem) || empty($menuItem->ID)) {
                    continue;
                }

                $pumOptions = get_post_meta((int) $menuItem->ID, '_pum_nav_item_options', true);
                if (is_array($pumOptions) && !empty($pumOptions['popup_id']) && is_numeric($pumOptions['popup_id'])) {
                    $popupId = (int) $pumOptions['popup_id'];
                    if ($popupId > 0) {
                        $popupIds[] = $popupId;
                    }
                }

                $classes = $menuItem->classes ?? [];
                if (!is_array($classes)) {
                    continue;
                }

                foreach ($classes as $class) {
                    if (!is_string($class) || !preg_match('/^popmake-(\d+)$/', $class, $matches)) {
                        continue;
                    }

                    $popupId = (int) $matches[1];
                    if ($popupId > 0) {
                        $popupIds[] = $popupId;
                    }
                }
            }
        }

        return $popupIds;
    }
}
