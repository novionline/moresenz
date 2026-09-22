<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Partial;
use NoviOnline\Core\Singleton;

/**
 * Class ProjectArchiveGridBlock
 * @package NoviOnline
 */
class ProjectArchiveGridBlock extends Singleton {

    /**
     * Define block ID
     */
    const BLOCK_ID = 'block_project_archive_grid';

    /**
     * Image size for archive cards (landscape ~4/3 from Figma)
     */
    const IMAGE_SIZE = 'novi-project-archive';

    /**
     * Intermediate archive crop for srcset (same 4/3 aspect)
     */
    const IMAGE_SIZE_MEDIUM = 'novi-project-archive-md';

    /**
     * Archive grid card display widths (1-col mobile / 2-col from md; gaps match SCSS)
     */
    const IMAGE_SIZES_ATTR = '(max-width: 767px) 100vw, (max-width: 1023px) calc((100vw - 4rem) / 2), calc((100vw - 10rem) / 2)';

    /**
     * ProjectArchiveGridBlock constructor.
     */
    protected function __construct() {
        if (!function_exists('acf_register_block_type')) {
            return;
        }

        add_action('init', [$this, 'registerImageSize'], 20);

        acf_register_block_type([
            'name' => self::BLOCK_ID,
            'title' => self::getBlockLabel(),
            'description' => __('Grid of all projects using the same card markup and hover as the project marquee slider.', Theme::TEXT_DOMAIN),
            'render_callback' => [$this, 'render'],
            'category' => 'nectar',
            'supports' => [
                'customClassName' => true,
                'align' => false,
                'mode' => false,
            ],
            'mode' => 'preview',
            'icon' => [
                'src' => 'grid-view',
                'foreground' => '#945ef0',
            ],
        ]);

        add_action('wp_enqueue_scripts', function () {
            if ($this->shouldEnqueueAssets()) {
                $this->initFrontendAssets();
            }
        });

        add_action('enqueue_block_assets', function () {
            $this->initFrontendAssets();
        });

        add_action('enqueue_block_editor_assets', function () {
            $this->initFrontendAssets();
        });
    }

    /**
     * Register landscape image sizes for archive cards
     * @return void
     */
    public function registerImageSize(): void {
        add_image_size(self::IMAGE_SIZE, 1200, 900, true);
        //same aspect as IMAGE_SIZE so WP includes it in srcset for archive cards
        add_image_size(self::IMAGE_SIZE_MEDIUM, 800, 600, true);
    }

    /**
     * Whether frontend assets should load on this request
     * @return bool
     */
    protected function shouldEnqueueAssets(): bool {
        if (is_post_type_archive(ProjectPostType::TYPE)) {
            return true;
        }

        global $otherPosts;
        if (!isset($otherPosts) || !is_array($otherPosts)) {
            $otherPosts = [];
        }
        global $post;
        foreach (array_merge([$post], $otherPosts) as $pagePost) {
            if ($pagePost && has_block('acf/block-project-archive-grid', $pagePost)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get block label
     * @return string
     */
    public static function getBlockLabel(): string {
        return __('Project archive grid', Theme::TEXT_DOMAIN);
    }

    /**
     * Render block template with PHP
     * @param array $block
     * @param string $content
     * @param bool $is_preview
     * @param int|string $post_id
     * @return string
     */
    public function render(array $block, string $content = '', bool $is_preview = false, int|string $post_id = 0): string {
        return Partial::render(
            'project-archive-grid',
            [
                'block' => $block,
                'content' => $content,
                'is_preview' => $is_preview,
                'post_id' => $post_id,
            ],
            true,
            get_stylesheet_directory() . '/blocks/project-archive-grid/partials/'
        );
    }

    /**
     * Init frontend assets (reuses marquee card CSS/JS + archive grid layout)
     * @return void
     */
    public function initFrontendAssets(): void {
        //reuse marquee card styles and hover script for identical easings
        ProjectMarqueeSliderBlock::getInstance()->initFrontendAssets();

        $blockCss = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'project-archive-grid-style.css');
        if ($blockCss && !wp_style_is(self::BLOCK_ID . '_styles', 'enqueued')) {
            wp_enqueue_style(self::BLOCK_ID . '_styles', $blockCss, [ProjectMarqueeSliderBlock::BLOCK_ID . '_styles']);
        }

        $blockJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'project-archive-grid-script.js');
        if ($blockJs && !wp_script_is(self::BLOCK_ID . '_script', 'enqueued')) {
            wp_enqueue_script(
                self::BLOCK_ID . '_script',
                $blockJs,
                [ProjectMarqueeSliderBlock::BLOCK_ID . '_script'],
                null,
                true
            );
        }
    }
}
