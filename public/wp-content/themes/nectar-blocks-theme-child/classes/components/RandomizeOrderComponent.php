<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Gutenberg;
use NoviOnline\Core\Singleton;

/**
 * Client-side randomize-order bridge for Nectar container blocks.
 *
 * Editors enable per-block via the noviRandomizeOrder Gutenberg attribute.
 * Frontend JS reorders child items in the DOM before block scripts (e.g. Swiper) initialize.
 *
 * @package NoviOnline
 */
class RandomizeOrderComponent extends Singleton {

    const ATTR_NAME = 'noviRandomizeOrder';

    /**
     * Block configs keyed by block name.
     * @var array<string, array{wrapperClass: string, blockType: string, scriptDep?: string}>
     */
    const BLOCK_CONFIGS = [
        'nectar-blocks/carousel' => [
            'wrapperClass' => 'nectar-blocks-carousel',
            'blockType' => 'carousel',
            'scriptDep' => 'nectar-blocks-carousel',
        ],
        'nectar-blocks/flex-box' => [
            'wrapperClass' => 'nectar-blocks-flex-box',
            'blockType' => 'flexbox',
        ],
    ];

    /**
     * Whether the current request needs the randomize-order frontend script.
     * @var bool
     */
    private static bool $shouldEnqueue = false;

    /**
     * Block types detected on the current page that have randomize order enabled.
     * @var string[]
     */
    private static array $activeBlockTypes = [];

    /**
     * RandomizeOrderComponent constructor.
     */
    protected function __construct() {
        //run after global sections and nectar templates prime global $otherPosts (wp@25 / wp@35)
        add_action('wp', [static::class, 'detectRandomizeOrderBlocksFromPage'], 40);
        add_action('wp_enqueue_scripts', [static::class, 'enqueueFrontendScript'], 20);

        add_filter('register_block_type_args', [static::class, 'registerBlockAttributes'], 10, 2);
        add_filter('render_block', [static::class, 'filterRenderBlock'], 10, 2);
    }

    /**
     * Detect blocks with randomize order enabled on the current page before render.
     * @return void
     */
    public static function detectRandomizeOrderBlocksFromPage(): void {
        if (is_admin() || self::$shouldEnqueue) {
            return;
        }

        foreach (self::BLOCK_CONFIGS as $blockName => $config) {
            $blocks = Gutenberg::getUsedBlocksByName($blockName, true, true);
            if (empty($blocks)) {
                continue;
            }

            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $attrs = $block['attrs'] ?? [];
                if (!is_array($attrs) || empty($attrs[self::ATTR_NAME])) {
                    continue;
                }

                $blockType = $config['blockType'];
                if (!in_array($blockType, self::$activeBlockTypes, true)) {
                    self::$activeBlockTypes[] = $blockType;
                }

                self::$shouldEnqueue = true;
            }
        }
    }

    /**
     * Register noviRandomizeOrder attribute server-side for configured blocks.
     * @param array $args
     * @param string $blockType
     * @return array
     */
    public static function registerBlockAttributes(array $args, string $blockType): array {
        if (!isset(self::BLOCK_CONFIGS[$blockType])) {
            return $args;
        }

        if (!isset($args['attributes']) || !is_array($args['attributes'])) {
            $args['attributes'] = [];
        }

        $args['attributes'][self::ATTR_NAME] = [
            'type' => 'boolean',
            'default' => false,
        ];

        return $args;
    }

    /**
     * Add data attributes to block wrappers when randomize order is enabled.
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public static function filterRenderBlock($blockContent, array $block) {
        $blockName = (string)($block['blockName'] ?? '');
        if (!isset(self::BLOCK_CONFIGS[$blockName])) {
            return $blockContent;
        }

        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        $attrs = $block['attrs'] ?? [];
        if (!is_array($attrs) || empty($attrs[self::ATTR_NAME])) {
            return $blockContent;
        }

        $config = self::BLOCK_CONFIGS[$blockName];
        $wrapperClass = preg_quote($config['wrapperClass'], '/');
        $blockType = $config['blockType'];
        $blockId = (string)($attrs['blockId'] ?? '');

        $pattern = '/(<div\s[^>]*\b' . $wrapperClass . '\b[^>]*)(>)/s';
        if (!preg_match($pattern, $blockContent)) {
            return $blockContent;
        }

        $dataAttrs = ' data-novi-randomize-order="true" data-novi-randomize-block="' . esc_attr($blockType) . '"';
        if ($blockId !== '') {
            $dataAttrs .= ' data-novi-randomize-id="' . esc_attr($blockId) . '"';
        }

        $replacement = '$1' . $dataAttrs . '$2';

        return preg_replace($pattern, $replacement, $blockContent, 1);
    }

    /**
     * Conditionally enqueue the randomize-order frontend script.
     * @return void
     */
    public static function enqueueFrontendScript(): void {
        if (is_admin() || !self::$shouldEnqueue) {
            return;
        }

        $scriptUrl = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'randomize-order.js');
        if (!$scriptUrl) {
            $scriptUrl = get_stylesheet_directory_uri() . '/js/chunk/randomize-order.js';
        }

        if (!$scriptUrl) {
            return;
        }

        $deps = [];
        foreach (self::BLOCK_CONFIGS as $config) {
            $blockType = $config['blockType'];
            if (!in_array($blockType, self::$activeBlockTypes, true)) {
                continue;
            }

            $scriptDep = $config['scriptDep'] ?? '';
            if ($scriptDep !== '' && wp_script_is($scriptDep, 'registered')) {
                $deps[] = $scriptDep;
            }
        }

        wp_enqueue_script(
            Theme::TEXT_DOMAIN . '_randomize_order',
            $scriptUrl,
            array_values(array_unique($deps)),
            false,
            true
        );
    }
}
