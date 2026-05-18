<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Formatting;
use NoviOnline\Core\Partial;
use NoviOnline\Core\Singleton;

/**
 * Class ProjectMarqueeSliderBlock
 * @package NoviOnline
 */
class ProjectMarqueeSliderBlock extends Singleton {

    /**
     * Define block ID
     */
    const BLOCK_ID = 'block_project_marquee_slider';

    /**
     * Image size for marquee cards
     */
    const IMAGE_SIZE = 'novi-project-marquee';

    /**
     * ProjectMarqueeSliderBlock constructor.
     */
    protected function __construct() {
        if (!function_exists('acf_register_block_type')) {
            return;
        }

        add_action('init', [$this, 'registerImageSize'], 20);

        acf_register_block_type([
            'name' => self::BLOCK_ID,
            'title' => self::getBlockLabel(),
            'description' => __('Display a continuously scrolling marquee of projects with hover video.', Theme::TEXT_DOMAIN),
            'render_callback' => [$this, 'render'],
            'category' => 'nectar',
            'supports' => [
                'customClassName' => true,
                'align' => false,
                'mode' => false,
            ],
            'mode' => 'preview',
            'icon' => [
                'src' => 'images-alt2',
                'foreground' => '#945ef0',
            ],
        ]);

        add_action('wp_enqueue_scripts', function () {
            global $otherPosts;
            if (!isset($otherPosts) || !is_array($otherPosts)) {
                $otherPosts = [];
            }
            global $post;
            foreach (array_merge([$post], $otherPosts) as $pagePost) {
                if ($pagePost && has_block('acf/block-project-marquee-slider', $pagePost)) {
                    $this->initFrontendAssets();
                    break;
                }
            }
        });

        add_action('enqueue_block_assets', function () {
            $this->initFrontendAssets();
        });

        $this->handleAcfJson();
    }

    /**
     * Register image size for marquee cards
     * @return void
     */
    public function registerImageSize(): void {
        add_image_size(self::IMAGE_SIZE, 600, 750, true);
    }

    /**
     * Handle ACF JSON loading and saving
     * @return void
     */
    public function handleAcfJson(): void {
        $acfJsonPath = get_stylesheet_directory() . '/blocks/project-marquee-slider/acf-json';

        add_filter('acf/settings/load_json', function (array $paths = []) use ($acfJsonPath) {
            return array_merge($paths, [$acfJsonPath]);
        });

        add_filter('acf/settings/save_json', function (string $path) use ($acfJsonPath) {
            if ($fieldGroupTitle = ($_POST['post_title'] ?? '')) {
                $blockGroups = array_map(function ($blockFile) {
                    return str_replace('.json', '', $blockFile);
                }, array_diff(scandir($acfJsonPath), ['.', '..']));

                if (count($blockGroups) > 0 && in_array(Formatting::slugify($fieldGroupTitle), $blockGroups)) {
                    $path = $acfJsonPath;
                }
            }
            return $path;
        });
    }

    /**
     * Get block label
     * @return string
     */
    public static function getBlockLabel(): string {
        return __('Project marquee slider', Theme::TEXT_DOMAIN);
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
            'project-marquee-slider',
            [
                'block' => $block,
                'content' => $content,
                'is_preview' => $is_preview,
                'post_id' => $post_id,
            ],
            true,
            get_stylesheet_directory() . '/blocks/project-marquee-slider/partials/'
        );
    }

    /**
     * Init frontend assets
     * @return void
     */
    public function initFrontendAssets(): void {
        if (!wp_style_is('nectar-blocks-scrolling-marquee', 'registered') && defined('NECTAR_BLOCKS_PLUGIN_PATH') && defined('NECTAR_BLOCKS_VERSION')) {
            wp_register_style(
                'nectar-blocks-scrolling-marquee',
                NECTAR_BLOCKS_PLUGIN_PATH . '/build/blocks/scrolling-marquee/frontend-style.css',
                [],
                NECTAR_BLOCKS_VERSION
            );
        }
        if (wp_style_is('nectar-blocks-scrolling-marquee', 'registered')) {
            wp_enqueue_style('nectar-blocks-scrolling-marquee');
        }

        $blockCss = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'project-marquee-slider-style.css');
        if ($blockCss && !wp_style_is(self::BLOCK_ID . '_styles', 'enqueued')) {
            wp_enqueue_style(self::BLOCK_ID . '_styles', $blockCss);
        }

        $blockJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'project-marquee-slider-script.js');
        if ($blockJs && !wp_script_is(self::BLOCK_ID . '_script', 'enqueued')) {
            wp_enqueue_script(self::BLOCK_ID . '_script', $blockJs, [], null, true);
        }
    }

    /**
     * Convert ACF speed value (0–1) to Nectar marquee --speed duration
     * @param float|null $speed
     * @return string
     */
    public static function speedToCssDuration(?float $speed): string {
        if ($speed === null) {
            $speed = 0.5;
        }
        $speed = max(0, min(1, $speed));
        if ($speed === 0.0) {
            return '0s';
        }
        if ($speed === 1.0) {
            return '2s';
        }
        return max(0, 70 * (1 - $speed)) . 's';
    }

    /**
     * Resolve fallback logo attachment ID for cards without featured images
     * @param int|null $blockLogoId
     * @return int
     */
    public static function resolveFallbackLogoId(?int $blockLogoId = null): int {
        if (!empty($blockLogoId)) {
            return (int)$blockLogoId;
        }

        $customLogoId = (int)get_theme_mod('custom_logo');
        if ($customLogoId > 0) {
            return $customLogoId;
        }

        $siteIconId = (int)get_option('site_icon');
        if ($siteIconId > 0) {
            return $siteIconId;
        }

        return 0;
    }
}
