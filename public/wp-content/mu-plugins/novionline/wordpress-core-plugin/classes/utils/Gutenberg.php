<?php

namespace NoviOnline\Core;

/**
 * Class Gutenberg
 * @package NoviOnline\Core
 */
class Gutenberg {

    /**
     * Cache for storing results per post.
     * Format: [ cacheKey => array of blocks ]
     *
     * @var array
     */
    protected static array $usedBlocksCacheByPost = [];

    /**
     * Get flat parsed Gutenberg block objects for the current post and, optionally, global $otherPosts.
     *
     * This method collects posts from the global $post and, if $checkOtherBlocks is true,
     * from the global $otherPosts, then delegates to getUsedBlocksByNameForPost() for each post
     * and merges the results.
     *
     * @param string $blockName
     * @param bool $checkOtherBlocks If false, only the global $post is checked.
     * @param bool $includeNonSingularGlobalPost If true, the global $post is also checked on non-singular views.
     * @return array
     */
    public static function getUsedBlocksByName(string $blockName, bool $checkOtherBlocks = true, bool $includeNonSingularGlobalPost = false): array {
        $postsToCheck = [];

        //handle current post
        global $post;
        if (is_single() || is_page() || $includeNonSingularGlobalPost) {
            if (is_a($post, '\WP_Post')) $postsToCheck[] = $post;
        }

        //handle other posts
        if ($checkOtherBlocks) {
            global $otherPosts;
            if (!is_array($otherPosts)) $otherPosts = [];
            foreach ($otherPosts as $otherPost) {
                if (is_a($otherPost, '\WP_Post')) $postsToCheck[] = $otherPost;
            }
        }

        //get blocks for each post
        $results = [];
        foreach ($postsToCheck as $singlePost) {
            $resultForPost = self::getUsedBlocksByNameForPost($singlePost, $blockName);
            $results = array_merge($results, $resultForPost);
        }

        //remove duplicate blocks before returning.
        return array_values(array_unique($results, SORT_REGULAR));
    }

    /**
     * Get flat parsed Gutenberg block objects for a given post by block name.
     * This method parses the given post content and recursively retrieves blocks matching $blockName.
     *
     * @param \WP_Post $post The post to parse.
     * @param string $blockName
     * @return array
     */
    public static function getUsedBlocksByNameForPost(\WP_Post $post, string $blockName): array {

        //attempt to retrieve from cache
        $cacheKey = md5($blockName . '_' . $post->ID);
        if (isset(self::$usedBlocksCacheByPost[$cacheKey])) return self::$usedBlocksCacheByPost[$cacheKey];

        //parse blocks
        $blocks = parse_blocks($post->post_content);
        $visitedReusableBlocks = [];
        $foundBlocks = self::getBlocksByName($blocks, $blockName, $visitedReusableBlocks);

        //store in cache
        self::$usedBlocksCacheByPost[$cacheKey] = $foundBlocks;

        return $foundBlocks;
    }

    /**
     * Extract synced pattern ref IDs (core/block attrs.ref) from a post's block content.
     *
     * @param \WP_Post $post
     * @return int[]
     */
    public static function getPatternRefsForPost(\WP_Post $post): array
    {
        //collect only the direct core/block refs used in this post's own content,
        //without following refs into referenced wp_block posts (the caller builds the tree level by level)
        $blocks = parse_blocks($post->post_content);

        $collectRefs = function (array $blocks) use (&$collectRefs): array {
            $refIds = [];
            foreach ($blocks as $block) {
                if (($block['blockName'] ?? '') === 'core/block') {
                    $refId = isset($block['attrs']['ref']) ? (int) $block['attrs']['ref'] : 0;
                    if ($refId && !in_array($refId, $refIds, true)) $refIds[] = $refId;
                }
                if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    $refIds = array_merge($refIds, $collectRefs($block['innerBlocks']));
                }
            }
            return $refIds;
        };

        return array_values(array_unique($collectRefs($blocks)));
    }

    /**
     * Recursively search through an array of blocks and return those matching the given block name.
     *
     * @param array $blocks Parsed blocks array.
     * @param string $blockName The block name to search for.
     * @param array<int, bool> $visitedReusableBlocks Prevent infinite recursion for reusable blocks (core/block ref).
     * @return array
     */
    protected static function getBlocksByName(array $blocks, string $blockName, array &$visitedReusableBlocks = []): array {
        $found = [];

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && $block['blockName'] === $blockName) $found[] = $block;

            //reusable blocks (synced patterns) are stored as core/block with a ref to a wp_block post
            if (($block['blockName'] ?? '') === 'core/block') {
                $ref = $block['attrs']['ref'] ?? null;
                $refId = is_numeric($ref) ? (int)$ref : 0;
                if ($refId > 0 && empty($visitedReusableBlocks[$refId])) {
                    $visitedReusableBlocks[$refId] = true;
                    $reusablePost = get_post($refId);
                    if (is_a($reusablePost, '\WP_Post') && $reusablePost->post_content) {
                        $reusableBlocks = parse_blocks($reusablePost->post_content);
                        $found = array_merge($found, self::getBlocksByName($reusableBlocks, $blockName, $visitedReusableBlocks));
                    }
                }
            }

            if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $found = array_merge($found, self::getBlocksByName($block['innerBlocks'], $blockName, $visitedReusableBlocks));
            }
        }

        return $found;
    }

    /**
     * Exclude specific blocks from post content.
     * @param string $content
     * @param array $blockNamesToExclude
     * @return string
     */
    public static function excludeBlocksFromPostContent(string $content, array $blockNamesToExclude): string {
        $blocks = parse_blocks($content);

        $filterBlocks = function(array $blocks) use (&$filterBlocks, $blockNamesToExclude) {
            $filtered = [];
            foreach ($blocks as $block) {
                if (isset($block['blockName']) && in_array($block['blockName'], $blockNamesToExclude)) {
                    continue; //skip this block
                }

                //recursively filter innerBlocks if present
                if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    $block['innerBlocks'] = $filterBlocks($block['innerBlocks']);
                }

                $filtered[] = $block;
            }

            return $filtered;
        };

        $filteredBlocks = $filterBlocks($blocks);
        return serialize_blocks($filteredBlocks);
    }
}