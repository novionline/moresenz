<?php

namespace NoviOnline;

use NoviOnline\Core\PostType;
use NoviOnline\Core\Singleton;

/**
 * Class ProjectPostType
 * @package NoviOnline
 */
class ProjectPostType extends Singleton {

    /**
     * Define post type slug
     */
    const TYPE = 'novi-project';

    /**
     * Define default archive page slug
     */
    const BASE_SLUG = 'projects';

    /**
     * Define taxonomy slug for project categories
     */
    const TAXONOMY_SLUG = 'novi-project-category';

    /**
     * ProjectPostType constructor.
     */
    protected function __construct() {

        //register post type (priority 5 so we appear above Global section / Theme builder in menu)
        add_action('init', [$this, 'registerPostType'], 5);

        //register project category taxonomy
        add_action('init', [$this, 'registerTaxonomy'], 5);

        //register portfolio video meta used by nectar blocks post grid video output
        add_action('init', [$this, 'registerProjectPortfolioVideoMeta'], 20);

        //sync ACF video fields to nectar portfolio video meta format after ACF saves
        add_action('acf/save_post', [$this, 'syncAcfVideoToNectarMeta'], 20);

        $this->handleAcfJson();
    }

    /**
     * Load ACF JSON for project video field group
     * @return void
     */
    protected function handleAcfJson(): void {
        $acfJsonPath = get_stylesheet_directory() . '/acf-json';

        add_filter('acf/settings/load_json', function (array $paths = []) use ($acfJsonPath) {
            return array_merge($paths, [$acfJsonPath]);
        });
    }

    /**
     * Register post type
     * @return void
     */
    public static function registerPostType(): void {
        //register post type
        register_post_type(self::TYPE, [
            'public' => true,
            'labels' => PostType::getLabels(
                __('Project', Theme::TEXT_DOMAIN),
                __('Projects', Theme::TEXT_DOMAIN)
            ),
            'supports' => ['title', 'excerpt', 'editor', 'thumbnail', 'revisions', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => [
                'slug' => self::BASE_SLUG,
                'with_front' => true
            ],
            'menu_icon' => 'dashicons-portfolio',
            'show_in_rest' => true, //needed to enable Gutenberg :)
            'taxonomies' => [self::TAXONOMY_SLUG],
            'menu_position' => 20,
        ]);
    }

    /**
     * Register project category taxonomy
     * @return void
     */
    public static function registerTaxonomy(): void {
        register_taxonomy(self::TAXONOMY_SLUG, [self::TYPE], [
            'public' => true,
            'labels' => PostType::getLabels(
                __('Project category', Theme::TEXT_DOMAIN),
                __('Project categories', Theme::TEXT_DOMAIN)
            ),
            'hierarchical' => true,
            'rewrite' => [
                'slug' => 'project-category',
                'with_front' => true
            ],
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Register portfolio video meta for project post type
     * @return void
     */
    public function registerProjectPortfolioVideoMeta(): void {
        register_post_meta(self::TYPE, '_nectar_portfolio_video', [
            'show_in_rest' => [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'source' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => ['integer', 'null'],
                                ],
                                'type' => [
                                    'type' => 'string',
                                ],
                                'url' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'label' => __('Video', Theme::TEXT_DOMAIN),
            'single' => true,
            'default' => [
                'source' => [
                    'id' => null,
                    'url' => '',
                    'type' => 'empty'
                ],
            ],
            'type' => 'object',
            'auth_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    /**
     * Sync ACF video field to nectar portfolio video meta format
     * @param mixed $postId
     * @return void
     */
    public function syncAcfVideoToNectarMeta($postId): void {
        if (!is_numeric($postId)) {
            return;
        }
        $postId = (int)$postId;
        if ($postId <= 0) {
            return;
        }

        if (get_post_type($postId) !== self::TYPE) {
            return;
        }

        $videoFileId = function_exists('get_field') ? get_field('project_video_file', $postId) : null;

        $sourceType = 'empty';
        $finalVideoId = null;
        $finalVideoUrl = '';

        if (!empty($videoFileId)) {
            $finalVideoId = (int)$videoFileId;
            $finalVideoUrl = wp_get_attachment_url($finalVideoId) ?: '';
            $sourceType = 'media';
        }

        $metaValue = [
            'source' => [
                'id' => $finalVideoId,
                'url' => $finalVideoUrl,
                'type' => $sourceType
            ],
        ];

        update_post_meta($postId, '_nectar_portfolio_video', $metaValue);
    }

    /**
     * Get video URL for a project by post ID
     * @param int $postId
     * @return string
     */
    public static function getVideoUrlByPostId(int $postId): string {
        if ($postId <= 0) {
            return '';
        }

        $portfolioVideo = get_post_meta($postId, '_nectar_portfolio_video', true);

        if (is_array($portfolioVideo) && isset($portfolioVideo['source'])) {
            $source = $portfolioVideo['source'];
            if (!empty($source['id'])) {
                $videoUrl = wp_get_attachment_url((int)$source['id']);
                if ($videoUrl) {
                    return $videoUrl;
                }
            }
            if (!empty($source['url'])) {
                return (string)$source['url'];
            }
        }

        if (function_exists('get_field')) {
            $videoFileId = get_field('project_video_file', $postId);
            if (!empty($videoFileId)) {
                $videoUrl = wp_get_attachment_url((int)$videoFileId);
                if ($videoUrl) {
                    return $videoUrl;
                }
            }
        }

        return '';
    }
}
