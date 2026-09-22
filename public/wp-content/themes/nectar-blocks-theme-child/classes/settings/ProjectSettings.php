<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class ProjectSettings
 * @package NoviOnline
 */
class ProjectSettings extends Singleton {

    /**
     * Define menu slug
     */
    const MENU_SLUG = 'novi-project-settings';

    /**
     * Option key for posts-per-page value synced across all Polylang languages
     */
    const SYNCED_OPTION_POSTS_PER_PAGE = 'novi_project_posts_per_page';

    /**
     * Option key for excerpt word count synced across all Polylang languages
     */
    const SYNCED_OPTION_EXCERPT_WORD_COUNT = 'novi_project_excerpt_word_count';

    /**
     * Default posts per page for the project archive grid
     */
    const DEFAULT_POSTS_PER_PAGE = 10;

    /**
     * Default excerpt word count for the project archive grid
     */
    const DEFAULT_EXCERPT_WORD_COUNT = 24;

    /**
     * ProjectSettings constructor.
     */
    protected function __construct() {
        add_action('init', function () {
            if (!function_exists('acf_add_options_sub_page')) {
                return;
            }

            acf_add_options_sub_page([
                'page_title' => __('Project settings', Theme::TEXT_DOMAIN),
                'menu_title' => __('Project settings', Theme::TEXT_DOMAIN),
                'menu_slug' => self::MENU_SLUG,
                'post_id' => self::MENU_SLUG,
                'parent_slug' => sprintf('edit.php?post_type=%1$s', ProjectPostType::TYPE),
            ]);
        });
    }

    /**
     * Get posts per page for project archive (-1 = show all)
     * @return int
     */
    public static function getPostsPerPage(): int {
        $synced = get_option(self::SYNCED_OPTION_POSTS_PER_PAGE, null);
        if ($synced !== null && $synced !== '') {
            $v = (int) $synced;
            if ($v === -1 || $v > 0) {
                return $v;
            }
        }

        $configuredPostsPerPage = null;
        if (function_exists('get_field')) {
            $configuredPostsPerPage = get_field('project_posts_per_page', self::MENU_SLUG);
        }

        if ($configuredPostsPerPage === null || $configuredPostsPerPage === '' || $configuredPostsPerPage === false) {
            return self::DEFAULT_POSTS_PER_PAGE;
        }

        $configuredPostsPerPage = (int) $configuredPostsPerPage;

        return $configuredPostsPerPage === -1 || $configuredPostsPerPage > 0
            ? $configuredPostsPerPage
            : self::DEFAULT_POSTS_PER_PAGE;
    }

    /**
     * Get excerpt word count for project archive cards
     * @return int
     */
    public static function getExcerptWordCount(): int {
        $synced = get_option(self::SYNCED_OPTION_EXCERPT_WORD_COUNT, null);
        if ($synced !== null && $synced !== '') {
            $v = (int) $synced;
            if ($v > 0) {
                return $v;
            }
        }

        $configuredWordCount = null;
        if (function_exists('get_field')) {
            $configuredWordCount = get_field('project_excerpt_word_count', self::MENU_SLUG);
        }

        if ($configuredWordCount === null || $configuredWordCount === '' || $configuredWordCount === false) {
            return self::DEFAULT_EXCERPT_WORD_COUNT;
        }

        $configuredWordCount = (int) $configuredWordCount;

        return $configuredWordCount > 0
            ? $configuredWordCount
            : self::DEFAULT_EXCERPT_WORD_COUNT;
    }
}
