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
     * @return array
     */
    public static function getUsedBlocksByName(string $blockName, bool $checkOtherBlocks = true): array {
        $postsToCheck = [];

        //handle current post
        if (is_single() || is_page()) {
            global $post;
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
        $foundBlocks = self::getBlocksByName($blocks, $blockName);

        //store in cache
        self::$usedBlocksCacheByPost[$cacheKey] = $foundBlocks;

        return $foundBlocks;
    }

    /**
     * Recursively search through an array of blocks and return those matching the given block name.
     *
     * @param array $blocks Parsed blocks array.
     * @param string $blockName The block name to search for.
     * @return array
     */
    protected static function getBlocksByName(array $blocks, string $blockName): array {
        $found = [];

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && $block['blockName'] === $blockName) $found[] = $block;
            if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) $found = array_merge($found, self::getBlocksByName($block['innerBlocks'], $blockName));
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