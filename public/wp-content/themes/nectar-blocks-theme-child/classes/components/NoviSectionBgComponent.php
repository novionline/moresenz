<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Append novi-bg-{slug} + --novi-section-bg on row / flex-box / column roots
 * and collapse adjacent .novi-section siblings that share the same bg slug.
 *
 * Front end: render_block + wp_enqueue_scripts.
 * Editor: novi-section-bg-editor.js (BlockListBlock) + enqueue_block_assets.
 *
 * Mapping (slug-only, no hex classes):
 * - solidGlobalColorData.slug → novi-bg-{slug}, --novi-section-bg: var(--{slug})
 * - unset / empty solid → novi-bg-page
 * - White global nectar-gc-g7gf2xnFzd → same as unset (novi-bg-page) so homepage
 *   quote→text and text→client-slider pairs collapse
 * - page CSS var uses NB White token: var(--nectar-gc-g7gf2xnFzd)
 * - image / gradient → no class / no var
 *
 * @package NoviOnline
 */
class NoviSectionBgComponent extends Singleton {

    private const BLOCK_NAMES = [
        'nectar-blocks/row',
        'nectar-blocks/flex-box',
        'nectar-blocks/column',
    ];

    private const ROOT_CLASS_BY_BLOCK = [
        'nectar-blocks/row' => 'nectar-blocks-row',
        'nectar-blocks/flex-box' => 'nectar-blocks-flex-box',
        'nectar-blocks/column' => 'nectar-blocks-column',
    ];

    //unset + White global share this class for adjacent collapse
    private const PAGE_SLUG = 'page';

    //nectar global color "White" — aliased to PAGE_SLUG
    private const WHITE_GLOBAL_SLUG = 'nectar-gc-g7gf2xnFzd';

    /**
     * NoviSectionBgComponent constructor.
     */
    protected function __construct() {
        add_filter('render_block', [$this, 'appendNoviBgClass'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAdjacentSectionCss'], 30);
        //priority 100: after NB registers nectar-editor-global / nectar-front-end-render (iframe handles)
        add_action('enqueue_block_assets', [$this, 'enqueueAdjacentSectionEditorCss'], 100);
    }

    /**
     * Append novi-bg-{slug} and --novi-section-bg from solid global/accent slug.
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function appendNoviBgClass($blockContent, array $block) {
        $blockName = $block['blockName'] ?? '';
        if (!in_array($blockName, self::BLOCK_NAMES, true)) {
            return $blockContent;
        }

        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        $resolved = $this->resolveBg($block['attrs'] ?? []);
        if ($resolved === null) {
            return $blockContent;
        }

        $modifier = 'novi-bg-' . $resolved['slug'];
        $cssVar = $resolved['cssVar'];
        $rootClass = self::ROOT_CLASS_BY_BLOCK[$blockName] ?? '';
        if ($rootClass === '') {
            return $blockContent;
        }

        //only touch the first root that matches this block type
        $pattern = '/(<div\b)([^>]*\bclass=(["\'])([^"\']*\b' . preg_quote($rootClass, '/') . '\b[^"\']*)\3)([^>]*>)/i';
        $updated = preg_replace_callback(
            $pattern,
            static function (array $match) use ($modifier, $cssVar): string {
                $before = $match[1];
                $attrsWithClass = $match[2];
                $afterClass = $match[5];
                $classValue = $match[4];

                if (preg_match('/\bnovi-bg-[a-zA-Z0-9_-]+\b/', $classValue)) {
                    return $match[0];
                }

                $attrsWithClass = (string)preg_replace(
                    '/\bclass=(["\'])/',
                    'class=$1' . esc_attr($modifier) . ' ',
                    $attrsWithClass,
                    1
                );

                $styleDecl = '--novi-section-bg:' . $cssVar;
                $full = $before . $attrsWithClass . $afterClass;

                if (preg_match('/\bstyle=(["\'])(.*?)\1/i', $full, $styleMatch)) {
                    $quote = $styleMatch[1];
                    $existing = trim((string)$styleMatch[2]);
                    if (str_contains($existing, '--novi-section-bg')) {
                        return $full;
                    }
                    $merged = $existing === ''
                        ? $styleDecl
                        : rtrim($existing, ';') . ';' . $styleDecl;
                    return (string)preg_replace(
                        '/\bstyle=(["\'])(.*?)\1/i',
                        'style=' . $quote . esc_attr($merged) . $quote,
                        $full,
                        1
                    );
                }

                //insert style before the closing >
                return $before . $attrsWithClass . ' style="' . esc_attr($styleDecl) . '"' . $afterClass;
            },
            $blockContent,
            1
        );

        return is_string($updated) ? $updated : $blockContent;
    }

    /**
     * Emit CSS that zeros padding-top when two .novi-section siblings share novi-bg-*.
     * Unscoped adjacent sibling: works under .nectar-content, nectar_template_single__*,
     * entry-content, global sections, etc. Safe because .novi-section is opt-in on
     * top-level section roots only (not nested column/flex children).
     */
    public function enqueueAdjacentSectionCss(): void {
        if (is_admin()) {
            return;
        }

        //empty prefix = universal .novi-section.novi-bg-X + .novi-section.novi-bg-X
        $css = $this->buildCollapseCss([''], false);

        if ($css === '') {
            return;
        }

        $handle = Theme::TEXT_DOMAIN . '_novi_section_adjacent_bg';
        wp_register_style($handle, false, [], null);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, $css);
    }

    /**
     * Same collapse rules for the block editor canvas (iframe).
     * Classes come from novi-section-bg-editor.js (BlockListBlock), not render_block.
     * Scoped to the root container so nested editor chrome is not affected.
     */
    public function enqueueAdjacentSectionEditorCss(): void {
        if (!is_admin()) {
            return;
        }

        $css = $this->buildCollapseCss([
            '.editor-styles-wrapper .is-root-container > ',
            '.is-root-container > ',
        ], true);

        if ($css === '') {
            return;
        }

        $handle = Theme::TEXT_DOMAIN . '_novi_section_adjacent_bg_editor';
        wp_register_style($handle, false, [], null);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, $css);

        //nectar-editor-global + nectar-front-end-render are enqueued into the iframed canvas by NB
        foreach (['nectar-editor-global', 'nectar-front-end-render', 'wp-block-library'] as $iframeHandle) {
            if (wp_style_is($iframeHandle, 'registered') || wp_style_is($iframeHandle, 'enqueued')) {
                wp_add_inline_style($iframeHandle, $css);
                return;
            }
        }
    }

    /**
     * Build adjacent same-bg padding-collapse rules for a set of parent prefixes.
     * Pass [''] for unscoped front-end rules (all Gutenberg content wrappers).
     *
     * @param list<string> $parentPrefixes
     * @param bool $includeEditorDescendants zero padding on NB roots inside BlockListBlock wrappers
     * @return string
     */
    private function buildCollapseCss(array $parentPrefixes, bool $includeEditorDescendants): string {
        $slugs = $this->collectCollapseSlugs();
        if ($slugs === []) {
            return '';
        }

        $rules = [];
        foreach ($slugs as $slug) {
            $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
            if ($safe === null || $safe === '') {
                continue;
            }

            foreach ($parentPrefixes as $prefix) {
                $pair = $prefix . '.novi-section.novi-bg-' . $safe
                    . ' + .novi-section.novi-bg-' . $safe;

                $selectors = [
                    $pair,
                    $pair . ' > .nectar-blocks-row__wrapper',
                    $pair . ' > .nectar-blocks-row__inner',
                    $pair . ' > .nectar-blocks-flex-box__inner',
                    $pair . ' > .nectar-blocks-flex-box__wrapper',
                ];

                //editor: novi-bg-* lives on BlockListBlock; padding is on inner NB roots
                if ($includeEditorDescendants) {
                    $selectors[] = $pair . ' > .nectar-blocks-row';
                    $selectors[] = $pair . ' > .nectar-blocks-flex-box';
                    $selectors[] = $pair . ' > .nectar-blocks-column';
                    $selectors[] = $pair . ' .nectar-blocks-row__wrapper';
                    $selectors[] = $pair . ' .nectar-blocks-row__inner';
                    $selectors[] = $pair . ' .nectar-blocks-flex-box__inner';
                    $selectors[] = $pair . ' .nectar-blocks-flex-box__wrapper';
                }

                $rules[] = implode(',', $selectors) . '{padding-top:0!important}';
            }
        }

        return implode('', $rules);
    }

    /**
     * Resolve solid bg slug + CSS variable token, or null when not solid-matchable.
     *
     * @param array $attrs
     * @return array{slug: string, cssVar: string}|null
     */
    private function resolveBg(array $attrs): ?array {
        if ($this->hasBgImage($attrs)) {
            return null;
        }

        $bgColor = $attrs['bgColor'] ?? null;
        if (!is_array($bgColor)) {
            return $this->pageBg();
        }

        $desktop = $bgColor['desktop'] ?? null;
        if (!is_array($desktop) || $desktop === []) {
            return $this->pageBg();
        }

        $type = isset($desktop['type']) ? (string)$desktop['type'] : '';
        if ($type === 'gradient' || $type === 'global-gradient') {
            return null;
        }

        $gradientValue = isset($desktop['gradientValue']) ? trim((string)$desktop['gradientValue']) : '';
        if ($gradientValue !== '' && str_contains($gradientValue, 'gradient')) {
            return null;
        }

        $slug = '';
        if (!empty($desktop['solidGlobalColorData']) && is_array($desktop['solidGlobalColorData'])) {
            $slug = isset($desktop['solidGlobalColorData']['slug'])
                ? (string)$desktop['solidGlobalColorData']['slug']
                : '';
        }

        //White global shares page with unset sections
        if ($slug === self::WHITE_GLOBAL_SLUG) {
            return $this->pageBg();
        }

        if ($slug !== '') {
            $safe = sanitize_html_class($slug);
            if ($safe === '') {
                return null;
            }

            //css custom property name matches nectar token (var(--accentPrimary), var(--nectar-gc-…))
            return [
                'slug' => $safe,
                'cssVar' => 'var(--' . $safe . ')',
            ];
        }

        //empty / unknown solid without a named slug → page (matches unset)
        if ($type === '' || $type === 'solid' || $type === 'global-solid') {
            return $this->pageBg();
        }

        return null;
    }

    /**
     * Shared token for unset + White global (nectar-gc-g7gf2xnFzd).
     *
     * @return array{slug: string, cssVar: string}
     */
    private function pageBg(): array {
        return [
            'slug' => self::PAGE_SLUG,
            'cssVar' => 'var(--' . self::WHITE_GLOBAL_SLUG . ')',
        ];
    }

    /**
     * @param array $attrs
     * @return bool
     */
    private function hasBgImage(array $attrs): bool {
        $bgImage = $attrs['bgImage'] ?? null;
        if (!is_array($bgImage)) {
            return false;
        }

        $desktop = $bgImage['desktop'] ?? null;
        if (!is_array($desktop)) {
            return false;
        }

        $url = isset($desktop['url']) ? trim((string)$desktop['url']) : '';
        $id = isset($desktop['id']) ? (int)$desktop['id'] : 0;

        return $url !== '' || $id > 0;
    }

    /**
     * @return list<string>
     */
    private function collectCollapseSlugs(): array {
        $slugs = [self::PAGE_SLUG];
        $colors = get_option('nectar_global_colors', []);
        if (!is_array($colors)) {
            return $slugs;
        }

        foreach (['coreSolids', 'userSolids'] as $group) {
            if (empty($colors[$group]) || !is_array($colors[$group])) {
                continue;
            }
            foreach ($colors[$group] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $raw = isset($row['slug']) ? (string)$row['slug'] : '';
                if ($raw === '' || $raw === self::WHITE_GLOBAL_SLUG) {
                    continue;
                }
                $slug = sanitize_html_class($raw);
                if ($slug === '') {
                    continue;
                }
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }
}
