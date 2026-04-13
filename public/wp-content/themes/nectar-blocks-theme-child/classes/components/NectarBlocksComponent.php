<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class PolylangComponent
 * @package NectarBlocksComponent
 */
class NectarBlocksComponent extends Singleton {

    /**
     * NectarBlocksComponent component
     */
    protected function __construct() {

        //enqueue dynamic nectarBlocks CSS for the block editor preview
        add_action('enqueue_block_assets', [$this, 'nectarEnqueueDynamicCssBlockEditor'], 1);

        //fix Polylang meta sync for NectarBlocks
        add_filter('pll_copy_post_metas', [$this, 'fixPllMetaSync'], 1000, 2);

        //add a top-level “Block patterns” CMS page
        add_action('admin_menu', [$this, 'registerBlockPatternsMenuPage'], 19);

        //add a “Block patterns” sub-item under “Nectar Blocks”
        add_action('admin_menu', [$this, 'registerBlockPatternsSubmenu'], 20);
    }

    /**
     * Add a top-level “Block patterns” CMS page that links to the Site Editor.
     *
     * @return void
     */
    public function registerBlockPatternsMenuPage(): void {

        /*
         * page_title  = Block patterns                 → browser tab / screen heading
         * menu_title  = Block patterns                 → left-hand menu label
         * capability  = edit_theme_options            → keep in line with Site Editor
         * menu_slug   = site-editor.php?p=%2Fpattern  → direct link to patterns
         * icon_url    = dashicons-art                 → visual/pattern-related icon
         * position    = 20                            → below “Nectar Blocks”
         */
        add_menu_page(
            esc_html__('Patterns', Theme::TEXT_DOMAIN),
            esc_html__('Patterns', Theme::TEXT_DOMAIN),
            'edit_theme_options',
            'site-editor.php?p=%2Fpattern',
            '',
            'dashicons-welcome-widgets-menus',
            20
        );
    }

    /**
     * Add the “Block patterns” submenu that links to the Site Editor.
     *
     * @return void
     */
    public function registerBlockPatternsSubmenu(): void {

        /*
         * parent_slug  = nectar-blocks              → the main menu slug
         * menu_slug    = site-editor.php?p=%2Fpattern → direct link to patterns
         *
         * We don’t need a callback because we’re sending the user
         * straight to another core screen.
         */
        add_submenu_page(
            'nectar-blocks',
            esc_html__('Patterns', Theme::TEXT_DOMAIN),
            esc_html__('Patterns', Theme::TEXT_DOMAIN),
            'edit_theme_options',
            'site-editor.php?p=%2Fpattern'
        );
    }

    /**
     * Push Salient “Custom CSS” into every block-editor context,
     * including the iframe used for patterns/templates.
     * @return void
     */
    public static function nectarEnqueueDynamicCssBlockEditor(): void {

        //run only in the back-end.
        if (!is_admin()) return;

        //get the code from NB ▸ Theme Options ▸ Custom CSS.
        $codeOptions = get_option('nectar_code_options') ?: [];
        $userCss = $codeOptions['cssCode'] ?? '';

        //if there is no custom CSS, do not enqueue anything.
        if (!$userCss) return;

        /*
         * Attach to a handle that *always* exists in the editor
         * (post/page, Site Editor, pattern iframe, etc.).
         */
        if (wp_style_is('nectar-block-editor-styles', 'registered')) {
            wp_add_inline_style('nectar-block-editor-styles', $userCss);
        } else {
            wp_add_inline_style('wp-block-library', $userCss);
        }
    }

    /**
     * Allow Nectar / Salient meta to be copied **only** when Polylang
     * is creating a brand-new translation, but block them on every later
     * save or update so each language can store its own values.
     *
     * Runs very late (priority = 1000) to override the filter added by
     * Salient (~20) and the copy rules in nectar-blocks/wpml-config.xml.
     *
     * @param array $metas
     * @param bool $sync
     * @return array
     */
    public static function fixPllMetaSync(array $metas, bool $sync): array {

        //when cloning a translation ($sync is false) let Polylang copy everything
        if (!$sync) return $metas;

        //list of prefixes that should not be copied on sync-save
        $doNotCopyMetaPrefixes = ['_nectar_', 'nectar_'];

        //prevent PLL from copying any meta keys that start with the defined prefixes
        foreach ($metas as $index => $key) {
            foreach ($doNotCopyMetaPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    unset($metas[$index]);
                    break;
                }
            }
        }

        return $metas;
    }
}