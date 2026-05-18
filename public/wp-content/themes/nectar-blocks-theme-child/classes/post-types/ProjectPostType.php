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
     * Card hover type values for marquee slider
     */
    const HOVER_TYPE_NONE = 'none';

    const HOVER_TYPE_VIDEO = 'video';

    const HOVER_TYPE_IMAGE = 'image';

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
     * Get card hover type for a project by post ID
     * @param int $postId
     * @return string
     */
    public static function getCardHoverTypeByPostId(int $postId): string {
        if ($postId <= 0) {
            return self::HOVER_TYPE_NONE;
        }

        $hoverType = '';
        if (function_exists('get_field')) {
            $hoverType = (string)(get_field('project_card_hover_type', $postId) ?: '');
        }

        if ($hoverType === self::HOVER_TYPE_VIDEO || $hoverType === self::HOVER_TYPE_IMAGE) {
            return $hoverType;
        }

        //backward compatibility: legacy projects with video but no hover type saved
        if ($hoverType === '' && function_exists('get_field')) {
            $videoFileId = get_field('project_video_file', $postId);
            if (!empty($videoFileId)) {
                return self::HOVER_TYPE_VIDEO;
            }
        }

        return self::HOVER_TYPE_NONE;
    }

    /**
     * Get hover image attachment ID for a project by post ID
     * @param int $postId
     * @return int
     */
    public static function getHoverImageIdByPostId(int $postId): int {
        if ($postId <= 0 || self::getCardHoverTypeByPostId($postId) !== self::HOVER_TYPE_IMAGE) {
            return 0;
        }

        if (!function_exists('get_field')) {
            return 0;
        }

        $imageId = get_field('project_hover_image', $postId);
        return is_numeric($imageId) ? (int)$imageId : 0;
    }

    /**
     * Get hover image URL for a project by post ID
     * @param int $postId
     * @return string
     */
    public static function getHoverImageUrlByPostId(int $postId): string {
        $imageId = self::getHoverImageIdByPostId($postId);
        if ($imageId <= 0) {
            return '';
        }

        $imageUrl = wp_get_attachment_image_url($imageId, 'full');
        return is_string($imageUrl) ? $imageUrl : '';
    }

    /**
     * Get video URL for a project by post ID
     * @param int $postId
     * @return string
     */
    public static function getVideoUrlByPostId(int $postId): string {
        if ($postId <= 0 || self::getCardHoverTypeByPostId($postId) !== self::HOVER_TYPE_VIDEO) {
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

    /**
     * Get video MIME type for a project by post ID
     * @param int $postId
     * @return string
     */
    public static function getVideoMimeTypeByPostId(int $postId): string {
        if ($postId <= 0 || self::getCardHoverTypeByPostId($postId) !== self::HOVER_TYPE_VIDEO) {
            return 'video/mp4';
        }

        $portfolioVideo = get_post_meta($postId, '_nectar_portfolio_video', true);

        if (is_array($portfolioVideo) && isset($portfolioVideo['source']) && !empty($portfolioVideo['source']['id'])) {
            $mimeType = get_post_mime_type((int)$portfolioVideo['source']['id']);
            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        if (function_exists('get_field')) {
            $videoFileId = get_field('project_video_file', $postId);
            if (!empty($videoFileId)) {
                $mimeType = get_post_mime_type((int)$videoFileId);
                if (is_string($mimeType) && $mimeType !== '') {
                    return $mimeType;
                }
            }
        }

        $videoUrl = self::getVideoUrlByPostId($postId);
        if ($videoUrl !== '') {
            $filetype = wp_check_filetype($videoUrl);
            if (!empty($filetype['type'])) {
                return $filetype['type'];
            }
        }

        return 'video/mp4';
    }
}
