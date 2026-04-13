<?php

namespace NoviOnline;

use NoviOnline\Core\Enqueue;
use NoviOnline\Core\Formatting;
use NoviOnline\Core\Gutenberg;
use NoviOnline\Core\Partial;
use NoviOnline\Core\Singleton;

/**
 * Class NoviMenuBlock
 * @package NoviOnline
 */
class NoviMenuBlock extends Singleton {

    /**
     * Define block ID
     */
    const BLOCK_ID = 'block_novi_menu';

    /**
     * MenuBlock constructor.
     */
    protected function __construct() {
        if (function_exists('acf_register_block_type')) {

            //register block
            acf_register_block_type([
                'name' => self::BLOCK_ID,
                'title' => self::getBlockLabel(),
                'description' => __('Display a centralized WordPress menu.', Theme::TEXT_DOMAIN),
                'render_callback' => [$this, 'render'],
                'category' => 'nectar',
                'supports' => [
                    'customClassName' => true,
                    'align' => false,
                    'mode' => false
                ],
                'mode' => 'preview',
                'icon' => [
                    'src' => 'menu',
                    'foreground' => '#945ef0',
                ]
            ]);

            //init front-end assets
            add_action('wp_enqueue_scripts', function () {
                global $otherPosts;
                if (!isset($otherPosts) || !is_array($otherPosts)) $otherPosts = [];
                global $post;
                foreach (array_merge([$post], $otherPosts) as $pagePost) {
                    if (has_block('acf/block-novi-menu', $pagePost)) {
                        $this->initFrontendAssets();
                        break;
                    }
                }
            });

            //init back-end assets
            add_action('enqueue_block_assets', function () {
                $this->initFrontendAssets();
            });

            //handle ACF JSON
            $this->handleAcfJson();
        }
    }

    /**
     * Handle ACF JSON loading and saving
     * @return void
     */
    public function handleAcfJson(): void {

        //define the path where the ACF JSON files of this block are stored
        $acfJsonPath = get_stylesheet_directory() . '/blocks/novi-menu/acf-json';

        //load ACF JSON for this block
        add_filter('acf/settings/load_json', function (array $paths = []) use ($acfJsonPath) {
            return array_merge($paths, [$acfJsonPath]);
        });

        //save ACF JSON for this block in the correct directory
        add_filter('acf/settings/save_json', function (string $path) use ($acfJsonPath) {
            if ($fieldGroupTitle = ($_POST['post_title'] ?? '')) {

                //read the acf-json directory of this block for json files
                $blockGroups = array_map(function ($blockFile) {
                    return str_replace('.json', '', $blockFile);
                }, array_diff(scandir($acfJsonPath), ['.', '..']));

                //save to the block directory if the ACF group belongs to this block
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
        return __('Novi menu', Theme::TEXT_DOMAIN);
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
        return Partial::render('novi-menu',
            ['block' => $block, 'content' => $content, 'is_preview' => $is_preview, 'post_id' => $post_id],
            true,
            get_stylesheet_directory() . '/blocks/novi-menu/partials/'
        );
    }

    /**
     * Init frontend assets
     * @return void
     */
    public function initFrontendAssets(): void {

        //register block styles
        $blockCss = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'novi-menu-style.css');
        if ($blockCss) wp_enqueue_style(self::BLOCK_ID . '_styles', $blockCss);

        //check if at least one menu block has attr collapsible set to "1"
        $menuBlocks = Gutenberg::getUsedBlocksByName('acf/block-novi-menu');
        $collapsibleEnabled = false;
        foreach ($menuBlocks as $menuBlock) {
            if (isset($menuBlock['attrs']['data']['collapsible']) && $menuBlock['attrs']['data']['collapsible'] === "1") {
                $collapsibleEnabled = true;
                break;
            }
        }

        //add accordion assets if collapsible is enabled for at least one menu block
        if ($collapsibleEnabled) {
            $accordionJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'Accordion.js');
            $initAccordionJs = Enqueue::getWebpackAssetUrlByKey(MANIFEST_PATH, 'initAccordions.js');
            if ($accordionJs && $initAccordionJs) {
                wp_enqueue_script('novi-accordion', $accordionJs, [], null, true);
                wp_enqueue_script('novi-accordion-init', $initAccordionJs, ['novi-accordion'], null, true);
            }
        }
    }
}