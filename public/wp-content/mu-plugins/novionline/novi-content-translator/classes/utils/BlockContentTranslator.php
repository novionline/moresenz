<?php

namespace NoviOnline\ContentTranslator\Core;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\InternalLinkTranslator;

/**
 * Class BlockContentTranslator
 * Translates Gutenberg block content in post_content
 * @package NoviOnline\ContentTranslator\Core
 */
class BlockContentTranslator
{
    /**
     * Last translation error for the current request.
     */
    private static ?string $lastError = null;

    /**
     * Current target language slug/locale for the running translation pass.
     * Used by helpers that need language context during apply/sync steps.
     */
    private static ?string $currentTargetLang = null;

    /**
     * Current source language slug/locale for the running translation pass.
     * Used by helpers that need language context during apply/sync steps.
     */
    private static ?string $currentSourceLang = null;

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    private static function setLastError(?string $error): void
    {
        self::$lastError = $error !== null ? trim((string) $error) : null;
    }
    /**
     * Runtime reusable block ref overrides for current request, keyed by target language slug.
     * Structure: [langSlug => [sourceRef => targetRef]]
     *
     * @var array<string, array<int, int>>
     */
    private static array $runtimeReusableRefMapByLang = [];

    /**
     * When true, internal link rewrites are strict (no language-switch/slug guessing).
     * Enabled only during sync-links-only runs.
     */
    private static bool $strictLinkRewrite = false;

    /**
     * Gutenberg serialize_block_attributes() only emits these \u00XX escapes.
     * Matching arbitrary u+4hex falsely hits words ("houdbaar"→udbaa) and block IDs ("block-u605b…").
     *
     * @see serialize_block_attributes() in wp-includes/blocks.php
     */
    private const GUTENBERG_UNICODE_ESCAPE_HEX = '3[cCeE]|22|26|2[dD]|5[cC]';

    /**
     * Detect Gutenberg JSON unicode escapes that lost their leading backslash.
     * Example broken form in block attrs: content":"u003cemu003e..." (should be "\u003cem\u003e...").
     * Saving serialized blocks via wp_update_post()/wp_insert_post() without wp_slash() causes this.
     */
    public static function hasLostUnicodeEscapeBackslashes(string $value): bool
    {
        if ($value === '' || stripos($value, 'u00') === false) {
            return false;
        }

        return (bool) preg_match('/(?<!\\\\)u00(?:' . self::GUTENBERG_UNICODE_ESCAPE_HEX . ')/', $value);
    }

    /**
     * Count lost Gutenberg unicode-escape symptoms (bare u003c / u0022 / …).
     *
     * @return array<string, int>
     */
    public static function countLostUnicodeEscapeSymptoms(string $value): array
    {
        $symptoms = [
            'u003c' => 0,
            'u003e' => 0,
            'u0022' => 0,
            'u0026' => 0,
            'u002d' => 0,
            'u005c' => 0,
        ];
        if ($value === '' || stripos($value, 'u00') === false) {
            return $symptoms;
        }

        foreach (array_keys($symptoms) as $token) {
            $symptoms[$token] = (int) preg_match_all('/(?<!\\\\)' . $token . '/i', $value);
        }

        return $symptoms;
    }

    /**
     * Restore lost backslashes before Gutenberg JSON unicode escapes in serialized block markup.
     * "u003c" / "u0022" → "\u003c" / "\u0022". Do not decode to raw characters here:
     * decoding u0022 to a quote would break the surrounding JSON attribute string.
     * Only touches escapes that serialize_block_attributes() emits — leaves block IDs / prose alone.
     */
    public static function repairLostUnicodeEscapeBackslashes(string $value): string
    {
        return self::normalizeLostBackslashUnicodeEscapes($value);
    }

    /**
     * @see repairLostUnicodeEscapeBackslashes()
     */
    private static function normalizeLostBackslashUnicodeEscapes(string $value): string
    {
        if ($value === '' || stripos($value, 'u00') === false) {
            return $value;
        }

        //restore "\u00XX" so serialize_blocks / Gutenberg JSON stays valid (especially u0022 quotes)
        $out = preg_replace(
            '/(?<!\\\\)u00(' . self::GUTENBERG_UNICODE_ESCAPE_HEX . ')/',
            '\\\\u00$1',
            $value
        );

        return is_string($out) && $out !== '' ? $out : $value;
    }

    /**
     * DeepL API limit: max texts per request (we chunk above this)
     * @see https://developers.deepl.com/docs/resources/usage-limits
     */
    private const DEEPL_BATCH_SIZE = 50;

    /**
     * DeepL API limit: total request size 128 KiB; we chunk by content size to stay under it
     * Use 100 KiB to leave room for JSON/request overhead
     * @see https://developers.deepl.com/docs/resources/usage-limits
     */
    private const DEEPL_MAX_REQUEST_BYTES = 100 * 1024;

    /**
     * ACF field type handlers: extract(data, key) -> string, apply(data, key, translated) -> void
     * @var array<string, array{extract: callable, apply: callable}>
     */
    private static array $acfFieldHandlers = [];

    /**
     * Block translation configurations
     * Each block type can have its own translation strategy
     *
     * @var array<string, array{
     *     strategy: 'regex'|'xpath'|'callback'|'attributes'|'html'|'acf_fields',
     *     rules?: array,
     *     extract?: callable,
     *     replace?: callable,
     *     fields?: array,
     *     repeater?: string,
     *     subfields?: array
     * }>
     */
    private static array $blockConfigs = [
        'core/paragraph' => [
            'strategy' => 'html',
        ],
        'core/list-item' => [
            'strategy' => 'html',
        ],
        'nectar-blocks/text' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'content',
            ],
            'sync' => [
                [
                    'type' => 'emit_wrapper',
                    'tag_attr' => 'textElement',
                    'base_classes' => ['wp-block-nectar-blocks-text', 'nectar-blocks-text'],
                    'id_attrs' => ['customId', 'blockId'],
                    'append_typography_class_from_attr' => 'typography',
                    'append_class_name_from_attr' => 'className',
                ],
            ],
        ],
        'nectar-blocks/button' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'text',
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-button__text',
                    'field_index' => 0,
                ],
            ],
        ],
        'nectar-blocks/flex-box' => [
            'strategy' => 'attrs_first',
            'always_sync' => true,
            'fields' => [
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'rewrite_link_only',
                    'attr_paths' => ['link.href.value', 'link.href'],
                ],
            ],
        ],
        'nectar-blocks/column' => [
            'strategy' => 'attrs_first',
            'always_sync' => true,
            'fields' => [
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'rewrite_link_only',
                    'attr_paths' => ['link.href.value', 'link.href'],
                ],
            ],
        ],
        'nectar-blocks/icon-list-item' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'title',
                'description.content',
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'replace_icon_list_item_title',
                    'content_class' => 'nectar-blocks-icon-list-item__content',
                    'desc_class' => 'nectar-blocks-icon-list-item__desc',
                    'field_index' => 0,
                    'rewrite_anchors' => true,
                ],
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-icon-list-item__desc',
                    'field_index' => 1,
                ],
            ],
        ],
        'nectar-blocks/accordion-section' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'title',
            ],
            'sync' => [
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-accordion-section__title__text',
                ],
            ],
        ],
        'nectar-blocks/image' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'image.alt',
                'image.title',
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'replace_img_attr',
                    'attr' => 'alt',
                    'field_index' => 0,
                ],
                [
                    'type' => 'replace_img_attr',
                    'attr' => 'title',
                    'field_index' => 1,
                ],
            ],
        ],
        'nectar-blocks/icon' => [
            'strategy' => 'attrs_first',
            'always_sync' => true,
            'fields' => [
                'icon.iconCustom.alt',
                'ariaLabel',
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'replace_img_attr',
                    'attr' => 'alt',
                    'field_index' => 0,
                ],
                [
                    //remix/library icons render aria-label on the icon span (not <img>)
                    'type' => 'replace_html_attr',
                    'attr' => 'aria-label',
                    'attr_path' => 'ariaLabel',
                ],
            ],
        ],
        'nectar-blocks/milestone' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'text',
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'div',
                    'class' => 'nectar-blocks-milestone__content',
                    'field_index' => 0,
                ],
                [
                    'type' => 'rewrite_link_only',
                    'attr_paths' => ['link.href.value', 'link.href'],
                ],
            ],
        ],
        'nectar-blocks/search' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'placeholder',
            ],
            'sync' => [
                [
                    'type' => 'replace_input_placeholder',
                    'field_index' => 0,
                ],
            ],
        ],
        'nectar-blocks/video-player' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'playButtonLabel',
            ],
            'sync' => [
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-video-player__center-play-label',
                    'field_index' => 0,
                ],
            ],
        ],
        'nectar-blocks/video-lightbox' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'text.content',
            ],
            'sync' => [
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-video-lightbox__play-button__text',
                    'field_index' => 0,
                ],
            ],
        ],
        'nectar-blocks/header-action-account' => [
            'strategy' => 'attrs_first',
            'always_sync' => true,
            'fields' => [
                'link.screenReaderText',
            ],
            'sync' => [
                [
                    'type' => 'rewrite_link_only',
                    'attr_paths' => ['link.href.value', 'link.href'],
                ],
            ],
        ],
        'nectar-blocks/testimonial' => [
            'strategy' => 'attrs_first',
            'fields' => [
                'quoteText',
                'subtitleText',
                'authorImage.image.alt',
                'authorImage.image.title',
            ],
            'sync' => [
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'p',
                    'class' => 'nectar-blocks-testimonial__quote',
                    'field_index' => 0,
                ],
                [
                    // nameText is intentionally NOT translated, but can contain inline <a href="..."> links.
                    // Keep markup aligned with attrs and allow link rewriting within the HTML fragment.
                    'type' => 'replace_tag_inner_html_from_attr',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-testimonial__author-info--name',
                    'attr_path' => 'nameText',
                    'rewrite_anchors' => true,
                ],
                [
                    'type' => 'replace_tag_inner_html',
                    'tag' => 'span',
                    'class' => 'nectar-blocks-testimonial__author-info--subtitle',
                    'field_index' => 1,
                ],
                [
                    'type' => 'replace_img_attr',
                    'attr' => 'alt',
                    'field_index' => 2,
                ],
                [
                    'type' => 'replace_img_attr',
                    'attr' => 'title',
                    'field_index' => 3,
                ],
            ],
        ],
        'nectar-blocks/table-of-contents' => [
            'strategy' => 'callback',
            'extract' => [self::class, 'extractTableOfContentsTexts'],
            'replace' => [self::class, 'replaceTableOfContentsTexts'],
        ],
        'nectar-blocks/tabs' => [
            'strategy' => 'callback',
            'extract' => [self::class, 'extractTabsTexts'],
            'replace' => [self::class, 'replaceTabsTexts'],
        ],
        'nectar-blocks/scrolling-marquee' => [
            'strategy' => 'callback',
            'extract' => [self::class, 'extractScrollingMarqueeTexts'],
            'replace' => [self::class, 'replaceScrollingMarqueeTexts'],
        ],
        'nectar-blocks/image-grid' => [
            'strategy' => 'callback',
            'extract' => [self::class, 'extractImageGalleryMetadataTexts'],
            'replace' => [self::class, 'replaceImageGridMetadataTexts'],
        ],
        'nectar-blocks/image-gallery' => [
            'strategy' => 'callback',
            'extract' => [self::class, 'extractImageGalleryMetadataTexts'],
            'replace' => [self::class, 'replaceImageGalleryMetadataTexts'],
        ],
        'acf/block-button' => [
            'strategy' => 'acf_fields',
            'fields' => [
                ['type' => 'repeater', 'repeater' => 'buttons', 'subfields' => [
                    ['key' => 'button_link', 'type' => 'link_title'],
                ]],
            ],
        ],
        'acf/block-collapsible-content' => [
            'strategy' => 'acf_fields',
            'fields' => [
                ['key' => 'collapsible_content_heading', 'type' => 'string'],
            ],
        ],
        'acf/block-icons-with-text' => [
            'strategy' => 'acf_fields',
            'fields' => [
                ['type' => 'repeater', 'repeater' => 'icons', 'subfields' => [
                    ['key' => 'title', 'type' => 'string'],
                    ['key' => 'description', 'type' => 'string'],
                ]],
            ],
        ],
        'acf/block-team-cta' => [
            'strategy' => 'acf_fields',
            'fields' => [
                ['key' => 'cta_text', 'type' => 'string'],
            ],
        ],
        'acf/block-novi-menu' => [
            'strategy' => 'acf_fields',
            'fields' => [
                ['key' => 'heading', 'type' => 'string'],
            ],
        ],
    ];

    /**
     * Supported blocks that currently have no translatable strings.
     * Kept separate so support state can be managed independently from translation logic.
     *
     * Notes on intentional skips:
     * - `gravityforms/form` has no translatable text attrs; `formId` is remapped to the locale-suffixed
     *   target form in transformBlocksForTargetLanguage (title suffix e.g. "Contact - NL" -> "Contact - EN").
     * - `yoast-seo/breadcrumbs` renders final HTML (and any links) at runtime.
     *   It does not store stable href/post/term references in block attrs that we can safely rewrite.
     * - Video source/poster URLs on `nectar-blocks/video-lightbox` and `nectar-blocks/video-player` are not rewritten
     *   (often external providers, local media, or embeds). Play labels / lightbox text are translated.
     * - `nectar-blocks/navigation` / `megamenu` / `ticker*` copy lives in InnerBlocks or linked sections, not NB string attrs.
     * - `nectar-blocks/world-time` `city` is a timezone id, not UI prose.
     *
     * Hook name: nct_supported_no_text_blocks
     *
     * @var array<int, string>
     */
    private static array $supportedNoTextBlocks = [
        'nectar-blocks/accordion',
        'nectar-blocks/tab-section',
        'nectar-blocks/taxonomy-grid',
        'nectar-blocks/taxonomy-terms',
        'nectar-blocks/star-rating',
        'nectar-blocks/icon-list',
        'nectar-blocks/divider',
        'nectar-blocks/row',
        'nectar-blocks/column',
        'nectar-blocks/flex-box',
        'nectar-blocks/carousel',
        'nectar-blocks/carousel-item',
        'nectar-blocks/post-content',
        'nectar-blocks/post-grid',
        'nectar-blocks/navigation',
        'nectar-blocks/megamenu',
        'nectar-blocks/ticker',
        'nectar-blocks/ticker-item',
        'nectar-blocks/header-actions',
        'nectar-blocks/world-time',
        'novi/article-authors',
        'novi/connected-team-members',
        'gravityforms/form',
        'yoast-seo/breadcrumbs',
        'core/list',
        'core/block',
    ];

    /**
     * Attrs-first helpers for blocks where save() output depends on attributes.
     */
    private static function getAttrStringByPath(array $attrs, string $path): string
    {
        $parts = explode('.', $path);
        $value = $attrs;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return '';
            }
            $value = $value[$part];
        }
        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    private static function setAttrStringByPath(array &$attrs, string $path, string $value): void
    {
        $parts = explode('.', $path);
        $ref = &$attrs;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;
                return;
            }
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
    }

    /**
     * Rewrite internal URL stored in block attrs and keep first anchor href in markup synced.
     *
     * @param array $block
     * @param string $innerHTML
     * @param array<int, string> $attrPaths
     * @return array{block: array, innerHTML: string, changed: bool}
     */
    private static function rewriteInternalLinkInAttrsAndMarkup(array $block, string $innerHTML, array $attrPaths): array
    {
        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            return ['block' => $block, 'innerHTML' => $innerHTML, 'changed' => false];
        }

        $currentHref = '';
        $matchedPath = '';
        foreach ($attrPaths as $path) {
            $href = self::getAttrStringByPath($block['attrs'], (string) $path);
            if (trim($href) !== '') {
                $currentHref = $href;
                $matchedPath = (string) $path;
                break;
            }
        }

        if ($currentHref === '' || $matchedPath === '') {
            return ['block' => $block, 'innerHTML' => $innerHTML, 'changed' => false];
        }

        $rewrite = InternalLinkTranslator::rewriteInternalUrl(
            $currentHref,
            (string) (self::$currentTargetLang ?? ''),
            (string) (self::$currentSourceLang ?? ''),
            [
                'strict' => self::$strictLinkRewrite,
            ]
        );
        $newHref = is_array($rewrite) && isset($rewrite['url']) && is_string($rewrite['url']) ? $rewrite['url'] : '';
        if ($newHref !== '') {
            $sourceParts = wp_parse_url($currentHref);
            $targetParts = wp_parse_url($newHref);
            $sourceQuery = is_array($sourceParts) && isset($sourceParts['query']) ? (string) $sourceParts['query'] : '';
            $sourceFragment = is_array($sourceParts) && isset($sourceParts['fragment']) ? (string) $sourceParts['fragment'] : '';
            $targetHasQuery = is_array($targetParts) && isset($targetParts['query']) && (string) $targetParts['query'] !== '';
            $targetHasFragment = is_array($targetParts) && isset($targetParts['fragment']) && (string) $targetParts['fragment'] !== '';
            if ($sourceQuery !== '' && !$targetHasQuery) {
                $newHref .= (strpos($newHref, '?') === false ? '?' : '&') . $sourceQuery;
            }
            if ($sourceFragment !== '' && !$targetHasFragment) {
                $newHref .= '#' . $sourceFragment;
            }
        }
        if ($newHref === '' || $newHref === $currentHref) {
            return ['block' => $block, 'innerHTML' => $innerHTML, 'changed' => false];
        }

        // Update all configured href paths that currently point to old href.
        foreach ($attrPaths as $path) {
            $existing = self::getAttrStringByPath($block['attrs'], (string) $path);
            if ($existing !== '' && $existing === $currentHref) {
                self::setAttrStringByPath($block['attrs'], (string) $path, $newHref);
            }
        }

        // Update markup href on first anchor in saved HTML.
        $hrefPattern = '/(<a\\b[^>]*\\bhref=\")([^\"]*)(\"[^>]*>)/i';
        $updatedHtml = preg_replace($hrefPattern, '${1}' . esc_attr($newHref) . '${3}', $innerHTML, 1, $hrefCount);
        if (is_string($updatedHtml) && $hrefCount > 0) {
            $innerHTML = $updatedHtml;
        }

        return ['block' => $block, 'innerHTML' => $innerHTML, 'changed' => true];
    }

    private static function nectarTypographyClass(string $typography): string
    {
        $typography = trim($typography);
        if ($typography === '') {
            return '';
        }
        return strpos($typography, 'nectar-gt') !== false ? $typography : ('nectar-font-' . $typography);
    }

    private static function getBlockInnerHtml(array $block): string
    {
        $innerHTML = (string) ($block['innerHTML'] ?? '');
        if ($innerHTML !== '') {
            return $innerHTML;
        }
        $innerContent = $block['innerContent'] ?? [];
        if (is_array($innerContent) && $innerContent !== []) {
            //when inner blocks exist, preserve their positions so we can map changes back
            if (!empty($block['innerBlocks'])) {
                $placeholder = '<!-- INNER_BLOCK_PLACEHOLDER -->';
                return implode('', array_map(function ($item) use ($placeholder) {
                    return is_string($item) ? $item : $placeholder;
                }, $innerContent));
            }

            return implode('', array_filter($innerContent, function ($item) {
                return is_string($item);
            }));
        }
        return '';
    }

    private static function setBlockInnerHtml(array &$block, string $html): void
    {
        $block['innerHTML'] = $html;
        if (empty($block['innerBlocks'])) {
            $block['innerContent'] = [$html];
            return;
        }
        // For blocks with inner blocks, we must keep null placeholders *in the right places*
        // or Gutenberg validation can fail (notably for nectar-blocks/accordion-section).
        $placeholder = '<!-- INNER_BLOCK_PLACEHOLDER -->';
        $parts = explode($placeholder, $html);
        $innerBlocksCount = is_array($block['innerBlocks']) ? count($block['innerBlocks']) : 0;

        // If placeholder count doesn't match innerBlocks count, don't try to get clever.
        if ($innerBlocksCount <= 0 || count($parts) !== $innerBlocksCount + 1) {
            $block['innerContent'] = $block['innerContent'] ?? [$html];
            return;
        }

        $newInnerContent = [];
        for ($i = 0; $i < $innerBlocksCount; $i++) {
            $newInnerContent[] = (string) ($parts[$i] ?? '');
            $newInnerContent[] = null;
        }
        $newInnerContent[] = (string) ($parts[$innerBlocksCount] ?? '');

        $block['innerContent'] = $newInnerContent;
    }

    /**
     * Guard DOM-dependent sync paths for hosts without ext-dom enabled.
     * @return bool
     */
    private static function hasDomSupport(): bool
    {
        return class_exists('\DOMDocument') && class_exists('\DOMXPath');
    }

    private static function syncAttrsFirstBlock(array $block, array $config, array $originals, array $translated): array
    {
        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            $block['attrs'] = [];
        }

        $fields = $config['fields'] ?? [];
        foreach ($fields as $i => $path) {
            $t = isset($translated[$i]) ? trim((string) $translated[$i]) : '';
            if ($t === '') {
                continue;
            }
            //nectar text can contain entity-encoded literal angle brackets which can become double-encoded (e.g. &amp;gt)
            if (($block['blockName'] ?? '') === 'nectar-blocks/text' && (string) $path === 'content') {
                if (stripos($t, '&amp;gt') !== false || stripos($t, '&amp;lt') !== false || stripos($t, '&lt') !== false || stripos($t, '&gt') !== false) {
                    self::debugLog('nectar text: attrs.content normalization before', [
                        'blockId' => $block['attrs']['blockId'] ?? null,
                        'valuePreview' => substr($t, 0, 160),
                    ]);
                }
                $t = self::escapeLiteralAngleBracketsPreserveInlineTags($t);
                if (stripos($t, '&amp;gt') !== false || stripos($t, '&amp;lt') !== false || stripos($t, '&lt') !== false || stripos($t, '&gt') !== false) {
                    self::debugLog('nectar text: attrs.content normalization after', [
                        'blockId' => $block['attrs']['blockId'] ?? null,
                        'valuePreview' => substr($t, 0, 160),
                    ]);
                }
            }
            self::setAttrStringByPath($block['attrs'], (string) $path, $t);
        }

        $innerHTML = self::getBlockInnerHtml($block);
        $syncRules = $config['sync'] ?? [];
        $matchedAny = false;

        foreach ($syncRules as $rule) {
            if (!is_array($rule) || !isset($rule['type'])) {
                continue;
            }
            $type = (string) $rule['type'];

            if ($type === 'rewrite_link_only') {
                $attrPaths = $rule['attr_paths'] ?? ['link.href.value', 'link.href'];
                if (!is_array($attrPaths)) {
                    $attrPaths = ['link.href.value', 'link.href'];
                }
                $rewriteResult = self::rewriteInternalLinkInAttrsAndMarkup($block, $innerHTML, $attrPaths);
                $block = $rewriteResult['block'];
                $innerHTML = $rewriteResult['innerHTML'];
                if (!empty($rewriteResult['changed'])) {
                    // For blocks with innerBlocks, setBlockInnerHtml() may preserve existing innerContent
                    // when placeholders are not present in reconstructed HTML. Ensure wrapper href is also
                    // updated directly in innerContent string fragments to keep serialized markup aligned.
                    if (!empty($block['innerBlocks']) && isset($block['innerContent']) && is_array($block['innerContent'])) {
                        $newHref = '';
                        foreach ($attrPaths as $path) {
                            $candidate = self::getAttrStringByPath($block['attrs'], (string) $path);
                            if (trim($candidate) !== '') {
                                $newHref = $candidate;
                                break;
                            }
                        }
                        if ($newHref !== '') {
                            $hrefPattern = '/(<a\\b[^>]*\\bhref=\")([^\"]*)(\"[^>]*>)/i';
                            foreach ($block['innerContent'] as $idx => $part) {
                                if (!is_string($part) || $part === '') {
                                    continue;
                                }
                                $replacedPart = preg_replace($hrefPattern, '${1}' . esc_attr($newHref) . '${3}', $part, 1, $count);
                                if (is_string($replacedPart) && $count > 0) {
                                    $block['innerContent'][$idx] = $replacedPart;
                                    break;
                                }
                            }
                            $block['innerHTML'] = implode('', array_map(function ($item) {
                                return is_string($item) ? $item : '';
                            }, $block['innerContent']));
                        }
                    }
                    $matchedAny = true;
                }
                continue;
            }

            if ($type === 'emit_wrapper') {
                $originalInnerHTML = $innerHTML;
                $tagAttr = (string) ($rule['tag_attr'] ?? '');
                $tag = $tagAttr !== '' ? strtolower((string) ($block['attrs'][$tagAttr] ?? '')) : '';
                if ($tag === '') {
                    $tag = 'p';
                }
                $allowedTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span'];
                if (!in_array($tag, $allowedTags, true)) {
                    $tag = 'p';
                }

                $id = '';
                $idAttrs = $rule['id_attrs'] ?? [];
                if (is_array($idAttrs)) {
                    foreach ($idAttrs as $key) {
                        if (isset($block['attrs'][$key]) && is_string($block['attrs'][$key]) && trim($block['attrs'][$key]) !== '') {
                            $id = trim($block['attrs'][$key]);
                            break;
                        }
                    }
                }

                $classParts = $rule['base_classes'] ?? [];
                if (!is_array($classParts)) {
                    $classParts = [];
                }
                $typographyAttr = (string) ($rule['append_typography_class_from_attr'] ?? '');
                if ($typographyAttr !== '' && isset($block['attrs'][$typographyAttr]) && is_string($block['attrs'][$typographyAttr])) {
                    $typoClass = self::nectarTypographyClass($block['attrs'][$typographyAttr]);
                    if ($typoClass !== '') {
                        $classParts[] = $typoClass;
                    }
                }
                $classNameAttr = (string) ($rule['append_class_name_from_attr'] ?? '');
                if ($classNameAttr !== '' && isset($block['attrs'][$classNameAttr]) && is_string($block['attrs'][$classNameAttr]) && trim($block['attrs'][$classNameAttr]) !== '') {
                    $classParts[] = trim($block['attrs'][$classNameAttr]);
                }

                // Preserve any extra classes that were present on the original wrapper.
                // NectarBlocks can add animation-related classes (e.g. text highlight) that we don't model in attrs_first.
                $originalWrapperAttrs = self::extractOpeningTagAttributes($originalInnerHTML, $tag);
                $originalClassAttr = is_array($originalWrapperAttrs) ? (string) ($originalWrapperAttrs['class'] ?? '') : '';
                if ($originalClassAttr !== '') {
                    $originalClasses = preg_split('/\s+/', trim($originalClassAttr)) ?: [];
                    foreach ($originalClasses as $c) {
                        $c = trim((string) $c);
                        if ($c !== '') {
                            $classParts[] = $c;
                        }
                    }
                }

                $classParts = array_values(array_filter(array_unique(array_map('trim', $classParts))));

                $content = isset($translated[0]) ? trim((string) $translated[0]) : '';
                if ($content === '') {
                    $content = trim((string) ($originals[0] ?? ''));
                }

                // Preserve unknown attributes from the original wrapper so future NectarBlocks
                // features (like animations via data-*) don't break validation when we re-emit.
                $preservedAttrs = self::extractWrapperAttributesForEmitWrapper($originalInnerHTML, $tag);

                // Rewrite internal links in nectar text content before escaping angle brackets.
                // This targets translated content in `attrs.content`, which can contain real <a href="..."> tags.
                if (($block['blockName'] ?? '') === 'nectar-blocks/text') {
                    $rewrite = InternalLinkTranslator::rewriteAnchorsInHtmlFragment(
                        (string) $content,
                        (string) (self::$currentTargetLang ?? ''),
                        [
                            'prefer_anchor_id' => true,
                            'update_anchor_id' => true,
                            'source_lang' => (string) (self::$currentSourceLang ?? ''),
                            'strict' => self::$strictLinkRewrite,
                        ]
                    );
                    if (is_array($rewrite) && isset($rewrite['html']) && is_string($rewrite['html'])) {
                        $content = $rewrite['html'];
                    }
                }

                //escape literal angle brackets in user content but keep basic inline tags
                if (
                    ($block['blockName'] ?? '') === 'nectar-blocks/text'
                    && (stripos($content, '&lt') !== false || stripos($content, '&gt') !== false || stripos($content, '&amp;gt') !== false || stripos($content, '&amp;lt') !== false)
                ) {
                    self::debugLog('nectar text: angle bracket normalization before', [
                        'blockId' => $block['attrs']['blockId'] ?? null,
                        'contentPreview' => substr($content, 0, 160),
                    ]);
                }
                $content = self::escapeLiteralAngleBracketsPreserveInlineTags($content);
                if (
                    ($block['blockName'] ?? '') === 'nectar-blocks/text'
                    && (stripos($content, '&lt') !== false || stripos($content, '&gt') !== false || stripos($content, '&amp;gt') !== false || stripos($content, '&amp;lt') !== false)
                ) {
                    self::debugLog('nectar text: angle bracket normalization after', [
                        'blockId' => $block['attrs']['blockId'] ?? null,
                        'contentPreview' => substr($content, 0, 160),
                    ]);
                }

                // Keep serialized comment attrs in sync with emitted HTML.
                // NectarBlocks `save()` uses attrs.content, so if we rewrite links in the content HTML
                // we must also update the attribute; otherwise Gutenberg block validation fails.
                if (($block['blockName'] ?? '') === 'nectar-blocks/text') {
                    self::setAttrStringByPath($block['attrs'], 'content', (string) $content);
                }

                $idAttr = $id !== '' ? ' id="' . esc_attr($id) . '"' : '';
                $classAttr = $classParts !== [] ? ' class="' . esc_attr(implode(' ', $classParts)) . '"' : '';
                $extraAttrs = self::buildPreservedAttributesHtml($preservedAttrs);
                $innerHTML = '<' . $tag . $idAttr . $classAttr . $extraAttrs . '>' . $content . '</' . $tag . '>';
                $matchedAny = true;
                continue;
            }

            if ($type === 'replace_tag_inner_html') {
                $tag = (string) ($rule['tag'] ?? 'span');
                $class = (string) ($rule['class'] ?? '');
                if ($class === '') {
                    continue;
                }

                // Extra: rewrite internal button link URLs (keep attrs + markup in sync)
                if (($block['blockName'] ?? '') === 'nectar-blocks/button' && $class === 'nectar-blocks-button__text') {
                    $rewriteResult = self::rewriteInternalLinkInAttrsAndMarkup($block, $innerHTML, ['link.href.value']);
                    $block = $rewriteResult['block'];
                    $innerHTML = $rewriteResult['innerHTML'];
                    if (!empty($rewriteResult['changed'])) {
                        $matchedAny = true;
                    }
                }

                $fieldIndex = isset($rule['field_index']) ? (int) $rule['field_index'] : 0;
                $newInner = isset($translated[$fieldIndex]) ? (string) $translated[$fieldIndex] : '';
                if ($newInner === '') {
                    continue;
                }

                // Testimonial fields can contain inline anchors that should be rewritten.
                if (($block['blockName'] ?? '') === 'nectar-blocks/testimonial') {
                    $rewrite = InternalLinkTranslator::rewriteAnchorsInHtmlFragment(
                        (string) $newInner,
                        (string) (self::$currentTargetLang ?? ''),
                        [
                            'prefer_anchor_id' => true,
                            'update_anchor_id' => true,
                            'source_lang' => (string) (self::$currentSourceLang ?? ''),
                        ]
                    );
                    if (is_array($rewrite) && isset($rewrite['html']) && is_string($rewrite['html'])) {
                        $newInner = (string) $rewrite['html'];

                        // Keep comment attrs in sync for these fields to avoid validation issues.
                        if ($class === 'nectar-blocks-testimonial__quote') {
                            self::setAttrStringByPath($block['attrs'], 'quoteText', $newInner);
                        } elseif ($class === 'nectar-blocks-testimonial__author-info--subtitle') {
                            self::setAttrStringByPath($block['attrs'], 'subtitleText', $newInner);
                        }
                    }
                }
                $pattern = '/(<'.preg_quote($tag, '/').'\\b[^>]*class="[^"]*\\b'.preg_quote($class, '/').'\\b[^"]*"[^>]*>)(.*?)(<\\/'.preg_quote($tag, '/').'>)/s';

                // For blocks with inner blocks (notably nectar accordion sections), update the relevant
                // innerContent string fragment in place so we don't disturb placeholder ordering.
                if (
                    !empty($block['innerBlocks'])
                    && isset($block['innerContent'])
                    && is_array($block['innerContent'])
                ) {
                    foreach ($block['innerContent'] as $idx => $part) {
                        if (!is_string($part) || $part === '') {
                            continue;
                        }
                        //use ${n} to avoid ambiguity when replacement starts with digits (e.g. phone numbers)
                        $replacedPart = preg_replace($pattern, '${1}' . $newInner . '${3}', $part, 1, $count);
                        if (is_string($replacedPart) && $count > 0) {
                            $block['innerContent'][$idx] = $replacedPart;
                            $block['innerHTML'] = implode('', array_map(function ($item) {
                                return is_string($item) ? $item : '';
                            }, $block['innerContent']));
                            $matchedAny = true;
                            break;
                        }
                    }
                    continue;
                }
                //use ${n} to avoid ambiguity when replacement starts with digits (e.g. phone numbers)
                $replaced = preg_replace($pattern, '${1}' . $newInner . '${3}', $innerHTML, 1, $count);
                if (is_string($replaced) && $count > 0) {
                    $innerHTML = $replaced;
                    $matchedAny = true;
                }
                continue;
            }

            if ($type === 'replace_input_placeholder') {
                $fieldIndex = isset($rule['field_index']) ? (int) $rule['field_index'] : 0;
                $newPlaceholder = isset($translated[$fieldIndex]) ? (string) $translated[$fieldIndex] : '';
                if ($newPlaceholder === '') {
                    continue;
                }
                $escaped = htmlspecialchars($newPlaceholder, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                //nb search (popup mode) renders the placeholder on multiple <input> nodes
                $replaced = preg_replace(
                    '/(<input\\b[^>]*\\bplaceholder=")([^"]*)(")/i',
                    '${1}' . $escaped . '${3}',
                    $innerHTML,
                    -1,
                    $count
                );
                if (is_string($replaced) && $count > 0) {
                    $innerHTML = $replaced;
                    $matchedAny = true;
                }
                continue;
            }

            if ($type === 'replace_html_attr') {
                $attr = (string) ($rule['attr'] ?? '');
                if ($attr === '') {
                    continue;
                }

                $newValue = '';
                $attrPath = (string) ($rule['attr_path'] ?? '');
                if ($attrPath !== '') {
                    $newValue = self::getAttrStringByPath($block['attrs'], $attrPath);
                } else {
                    $fieldIndex = isset($rule['field_index']) ? (int) $rule['field_index'] : 0;
                    $newValue = isset($translated[$fieldIndex]) ? (string) $translated[$fieldIndex] : '';
                }
                if (trim($newValue) === '') {
                    continue;
                }

                $escaped = esc_attr($newValue);
                $limit = isset($rule['limit']) ? (int) $rule['limit'] : -1;
                $pattern = '/(\\s' . preg_quote($attr, '/') . '=")([^"]*)(")/i';
                $replaced = preg_replace($pattern, '${1}' . $escaped . '${3}', $innerHTML, $limit, $count);
                if (is_string($replaced) && $count > 0) {
                    $innerHTML = $replaced;
                    $matchedAny = true;
                }
                continue;
            }

            if ($type === 'replace_icon_list_item_title') {
                $contentClass = (string) ($rule['content_class'] ?? '');
                $descClass = (string) ($rule['desc_class'] ?? '');
                if ($contentClass === '') {
                    continue;
                }

                // Extra: rewrite internal icon-list-item link URLs (keep attrs + markup in sync)
                if (($block['blockName'] ?? '') === 'nectar-blocks/icon-list-item') {
                    $rewriteResult = self::rewriteInternalLinkInAttrsAndMarkup($block, $innerHTML, ['link.href.value', 'link.href']);
                    $block = $rewriteResult['block'];
                    $innerHTML = $rewriteResult['innerHTML'];
                    if (!empty($rewriteResult['changed'])) {
                        $matchedAny = true;
                    }
                }

                $fieldIndex = isset($rule['field_index']) ? (int) $rule['field_index'] : 0;
                $newTitle = isset($translated[$fieldIndex]) ? (string) $translated[$fieldIndex] : '';
                if ($newTitle === '') {
                    $newTitle = self::getAttrStringByPath($block['attrs'] ?? [], 'title');
                }
                if ($newTitle === '') {
                    continue;
                }

                //rewrite inline anchors inside title HTML (attrs + content markup)
                if (!empty($rule['rewrite_anchors'])) {
                    $rewrite = InternalLinkTranslator::rewriteAnchorsInHtmlFragment(
                        (string) $newTitle,
                        (string) (self::$currentTargetLang ?? ''),
                        [
                            'prefer_anchor_id' => true,
                            'update_anchor_id' => true,
                            'source_lang' => (string) (self::$currentSourceLang ?? ''),
                            'strict' => self::$strictLinkRewrite,
                        ]
                    );
                    if (is_array($rewrite) && isset($rewrite['html']) && is_string($rewrite['html'])) {
                        $newTitle = (string) $rewrite['html'];
                        self::setAttrStringByPath($block['attrs'], 'title', $newTitle);
                    }
                }

                //replace the content inside the content div up to (but not including) desc span if present
                $pattern = '/(<div\\b[^>]*class="[^"]*\\b'.preg_quote($contentClass, '/').'\\b[^"]*"[^>]*>)([\\s\\S]*?)(?=(<span\\b[^>]*class="[^"]*\\b'.preg_quote($descClass, '/').'\\b[^"]*"[^>]*>)|(<\\/div>))/';
                //use ${1} to avoid ambiguity when title starts with digits
                $replaced = preg_replace($pattern, '${1}' . $newTitle, $innerHTML, 1, $count);
                if (is_string($replaced) && $count > 0) {
                    $innerHTML = $replaced;
                    $matchedAny = true;
                }
                continue;
            }

            if ($type === 'replace_img_attr') {
                // Extra: rewrite internal image link URLs (keep attrs + markup in sync).
                // Run once per image block (on the first img-attr sync rule) to avoid duplicate work.
                if (
                    in_array(($block['blockName'] ?? ''), ['nectar-blocks/image', 'nectar-blocks/icon'], true)
                    && ((int) ($rule['field_index'] ?? -1) === 0)
                ) {
                    $rewriteResult = self::rewriteInternalLinkInAttrsAndMarkup($block, $innerHTML, ['link.href.value', 'link.href']);
                    $block = $rewriteResult['block'];
                    $innerHTML = $rewriteResult['innerHTML'];
                    if (!empty($rewriteResult['changed'])) {
                        $matchedAny = true;
                    }
                }

                $attr = (string) ($rule['attr'] ?? '');
                if ($attr === '') {
                    continue;
                }
                $fieldIndex = isset($rule['field_index']) ? (int) $rule['field_index'] : 0;
                $newValue = isset($translated[$fieldIndex]) ? (string) $translated[$fieldIndex] : '';
                if ($newValue === '') {
                    continue;
                }

                // Replace a specific attribute on the first <img ...> tag found.
                // Keep it conservative to avoid breaking NectarBlocks validation.
                $pattern = '/(<img\\b[^>]*?)\\s' . preg_quote($attr, '/') . '=\"[^\"]*\"([^>]*>)/i';
                $replacement = '${1} ' . $attr . '="' . esc_attr($newValue) . '"${2}';
                $replaced = preg_replace($pattern, $replacement, $innerHTML, 1, $count);

                // If the attribute is missing, inject it right after <img.
                if ((!is_string($replaced) || $count === 0)) {
                    $injectPattern = '/(<img\\b)/i';
                    $injectReplacement = '${1} ' . $attr . '="' . esc_attr($newValue) . '"';
                    $injected = preg_replace($injectPattern, $injectReplacement, $innerHTML, 1, $injectCount);
                    if (is_string($injected) && $injectCount > 0) {
                        $innerHTML = $injected;
                        $matchedAny = true;
                    }
                    continue;
                }

                $innerHTML = $replaced;
                $matchedAny = true;
                continue;
            }

            if ($type === 'replace_tag_inner_html_from_attr') {
                $tag = (string) ($rule['tag'] ?? 'span');
                $class = (string) ($rule['class'] ?? '');
                $attrPath = (string) ($rule['attr_path'] ?? '');
                if ($class === '' || $attrPath === '') {
                    continue;
                }

                $newInner = self::getAttrStringByPath($block['attrs'], $attrPath);
                if (trim($newInner) === '') {
                    continue;
                }

                if (!empty($rule['rewrite_anchors'])) {
                    $rewrite = InternalLinkTranslator::rewriteAnchorsInHtmlFragment(
                        (string) $newInner,
                        (string) (self::$currentTargetLang ?? ''),
                        [
                            'prefer_anchor_id' => true,
                            'update_anchor_id' => true,
                            'source_lang' => (string) (self::$currentSourceLang ?? ''),
                        ]
                    );
                    if (is_array($rewrite) && isset($rewrite['html']) && is_string($rewrite['html'])) {
                        $newInner = (string) $rewrite['html'];
                        self::setAttrStringByPath($block['attrs'], $attrPath, $newInner);
                    }
                }

                $pattern = '/(<'.preg_quote($tag, '/').'\\b[^>]*class="[^"]*\\b'.preg_quote($class, '/').'\\b[^"]*"[^>]*>)(.*?)(<\\/'.preg_quote($tag, '/').'>)/s';
                $replaced = preg_replace($pattern, '${1}' . $newInner . '${3}', $innerHTML, 1, $count);
                if (is_string($replaced) && $count > 0) {
                    $innerHTML = $replaced;
                    $matchedAny = true;
                }
                continue;
            }
        }

        if ($matchedAny) {
            self::setBlockInnerHtml($block, $innerHTML);
        }

        // Nectar accordion sections are very strict about whitespace-only fragments inside the content wrapper.
        // When the editor compares `save()` output vs stored HTML, extra newlines/spaces can invalidate the block.
        if (
            $matchedAny
            && ($block['blockName'] ?? '') === 'nectar-blocks/accordion-section'
        ) {
            // 1) If the content wrapper is empty (no inner blocks), strip whitespace between open/close.
            $currentHtml = self::getBlockInnerHtml($block);
            if ($currentHtml !== '' && (empty($block['innerBlocks']) || !is_array($block['innerBlocks']))) {
                $contentWrapperPattern = '/(<div\\b[^>]*class="[^"]*\\bnectar-blocks-accordion-section__content\\b[^"]*"[^>]*>)\\s*(<\\/div>)/s';
                $collapsed = preg_replace($contentWrapperPattern, '${1}${2}', $currentHtml);
                if (is_string($collapsed) && $collapsed !== $currentHtml) {
                    self::setBlockInnerHtml($block, $collapsed);
                }
            }

            // 2) Also normalize whitespace-only innerContent string fragments (when present as separate pieces).
            if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                foreach ($block['innerContent'] as $idx => $part) {
                    if (is_string($part) && trim($part) === '') {
                        $block['innerContent'][$idx] = '';
                    }
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::debugLog('attrs_first: applied', [
                'block' => $block['blockName'] ?? '',
                'blockId' => $block['attrs']['blockId'] ?? '',
                'fields' => $fields,
                'sync_rules' => array_map(function ($r) {
                    return is_array($r) ? ($r['type'] ?? '') : '';
                }, $syncRules),
                'synced_markup' => $matchedAny,
            ]);

            //extra diagnostics for accordion-section validation issues
            if (($block['blockName'] ?? '') === 'nectar-blocks/accordion-section') {
                $finalHtml = self::getBlockInnerHtml($block);
                $contentWrapperSample = '';
                if ($finalHtml !== '') {
                    if (preg_match('/<div\\b[^>]*class="[^"]*\\bnectar-blocks-accordion-section__content\\b[^"]*"[^>]*>([\\s\\S]*?)<\\/div>/s', $finalHtml, $m)) {
                        $inside = $m[1] ?? '';
                        $trimmedInside = trim($inside);
                        $contentWrapperSample = strlen($trimmedInside) > 160 ? substr($trimmedInside, 0, 160) . '…' : $trimmedInside;
                    }
                }

                self::debugLog('attrs_first: accordion-section snapshot', [
                    'blockId' => $block['attrs']['blockId'] ?? '',
                    'title_attr' => $block['attrs']['title'] ?? '',
                    'innerBlocks' => isset($block['innerBlocks']) && is_array($block['innerBlocks']) ? count($block['innerBlocks']) : null,
                    'innerContent_count' => isset($block['innerContent']) && is_array($block['innerContent']) ? count($block['innerContent']) : null,
                    'innerContent_whitespace_only' => isset($block['innerContent']) && is_array($block['innerContent'])
                        ? count(array_filter($block['innerContent'], function ($p) {
                            return is_string($p) && trim($p) === '';
                        }))
                        : null,
                    'innerHTML_len' => strlen($finalHtml),
                    'content_wrapper_trim_sample' => $contentWrapperSample,
                ]);
            }
        }

        return $block;
    }

    /**
     * Extract wrapper attributes that should be preserved when emit_wrapper rebuilds markup.
     * @param string $html
     * @param string $tag
     * @return array<string, string>
     */
    private static function extractWrapperAttributesForEmitWrapper(string $html, string $tag): array
    {
        $attrs = self::extractOpeningTagAttributes($html, $tag);
        if ($attrs === []) {
            return [];
        }

        // We control these explicitly when emitting.
        unset($attrs['id'], $attrs['class']);

        return $attrs;
    }

    /**
     * Extract attributes from the first opening tag of $tag in the given HTML.
     * @param string $html
     * @param string $tag
     * @return array<string, string>
     */
    private static function extractOpeningTagAttributes(string $html, string $tag): array
    {
        $tag = strtolower(trim($tag));
        if ($tag === '' || $html === '') {
            return [];
        }

        $pattern = '/<\s*' . preg_quote($tag, '/') . '\b([^>]*)>/i';
        if (!preg_match($pattern, $html, $m)) {
            return [];
        }

        $attrText = (string) ($m[1] ?? '');
        if ($attrText === '') {
            return [];
        }

        $attrs = [];

        // Parse key="value" and key='value'
        if (preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/u', $attrText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower((string) ($match[1] ?? ''));
                $value = (string) (($match[2] ?? '') !== '' || array_key_exists(2, $match) ? ($match[2] ?? '') : ($match[3] ?? ''));
                $attrs[$key] = $value;
            }
        }

        // Also capture attributes like data-await-in-view-desktop="" already covered above.
        return $attrs;
    }

    /**
     * Build HTML attribute string from preserved attributes.
     * @param array<string, string> $attrs
     * @return string
     */
    private static function buildPreservedAttributesHtml(array $attrs): string
    {
        if ($attrs === []) {
            return '';
        }

        $out = '';
        foreach ($attrs as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            // Decode once to avoid double-escaping (e.g. &quot; -> " -> &quot;).
            $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out .= ' ' . esc_attr($key) . '="' . esc_attr($decoded) . '"';
        }

        return $out;
    }

    /**
     * Escape literal < and > while preserving basic inline formatting tags (e.g. <strong>).
     * This avoids cases where editors use < and > around text, which can break block validation.
     * @param string $value
     * @return string
     */
    private static function escapeLiteralAngleBracketsPreserveInlineTags(string $value): string
    {
        //also handle entity-encoded angle brackets (&lt; / &gt;) and double-encoded variants (&amp;gt)
        if ($value === '') {
            return $value;
        }
        $hasRawAngles = (strpos($value, '<') !== false || strpos($value, '>') !== false);
        $hasEntityAngles = (stripos($value, '&lt') !== false || stripos($value, '&gt') !== false || stripos($value, '&amp;gt') !== false || stripos($value, '&amp;lt') !== false);
        if (!$hasRawAngles && !$hasEntityAngles) {
            return $value;
        }

        $allowedTags = '(?:a|strong|em|b|i|u|br|span|sup|sub|code|mark)';
        $pattern = '/<\\/?' . $allowedTags . '\\b[^>]*\\/?\\s*>/i';

        $kept = [];
        $tokenized = preg_replace_callback($pattern, static function ($m) use (&$kept) {
            $token = '__NCT_KEEP_TAG_' . count($kept) . '__';
            $kept[$token] = $m[0];
            return $token;
        }, $value);

        if (!is_string($tokenized)) {
            $tokenized = $value;
        }

        //normalize common entity variants (including missing semicolons) before escaping
        $normalized = preg_replace('/&lt(?!;)/i', '&lt;', $tokenized);
        if (!is_string($normalized)) {
            $normalized = $tokenized;
        }
        $normalized = preg_replace('/&gt(?!;)/i', '&gt;', $normalized);
        if (!is_string($normalized)) {
            $normalized = $tokenized;
        }
        $normalized = preg_replace('/&amp(?!;)/i', '&amp;', $normalized);
        if (!is_string($normalized)) {
            $normalized = $tokenized;
        }

        //decode once so &amp;gt; becomes &gt; and then a literal >
        $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $escaped = str_replace(['<', '>'], ['&lt;', '&gt;'], $decoded);

        foreach ($kept as $token => $tagHtml) {
            $escaped = str_replace($token, $tagHtml, $escaped);
        }

        return $escaped;
    }

    /**
     * Debug logging helper (only when WP_DEBUG is enabled)
     * @param string $message
     * @param array $context
     * @return void
     */
    private static function debugLog(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $suffix = $context !== [] ? ' ' . wp_json_encode($context) : '';
        error_log('Novi content translator: ' . $message . $suffix);
    }

    /**
     * Set runtime reusable ref overrides for a target language.
     * Used to guarantee same-request core/block ref swaps right after creating missing translations.
     *
     * @param string $targetLangSlug
     * @param array<int, int> $refMap sourceRef => targetRef
     * @return void
     */
    public static function setRuntimeReusableRefMap(string $targetLangSlug, array $refMap): void
    {
        $lang = trim((string) $targetLangSlug);
        if ($lang === '') {
            return;
        }

        $normalized = [];
        foreach ($refMap as $sourceRef => $targetRef) {
            $source = (int) $sourceRef;
            $target = (int) $targetRef;
            if ($source > 0 && $target > 0 && $source !== $target) {
                $normalized[$source] = $target;
            }
        }
        self::$runtimeReusableRefMapByLang[$lang] = $normalized;
    }

    /**
     * Clear runtime reusable ref overrides for a target language.
     * @param string $targetLangSlug
     * @return void
     */
    public static function clearRuntimeReusableRefMap(string $targetLangSlug): void
    {
        $lang = trim((string) $targetLangSlug);
        if ($lang === '') {
            return;
        }
        unset(self::$runtimeReusableRefMapByLang[$lang]);
    }

    /**
     * Pre-translation block transforms that depend on the target language.
     * Example: core/block reusable block refs should point to the translated wp_block when available.
     * @param array $blocks
     * @param string $targetLangSlug Polylang language slug (e.g. nl)
     * @return void
     */
    private static function transformBlocksForTargetLanguage(array &$blocks, string $targetLangSlug): void
    {
        foreach (array_keys($blocks) as $i) {
            if (!isset($blocks[$i]) || !is_array($blocks[$i])) {
                continue;
            }
            $block = &$blocks[$i];

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::transformBlocksForTargetLanguage($block['innerBlocks'], $targetLangSlug);
            }

            $blockName = $block['blockName'] ?? '';
            if ($blockName === 'core/block') {
                $ref = $block['attrs']['ref'] ?? null;
                if (!is_numeric($ref)) {
                    continue;
                }

                $sourceRef = (int) $ref;
                $translatedRef = 0;
                $resolvedVia = 'none';
                $runtimeMap = self::$runtimeReusableRefMapByLang[$targetLangSlug] ?? [];
                if (isset($runtimeMap[$sourceRef])) {
                    $translatedRef = (int) $runtimeMap[$sourceRef];
                    $resolvedVia = 'runtime_map';
                } elseif (function_exists('pll_get_post')) {
                    $translatedRef = (int) pll_get_post($sourceRef, $targetLangSlug);
                    $resolvedVia = 'pll_get_post';
                }

                self::debugLog('core/block ref resolution', [
                    'source_ref' => $sourceRef,
                    'target_lang' => $targetLangSlug,
                    'resolved_via' => $resolvedVia,
                    'translated_ref' => $translatedRef,
                ]);

                if ($translatedRef > 0 && $translatedRef !== $sourceRef) {
                    if (!isset($block['attrs']) || !is_array($block['attrs'])) {
                        $block['attrs'] = [];
                    }
                    $block['attrs']['ref'] = $translatedRef;
                }
                continue;
            }

            if ($blockName === 'nectar-blocks/taxonomy-grid') {
                if (
                    !isset($block['attrs']) ||
                    !is_array($block['attrs']) ||
                    !isset($block['attrs']['taxonomies']) ||
                    !is_array($block['attrs']['taxonomies']) ||
                    !function_exists('pll_get_term')
                ) {
                    continue;
                }

                foreach (array_keys($block['attrs']['taxonomies']) as $termIndex) {
                    $termId = $block['attrs']['taxonomies'][$termIndex] ?? null;
                    if (!is_numeric($termId)) {
                        continue;
                    }

                    $translatedTermId = pll_get_term((int) $termId, $targetLangSlug);
                    if (
                        is_numeric($translatedTermId) &&
                        (int) $translatedTermId > 0 &&
                        (int) $translatedTermId !== (int) $termId
                    ) {
                        $block['attrs']['taxonomies'][$termIndex] = (int) $translatedTermId;
                    }
                }
            }

            if ($blockName === 'nectar-blocks/post-grid') {
                if (
                    !isset($block['attrs']) ||
                    !is_array($block['attrs']) ||
                    !isset($block['attrs']['taxonomies']) ||
                    !is_array($block['attrs']['taxonomies']) ||
                    !function_exists('pll_get_term')
                ) {
                    continue;
                }

                foreach (array_keys($block['attrs']['taxonomies']) as $termIndex) {
                    $termId = $block['attrs']['taxonomies'][$termIndex] ?? null;
                    if (!is_numeric($termId)) {
                        continue;
                    }

                    $translatedTermId = pll_get_term((int) $termId, $targetLangSlug);
                    if (
                        is_numeric($translatedTermId) &&
                        (int) $translatedTermId > 0 &&
                        (int) $translatedTermId !== (int) $termId
                    ) {
                        $block['attrs']['taxonomies'][$termIndex] = (int) $translatedTermId;
                    }
                }
            }

            if ($blockName === 'gravityforms/form') {
                if (!isset($block['attrs']) || !is_array($block['attrs']) || !array_key_exists('formId', $block['attrs'])) {
                    continue;
                }

                $raw = $block['attrs']['formId'];
                $sourceFormId = is_numeric($raw) ? (int) $raw : 0;
                if ($sourceFormId <= 0) {
                    continue;
                }

                $sourceLang = trim((string) (self::$currentSourceLang ?? ''));
                if ($sourceLang === '') {
                    self::debugLog('gravityforms/form formId remap skipped: missing source lang', [
                        'source_form_id' => $sourceFormId,
                        'target_lang' => $targetLangSlug,
                    ]);
                    continue;
                }

                $targetFormId = GravityFormsTranslator::resolveTargetFormId($sourceFormId, $sourceLang, $targetLangSlug);
                self::debugLog('gravityforms/form formId resolution', [
                    'source_form_id' => $sourceFormId,
                    'source_lang' => $sourceLang,
                    'target_lang' => $targetLangSlug,
                    'target_form_id' => $targetFormId,
                ]);

                if ($targetFormId > 0 && $targetFormId !== $sourceFormId) {
                    $block['attrs']['formId'] = (string) $targetFormId;
                }
            }

            //remap acf/block-novi-menu menu term ids to the target language (like GF formId)
            if ($blockName === 'acf/block-novi-menu') {
                if (
                    !isset($block['attrs']) ||
                    !is_array($block['attrs']) ||
                    !isset($block['attrs']['data']) ||
                    !is_array($block['attrs']['data']) ||
                    !array_key_exists('menu', $block['attrs']['data'])
                ) {
                    //still attempt Popup Maker remaps below
                } else {
                    $rawMenu = $block['attrs']['data']['menu'];
                    $sourceMenuId = is_numeric($rawMenu) ? (int) $rawMenu : 0;
                    if ($sourceMenuId > 0) {
                        $targetMenuId = 0;
                        if (function_exists('pll_get_term')) {
                            $translatedMenuId = pll_get_term($sourceMenuId, $targetLangSlug);
                            if (is_numeric($translatedMenuId) && (int) $translatedMenuId > 0) {
                                $targetMenuId = (int) $translatedMenuId;
                            }
                        }

                        if ($targetMenuId <= 0) {
                            $sourceLang = trim((string) (self::$currentSourceLang ?? ''));
                            if ($sourceLang !== '') {
                                $stats = ['mapped' => 0, 'unmapped' => 0];
                                $mapFn = MenuBlockIdReplacer::buildMenuIdMapperBySuffix($sourceLang, $targetLangSlug, $stats);
                                $targetMenuId = (int) $mapFn($sourceMenuId);
                            }
                        }

                        self::debugLog('acf/block-novi-menu menu remap', [
                            'source_menu_id' => $sourceMenuId,
                            'target_lang' => $targetLangSlug,
                            'target_menu_id' => $targetMenuId,
                        ]);

                        if ($targetMenuId > 0 && $targetMenuId !== $sourceMenuId) {
                            $block['attrs']['data']['menu'] = (string) $targetMenuId;
                        }
                    }
                }
            }

            //remap Popup Maker openPopupId + popmake-{id} CSS classes to the target-language twin
            self::remapPopupMakerReferencesInBlock($block, $targetLangSlug);
        }
    }

    /**
     * Remap Popup Maker popup IDs in block attrs/HTML to the Polylang twin for $targetLangSlug.
     * Handles NB button `openPopupId` and CSS class `popmake-{id}` in className/innerHTML/innerContent.
     *
     * @param array<string, mixed> $block
     */
    private static function remapPopupMakerReferencesInBlock(array &$block, string $targetLangSlug): void
    {
        if ($targetLangSlug === '' || !function_exists('pll_get_post')) {
            return;
        }

        $sourcePopupId = 0;
        if (
            isset($block['attrs']) &&
            is_array($block['attrs']) &&
            isset($block['attrs']['openPopupId']) &&
            is_numeric($block['attrs']['openPopupId'])
        ) {
            $sourcePopupId = (int) $block['attrs']['openPopupId'];
        }

        if ($sourcePopupId <= 0) {
            $haystacks = [];
            if (!empty($block['attrs']['className']) && is_string($block['attrs']['className'])) {
                $haystacks[] = $block['attrs']['className'];
            }
            if (!empty($block['innerHTML']) && is_string($block['innerHTML'])) {
                $haystacks[] = $block['innerHTML'];
            }
            foreach ($haystacks as $haystack) {
                if (preg_match('/\bpopmake-(\d+)\b/', $haystack, $matches)) {
                    $sourcePopupId = (int) $matches[1];
                    break;
                }
            }
        }

        if ($sourcePopupId <= 0) {
            return;
        }

        $targetPopupId = (int) pll_get_post($sourcePopupId, $targetLangSlug);
        if ($targetPopupId <= 0 || $targetPopupId === $sourcePopupId) {
            return;
        }

        self::debugLog('popup maker id remap', [
            'source_popup_id' => $sourcePopupId,
            'target_lang' => $targetLangSlug,
            'target_popup_id' => $targetPopupId,
            'block_name' => (string) ($block['blockName'] ?? ''),
        ]);

        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            $block['attrs'] = [];
        }

        if (
            isset($block['attrs']['openPopupId']) &&
            is_numeric($block['attrs']['openPopupId']) &&
            (int) $block['attrs']['openPopupId'] === $sourcePopupId
        ) {
            $block['attrs']['openPopupId'] = (string) $targetPopupId;
        }

        $fromClass = 'popmake-' . $sourcePopupId;
        $toClass = 'popmake-' . $targetPopupId;

        if (!empty($block['attrs']['className']) && is_string($block['attrs']['className'])) {
            $block['attrs']['className'] = str_replace($fromClass, $toClass, $block['attrs']['className']);
        }
        if (!empty($block['innerHTML']) && is_string($block['innerHTML'])) {
            $block['innerHTML'] = str_replace($fromClass, $toClass, $block['innerHTML']);
        }
        if (!empty($block['innerContent']) && is_array($block['innerContent'])) {
            foreach ($block['innerContent'] as $chunkIndex => $chunk) {
                if (is_string($chunk)) {
                    $block['innerContent'][$chunkIndex] = str_replace($fromClass, $toClass, $chunk);
                }
            }
        }
    }

    /**
     * Translate post content containing Gutenberg blocks
     * Uses batch translation: collect all texts → translate in chunks → apply back to blocks
     * @param string $content Post content with blocks
     * @param string $sourceLang Source language code (WordPress locale)
     * @param string $targetLang Target language code (WordPress locale)
     * @return string Translated post content
     */
    public static function translatePostContent(string $content, string $sourceLang, string $targetLang): string
    {
        self::setLastError(null);
        self::$currentTargetLang = $targetLang;
        self::$currentSourceLang = $sourceLang;

        // Check if content has blocks
        if (!has_blocks($content)) {
            self::$currentTargetLang = null;
            self::$currentSourceLang = null;
            return $content;
        }

        try {
            $blocks = parse_blocks($content);
            if (empty($blocks)) {
                self::$currentTargetLang = null;
                self::$currentSourceLang = null;
                return $content;
            }

            //phase 0: non-text transforms that depend on target language
            self::transformBlocksForTargetLanguage($blocks, $targetLang);

            self::debugLog('Block translation: start', [
                'source' => $sourceLang,
                'target' => $targetLang,
                'top_level_blocks' => count($blocks),
            ]);

            // Phase 1: Collect all translatable texts and descriptors
            $allTexts = [];
            $descriptors = [];
            self::collectBlockTexts($blocks, $allTexts, $descriptors, []);

            self::debugLog('Block translation: collected', [
                'texts' => count($allTexts),
                'descriptors' => count($descriptors),
                'sample' => array_slice(array_map(function ($t) {
                    $t = is_string($t) ? trim($t) : '';
                    return strlen($t) > 120 ? substr($t, 0, 120) . '…' : $t;
                }, $allTexts), 0, 3),
            ]);

            if (empty($allTexts) && empty($descriptors)) {
                self::$currentTargetLang = null;
                self::$currentSourceLang = null;
                self::normalizeBlocksForSerialization($blocks);
                return self::normalizeLostBackslashUnicodeEscapes(serialize_blocks($blocks));
            }

            // Phase 2: Translate in batch (chunk by DeepL limits: 50 texts and 128 KiB request size)
            $translatedTexts = [];
            if (!empty($allTexts)) {
                $chunks = self::chunkTextsByDeepLLimits($allTexts);
                self::debugLog('Block translation: chunking', [
                    'chunks' => count($chunks),
                    'batch_size' => self::DEEPL_BATCH_SIZE,
                    'max_request_bytes' => self::DEEPL_MAX_REQUEST_BYTES,
                ]);
                foreach ($chunks as $chunk) {
                    $result = DeepLTranslator::translateTexts($chunk, $sourceLang, $targetLang, ['context' => 'html']);
                    if (!$result['success']) {
                        $error = (string) ($result['error'] ?? 'Unknown error');
                        self::setLastError($error);
                        error_log('Novi content translator: Batch block translation failed: ' . $error);
                        return $content;
                    }
                    $translatedTexts = array_merge($translatedTexts, $result['translations']);
                }
            }

            // Phase 3: Apply translations back to blocks
            self::applyBlockTranslations($blocks, $translatedTexts, $descriptors, []);

            //extra diagnostics: capture serialized snippets for accordion-section blocks
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $serialized = serialize_blocks($blocks);
                $accordionSectionCount = 0;
                $accordionSectionSamples = [];
                foreach ($blocks as $b) {
                    self::collectAccordionSectionDebug($b, $accordionSectionCount, $accordionSectionSamples);
                }
                if ($accordionSectionCount > 0) {
                    self::debugLog('Block translation: accordion-section serialized snapshot', [
                        'count' => $accordionSectionCount,
                        'samples' => array_slice($accordionSectionSamples, 0, 5),
                    ]);
                }
            }

            self::debugLog('Block translation: applied', [
                'translated_texts' => count($translatedTexts),
                'result_has_blocks' => has_blocks(serialize_blocks($blocks)),
            ]);

            self::$currentTargetLang = null;
            self::$currentSourceLang = null;
            self::normalizeBlocksForSerialization($blocks);
            return self::normalizeLostBackslashUnicodeEscapes(serialize_blocks($blocks));
        } catch (\Exception $e) {
            error_log('Novi content translator: Block translation error: ' . $e->getMessage());
            self::setLastError($e->getMessage());
            self::$currentTargetLang = null;
            self::$currentSourceLang = null;
            return $content;
        }
    }

    /**
     * Rewrite internal links only (no DeepL calls).
     * Reuses the normal collect/apply pipeline so attrs+markup sync rules still run.
     *
     * @param string $content
     * @param string $sourceLang Polylang slug (e.g. en)
     * @param string $targetLang Polylang slug (e.g. nl)
     * @return string
     */
    public static function syncLinksOnly(string $content, string $sourceLang, string $targetLang): string
    {
        self::setLastError(null);
        self::$currentTargetLang = $targetLang;
        self::$currentSourceLang = $sourceLang;
        self::$strictLinkRewrite = true;

        if (!has_blocks($content)) {
            // Still attempt deterministic link repair for classic (non-block) HTML content.
            $rewrite = InternalLinkTranslator::rewriteAnchorsInHtmlFragment($content, $targetLang, [
                'prefer_anchor_id' => true,
                'update_anchor_id' => true,
                'source_lang' => $sourceLang,
                'strict' => true,
            ]);
            $out = is_array($rewrite) && isset($rewrite['html']) && is_string($rewrite['html']) ? $rewrite['html'] : $content;
            self::$currentTargetLang = null;
            self::$currentSourceLang = null;
            self::$strictLinkRewrite = false;
            return $out;
        }

        try {
            $blocks = parse_blocks($content);
            if (empty($blocks)) {
                self::$currentTargetLang = null;
                self::$currentSourceLang = null;
                self::$strictLinkRewrite = false;
                return $content;
            }

            //phase 0: non-text transforms that depend on target language
            self::transformBlocksForTargetLanguage($blocks, $targetLang);

            $allTexts = [];
            $descriptors = [];
            self::collectBlockTexts($blocks, $allTexts, $descriptors, []);

            if (empty($allTexts) && empty($descriptors)) {
                self::$currentTargetLang = null;
                self::$currentSourceLang = null;
                self::normalizeBlocksForSerialization($blocks);
                self::$strictLinkRewrite = false;
                return self::normalizeLostBackslashUnicodeEscapes(serialize_blocks($blocks));
            }

            // Identity translation: apply phase runs, but text content is unchanged.
            self::applyBlockTranslations($blocks, $allTexts, $descriptors, []);

            self::$currentTargetLang = null;
            self::$currentSourceLang = null;
            self::normalizeBlocksForSerialization($blocks);
            self::$strictLinkRewrite = false;
            return self::normalizeLostBackslashUnicodeEscapes(serialize_blocks($blocks));
        } catch (\Throwable $e) {
            self::setLastError($e->getMessage());
            self::$currentTargetLang = null;
            self::$currentSourceLang = null;
            self::$strictLinkRewrite = false;
            return $content;
        }
    }

    /**
     * Normalize blocks just before serialization.
     * Guards against attrs/inner HTML accidentally containing literal "u00xx" sequences instead of real
     * characters (or "\u00xx" in JSON). Lost escapes in attrs become real chars so serialize_blocks()
     * can emit valid JSON; inner HTML is fixed the same way so the editor/frontend don't show "u003cbr".
     *
     * @param array<int, array<string, mixed>> $blocks
     * @return void
     */
    private static function normalizeBlocksForSerialization(array &$blocks): void
    {
        foreach (array_keys($blocks) as $i) {
            if (!isset($blocks[$i]) || !is_array($blocks[$i])) {
                continue;
            }
            $block = &$blocks[$i];
            if (isset($block['attrs']) && is_array($block['attrs'])) {
                self::normalizeUnicodeEscapeSequencesInMixed($block['attrs']);
            }
            if (isset($block['innerHTML']) && is_string($block['innerHTML'])) {
                self::normalizeUnicodeEscapeSequencesInMixed($block['innerHTML']);
            }
            if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                foreach (array_keys($block['innerContent']) as $j) {
                    if (is_string($block['innerContent'][$j])) {
                        self::normalizeUnicodeEscapeSequencesInMixed($block['innerContent'][$j]);
                    }
                }
            }
            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::normalizeBlocksForSerialization($block['innerBlocks']);
            }
        }
    }

    /**
     * Recursively decode lost Gutenberg "u00XX" sequences into real characters in attrs/HTML.
     * Only the escapes from serialize_block_attributes() — never arbitrary u+4hex (block IDs / words).
     *
     * @param mixed $value
     * @return void
     */
    private static function normalizeUnicodeEscapeSequencesInMixed(&$value): void
    {
        if (is_string($value)) {
            // Convert literal "u003c/u0022/..." sequences into actual characters.
            // Example: "u003ca href=u0022...u0022u003e" => "<a href="...">"
            // This protects against Gutenberg validation errors when attrs accidentally contain the
            // "u00xx" text instead of real characters.
            if (stripos($value, 'u00') !== false) {
                $value = preg_replace_callback(
                    '/(?<!\\\\)u00(' . self::GUTENBERG_UNICODE_ESCAPE_HEX . ')/',
                    static function (array $m): string {
                        $hex = isset($m[1]) ? (string) $m[1] : '';
                        if ($hex === '' || !ctype_xdigit($hex)) {
                            return (string) ($m[0] ?? '');
                        }
                        $code = hexdec($hex);
                        if (function_exists('mb_chr')) {
                            return (string) mb_chr($code, 'UTF-8');
                        }
                        //fallback: decode via HTML entity
                        $entity = '&#x' . $hex . ';';
                        $decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        return is_string($decoded) && $decoded !== '' ? $decoded : (string) ($m[0] ?? '');
                    },
                    $value
                ) ?? $value;
            }
            return;
        }

        if (is_array($value)) {
            foreach (array_keys($value) as $k) {
                self::normalizeUnicodeEscapeSequencesInMixed($value[$k]);
            }
            return;
        }
    }

    private static function collectAccordionSectionDebug(array $block, int &$count, array &$samples): void
    {
        if (($block['blockName'] ?? '') === 'nectar-blocks/accordion-section') {
            $count++;
            $blockId = (string) ($block['attrs']['blockId'] ?? '');
            $inner = self::getBlockInnerHtml($block);
            $snippet = $inner;
            if (strlen($snippet) > 400) {
                $snippet = substr($snippet, 0, 400) . '…';
            }
            $samples[] = [
                'blockId' => $blockId,
                'title_attr' => $block['attrs']['title'] ?? '',
                'innerBlocks' => isset($block['innerBlocks']) && is_array($block['innerBlocks']) ? count($block['innerBlocks']) : null,
                'innerHTML_snippet' => $snippet,
            ];
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $child) {
                if (is_array($child)) {
                    self::collectAccordionSectionDebug($child, $count, $samples);
                }
            }
        }
    }

    /**
     * Extract translatable TOC strings from headings[].content and customLabels values.
     * @param array $block
     * @return array<int, string>
     */
    private static function extractTableOfContentsTexts(array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        if (!is_array($attrs)) {
            return [];
        }

        $texts = [];
        $headings = $attrs['headings'] ?? [];
        if (is_array($headings)) {
            foreach ($headings as $heading) {
                if (!is_array($heading)) {
                    continue;
                }
                $content = trim((string) ($heading['content'] ?? ''));
                if ($content !== '') {
                    $texts[] = $content;
                }
            }
        }

        $customLabels = $attrs['customLabels'] ?? null;
        if (is_array($customLabels)) {
            foreach ($customLabels as $label) {
                $value = trim((string) $label);
                if ($value !== '') {
                    $texts[] = $value;
                }
            }
        }

        return $texts;
    }

    /**
     * Replace translated TOC heading/customLabels values and sync list link text.
     * @param array $block
     * @param array $originals
     * @param array $translated
     * @return array
     */
    private static function replaceTableOfContentsTexts(array $block, array $originals, array $translated): array
    {
        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            return $block;
        }

        $index = 0;
        $headings = $block['attrs']['headings'] ?? [];
        if (is_array($headings)) {
            foreach ($headings as $headingIndex => $heading) {
                if (!is_array($heading)) {
                    continue;
                }
                $currentValue = trim((string) ($heading['content'] ?? ''));
                if ($currentValue === '') {
                    continue;
                }
                if (isset($translated[$index])) {
                    $newValue = trim((string) $translated[$index]);
                    if ($newValue !== '') {
                        $headings[$headingIndex]['content'] = $newValue;
                    }
                }
                $index++;
            }
            $block['attrs']['headings'] = $headings;
        }

        $customLabels = $block['attrs']['customLabels'] ?? null;
        if (is_array($customLabels)) {
            foreach ($customLabels as $key => $label) {
                $currentValue = trim((string) $label);
                if ($currentValue === '') {
                    continue;
                }
                if (isset($translated[$index])) {
                    $newValue = trim((string) $translated[$index]);
                    if ($newValue !== '') {
                        $customLabels[$key] = $newValue;
                    }
                }
                $index++;
            }
            $block['attrs']['customLabels'] = $customLabels;
        }

        return self::syncTableOfContentsInnerHtml($block);
    }

    /**
     * Sync TOC list link text from headings / customLabels attrs.
     * @param array $block
     * @return array
     */
    private static function syncTableOfContentsInnerHtml(array $block): array
    {
        $innerHTML = self::getBlockInnerHtml($block);
        if ($innerHTML === '') {
            return $block;
        }

        $attrs = $block['attrs'] ?? [];
        if (!is_array($attrs)) {
            return $block;
        }

        $customLabels = $attrs['customLabels'] ?? null;
        if (!is_array($customLabels)) {
            $customLabels = [];
        }

        $headings = $attrs['headings'] ?? [];
        if (!is_array($headings)) {
            $headings = [];
        }

        $labelByAnchor = [];
        foreach ($headings as $heading) {
            if (!is_array($heading)) {
                continue;
            }
            $anchor = trim((string) ($heading['anchor'] ?? ''));
            if ($anchor === '') {
                continue;
            }
            $content = (string) ($heading['content'] ?? '');
            if (isset($customLabels[$anchor]) && trim((string) $customLabels[$anchor]) !== '') {
                $content = (string) $customLabels[$anchor];
            }
            $labelByAnchor[$anchor] = $content;
            $labelByAnchor['#' . ltrim($anchor, '#')] = $content;
        }

        if ($labelByAnchor === []) {
            return $block;
        }

        if (!self::hasDomSupport()) {
            return $block;
        }

        $wrapperHtml = '<div id="nct-toc-root-wrapper">' . $innerHTML . '</div>';
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8">' . $wrapperHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            return $block;
        }

        $xpath = new \DOMXPath($doc);
        $links = $xpath->query('//a[@href]');
        if ($links instanceof \DOMNodeList) {
            foreach ($links as $linkNode) {
                if (!$linkNode instanceof \DOMElement) {
                    continue;
                }
                $href = trim((string) $linkNode->getAttribute('href'));
                if ($href === '' || !isset($labelByAnchor[$href])) {
                    $anchorKey = ltrim($href, '#');
                    if ($anchorKey === '' || !isset($labelByAnchor[$anchorKey])) {
                        continue;
                    }
                    $label = $labelByAnchor[$anchorKey];
                } else {
                    $label = $labelByAnchor[$href];
                }
                while ($linkNode->firstChild) {
                    $linkNode->removeChild($linkNode->firstChild);
                }
                $linkNode->appendChild($doc->createTextNode($label));
            }
        }

        $wrapper = $doc->getElementById('nct-toc-root-wrapper');
        if (!$wrapper instanceof \DOMElement) {
            return $block;
        }

        $newInnerHTML = '';
        foreach ($wrapper->childNodes as $childNode) {
            $newInnerHTML .= $doc->saveHTML($childNode);
        }
        if ($newInnerHTML !== '') {
            self::setBlockInnerHtml($block, $newInnerHTML);
        }

        return $block;
    }

    /**
     * Extract translatable strings from Nectar Tabs tabItems.
     * We translate per tab item:
     * - label
     * - description
     * - icon.iconCustom.alt
     * @param array $block
     * @return array<int, string>
     */
    private static function extractTabsTexts(array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        if (!is_array($attrs)) {
            return [];
        }

        $tabItems = $attrs['tabItems'] ?? [];
        if (!is_array($tabItems)) {
            return [];
        }

        $texts = [];
        foreach ($tabItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            //nb3 save() renders nav from label; older content sometimes only had title
            $label = trim((string) ($item['label'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            $navLabel = $label !== '' ? $label : $title;
            if ($navLabel !== '') {
                $texts[] = $navLabel;
            }

            $description = trim((string) ($item['description'] ?? ''));
            if ($description !== '') {
                $texts[] = $description;
            }

            $icon = $item['icon']['iconCustom'] ?? null;
            if (!is_array($icon)) {
                continue;
            }
            foreach (['alt'] as $field) {
                $value = trim((string) ($icon[$field] ?? ''));
                if ($value !== '') {
                    $texts[] = $value;
                }
            }
        }

        return $texts;
    }

    /**
     * Replace translated tab item values and sync rendered tabs navigation markup.
     * @param array $block
     * @param array $originals
     * @param array $translated
     * @return array
     */
    private static function replaceTabsTexts(array $block, array $originals, array $translated): array
    {
        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            return $block;
        }

        $tabItems = $block['attrs']['tabItems'] ?? [];
        if (!is_array($tabItems)) {
            return $block;
        }

        $index = 0;
        foreach ($tabItems as $itemIndex => $item) {
            if (!is_array($item)) {
                continue;
            }

            //normalize to label (nb3 save source of truth); drop legacy title to avoid validation drift
            $label = trim((string) ($item['label'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            $navLabel = $label !== '' ? $label : $title;
            if ($navLabel !== '') {
                if (isset($translated[$index])) {
                    $newValue = trim((string) $translated[$index]);
                    if ($newValue !== '') {
                        $tabItems[$itemIndex]['label'] = $newValue;
                    }
                }
                unset($tabItems[$itemIndex]['title']);
                $index++;
            }

            $description = trim((string) ($item['description'] ?? ''));
            if ($description !== '') {
                if (isset($translated[$index])) {
                    $newValue = trim((string) $translated[$index]);
                    if ($newValue !== '') {
                        $tabItems[$itemIndex]['description'] = $newValue;
                    }
                }
                $index++;
            }

            $icon = $item['icon']['iconCustom'] ?? null;
            if (!is_array($icon)) {
                continue;
            }
            foreach (['alt'] as $field) {
                $currentValue = trim((string) ($icon[$field] ?? ''));
                if ($currentValue === '') {
                    continue;
                }
                if (isset($translated[$index])) {
                    $newValue = trim((string) $translated[$index]);
                    if ($newValue !== '') {
                        $tabItems[$itemIndex]['icon']['iconCustom'][$field] = $newValue;
                    }
                }
                $index++;
            }
        }

        $block['attrs']['tabItems'] = $tabItems;
        return self::syncTabsInnerHtml($block, $tabItems);
    }

    /**
     * Sync tabs nav markup from translated tabItems attrs.
     * @param array $block
     * @param array<int, array<string, mixed>> $tabItems
     * @return array
     */
    private static function syncTabsInnerHtml(array $block, array $tabItems): array
    {
        if (!self::hasDomSupport()) {
            return $block;
        }

        //tabs often contain inner blocks; update nav inside string fragments to preserve innerContent/null placeholders
        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            return self::syncTabsInnerContentFragments($block, $tabItems);
        }

        $innerHTML = self::getBlockInnerHtml($block);
        if ($innerHTML === '') {
            return $block;
        }

        $wrapperHtml = '<div id="nct-tabs-root-wrapper">' . $innerHTML . '</div>';
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8">' . $wrapperHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            return $block;
        }

        $xpath = new \DOMXPath($doc);
        $navLinks = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-tabs__nav__link ")]');
        if ($navLinks instanceof \DOMNodeList) {
            foreach ($navLinks as $itemIndex => $linkNode) {
                if (
                    !$linkNode instanceof \DOMElement ||
                    !isset($tabItems[$itemIndex]) ||
                    !is_array($tabItems[$itemIndex])
                ) {
                    continue;
                }
                $item = $tabItems[$itemIndex];

                $titleNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-tabs__nav__link__title ")]', $linkNode)->item(0);
                if ($titleNode instanceof \DOMElement) {
                    $title = (string) ($item['label'] ?? $item['title'] ?? '');
                    while ($titleNode->firstChild) {
                        $titleNode->removeChild($titleNode->firstChild);
                    }
                    $titleNode->appendChild($doc->createTextNode($title));
                }

                $descNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-tabs__nav__link__desc ")]', $linkNode)->item(0);
                if ($descNode instanceof \DOMElement) {
                    $description = (string) ($item['description'] ?? '');
                    while ($descNode->firstChild) {
                        $descNode->removeChild($descNode->firstChild);
                    }
                    $descNode->appendChild($doc->createTextNode($description));
                }

                $imgNode = $xpath->query('.//img', $linkNode)->item(0);
                if ($imgNode instanceof \DOMElement) {
                    $icon = $item['icon']['iconCustom'] ?? null;
                    if (is_array($icon)) {
                        if (array_key_exists('alt', $icon)) {
                            $imgNode->setAttribute('alt', (string) ($icon['alt'] ?? ''));
                        }
                    }
                    //tabs save() does not emit title on nav icon images; keep markup aligned for validation
                    $imgNode->removeAttribute('title');
                }
            }
        }

        $wrapper = $doc->getElementById('nct-tabs-root-wrapper');
        if (!$wrapper instanceof \DOMElement) {
            return $block;
        }

        $newInnerHTML = '';
        foreach ($wrapper->childNodes as $childNode) {
            $newInnerHTML .= $doc->saveHTML($childNode);
        }
        if ($newInnerHTML !== '') {
            self::setBlockInnerHtml($block, $newInnerHTML);
        }

        return $block;
    }

    /**
     * Sync tabs nav markup directly inside innerContent string fragments.
     * This avoids placeholder mismatches for blocks with innerBlocks.
     * @param array $block
     * @param array<int, array<string, mixed>> $tabItems
     * @return array
     */
    private static function syncTabsInnerContentFragments(array $block, array $tabItems): array
    {
        $innerContent = $block['innerContent'] ?? [];
        if (!is_array($innerContent) || $innerContent === []) {
            return $block;
        }

        $titleIndex = 0;
        $descIndex = 0;
        $imgIndex = 0;

        foreach ($innerContent as $index => $fragment) {
            if (!is_string($fragment) || strpos($fragment, 'nectar-blocks-tabs__nav__link') === false) {
                continue;
            }

            $fragment = preg_replace_callback(
                '/(<span class="nectar-blocks-tabs__nav__link__title">)(.*?)(<\/span>)/s',
                static function ($matches) use ($tabItems, &$titleIndex) {
                    $item = $tabItems[$titleIndex] ?? null;
                    $label = is_array($item) ? (string) ($item['label'] ?? $item['title'] ?? '') : '';
                    $titleIndex++;
                    return $matches[1] . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $matches[3];
                },
                $fragment
            );

            $fragment = preg_replace_callback(
                '/(<span class="nectar-blocks-tabs__nav__link__desc[^"]*">)(.*?)(<\/span>)/s',
                static function ($matches) use ($tabItems, &$descIndex) {
                    $item = $tabItems[$descIndex] ?? null;
                    $desc = is_array($item) ? (string) ($item['description'] ?? '') : '';
                    $descIndex++;
                    return $matches[1] . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . $matches[3];
                },
                $fragment
            );

            $fragment = preg_replace_callback(
                '/<img\b[^>]*class="[^"]*\bnectar-component__icon__img\b[^"]*"[^>]*>/',
                static function ($matches) use ($tabItems, &$imgIndex) {
                    $imgTag = $matches[0];
                    $item = $tabItems[$imgIndex] ?? null;
                    $imgIndex++;

                    $icon = is_array($item) ? ($item['icon']['iconCustom'] ?? null) : null;
                    $alt = is_array($icon) ? (string) ($icon['alt'] ?? '') : '';
                    $escapedAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

                    $imgTag = preg_replace('/\s+title="[^"]*"/', '', $imgTag);
                    if (preg_match('/\balt="/', $imgTag)) {
                        $imgTag = preg_replace('/\balt="[^"]*"/', 'alt="' . $escapedAlt . '"', $imgTag, 1);
                    } else {
                        $imgTag = preg_replace('/<img\b/', '<img alt="' . $escapedAlt . '"', $imgTag, 1);
                    }

                    return (string) $imgTag;
                },
                $fragment
            );

            if (is_string($fragment) && $fragment !== '') {
                $innerContent[$index] = $fragment;
            }
        }

        $block['innerContent'] = $innerContent;
        $block['innerHTML'] = implode('', array_filter($innerContent, static function ($item) {
            return is_string($item);
        }));
        return $block;
    }

    /**
     * Extract translatable strings from Nectar Scrolling Marquee repeater items.
     * We translate:
     * - text items: repeaterContent[*].text (type=text)
     * - image metadata: repeaterContent[*].image.image.alt/title
     * @param array $block
     * @return array<int, string>
     */
    private static function extractScrollingMarqueeTexts(array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        if (!is_array($attrs)) {
            return [];
        }

        $repeater = $attrs['repeaterContent'] ?? [];
        if (!is_array($repeater)) {
            return [];
        }

        $texts = [];
        foreach ($repeater as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');
            if ($type === 'text') {
                $value = trim((string) ($item['text'] ?? ''));
                if ($value !== '') {
                    $texts[] = $value;
                }
            }

            $image = $item['image']['image'] ?? null;
            if (!is_array($image)) {
                continue;
            }

            $alt = trim((string) ($image['alt'] ?? ''));
            if ($alt !== '') {
                $texts[] = $alt;
            }

            $title = trim((string) ($image['title'] ?? ''));
            if ($title !== '') {
                $texts[] = $title;
            }
        }

        return $texts;
    }

    /**
     * Apply translated strings back into Scrolling Marquee attrs and sync inner HTML.
     * @param array $block
     * @param array $originals
     * @param array $translated
     * @return array
     */
    private static function replaceScrollingMarqueeTexts(array $block, array $originals, array $translated): array
    {
        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            return $block;
        }

        $repeater = $block['attrs']['repeaterContent'] ?? [];
        if (!is_array($repeater) || $repeater === []) {
            return $block;
        }

        $rowItems = [];
        $translationIndex = 0;

        foreach ($repeater as $itemIndex => $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? '');
            if ($type === 'text') {
                $currentValue = trim((string) ($item['text'] ?? ''));
                if ($currentValue !== '' && isset($translated[$translationIndex])) {
                    $newValue = self::normalizeMarqueeTextValue((string) $translated[$translationIndex]);
                    if ($newValue !== '') {
                        $repeater[$itemIndex]['text'] = $newValue;
                        $rowItems[] = ['type' => 'text', 'value' => $newValue];
                    } else {
                        $rowItems[] = ['type' => 'text', 'value' => $currentValue];
                    }
                    $translationIndex++;
                } elseif ($currentValue !== '') {
                    $rowItems[] = ['type' => 'text', 'value' => $currentValue];
                } else {
                    $rowItems[] = ['type' => 'text', 'value' => ''];
                }
            } else {
                $rowItems[] = ['type' => 'non_text', 'value' => ''];
            }

            $image = $item['image']['image'] ?? null;
            if (!is_array($image)) {
                continue;
            }

            $currentAlt = trim((string) ($image['alt'] ?? ''));
            if ($currentAlt !== '' && isset($translated[$translationIndex])) {
                $newAlt = self::normalizeMarqueeTextValue((string) $translated[$translationIndex]);
                if ($newAlt !== '') {
                    $repeater[$itemIndex]['image']['image']['alt'] = $newAlt;
                }
                $translationIndex++;
            }

            $currentTitle = trim((string) ($image['title'] ?? ''));
            if ($currentTitle !== '' && isset($translated[$translationIndex])) {
                $newTitle = self::normalizeMarqueeTextValue((string) $translated[$translationIndex]);
                if ($newTitle !== '') {
                    $repeater[$itemIndex]['image']['image']['title'] = $newTitle;
                }
                $translationIndex++;
            }
        }

        $block['attrs']['repeaterContent'] = $repeater;
        $block = self::syncScrollingMarqueeInnerHtml($block, $rowItems, $repeater);

        // Rewrite an optional parent-level link (attrs + markup) using the shared helper.
        $innerHTML = self::getBlockInnerHtml($block);
        if ($innerHTML !== '') {
            $rewriteResult = self::rewriteInternalLinkInAttrsAndMarkup($block, $innerHTML, ['link.href.value', 'link.href']);
            $block = $rewriteResult['block'];
            if (isset($rewriteResult['innerHTML']) && is_string($rewriteResult['innerHTML']) && $rewriteResult['innerHTML'] !== $innerHTML) {
                self::setBlockInnerHtml($block, $rewriteResult['innerHTML']);
            }
        }

        return $block;
    }

    /**
     * Sync Scrolling Marquee rendered markup after attrs update to avoid validation mismatches.
     * @param array $block
     * @param array<int, array{type:string,value:string}> $rowItems
     * @param array<int, array<string, mixed>> $repeater
     * @return array
     */
    private static function syncScrollingMarqueeInnerHtml(
        array $block,
        array $rowItems,
        array $repeater
    ): array {
        if (!self::hasDomSupport()) {
            return $block;
        }

        $innerHTML = self::getBlockInnerHtml($block);
        if ($innerHTML === '') {
            return $block;
        }

        $wrapperHtml = '<div id="nct-marquee-root-wrapper">' . $innerHTML . '</div>';
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$doc->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapperHtml,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        if (!$loaded) {
            return $block;
        }

        $xpath = new \DOMXPath($doc);
        $rowNodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-marquee__inner ")]');
        if ($rowNodes instanceof \DOMNodeList) {
            foreach ($rowNodes as $rowNode) {
                if (!$rowNode instanceof \DOMElement) {
                    continue;
                }
                $ariaHidden = strtolower((string) $rowNode->getAttribute('aria-hidden'));
                if ($ariaHidden === 'false') {
                    self::syncScrollingMarqueeTextRow($doc, $rowNode, $rowItems, false);
                } elseif ($ariaHidden === 'true') {
                    self::syncScrollingMarqueeTextRow($doc, $rowNode, $rowItems, true);
                }
                self::syncScrollingMarqueeImageRow($rowNode, $repeater);
            }
        }

        $wrapper = $doc->getElementById('nct-marquee-root-wrapper');
        if (!$wrapper instanceof \DOMElement) {
            return $block;
        }

        $newInnerHTML = '';
        foreach ($wrapper->childNodes as $childNode) {
            $newInnerHTML .= $doc->saveHTML($childNode);
        }

        if ($newInnerHTML !== '') {
            self::setBlockInnerHtml($block, $newInnerHTML);
        }

        return $block;
    }

    /**
     * Normalize marquee text values to avoid double-encoded entities in attrs/markup sync.
     * @param string $value
     * @return string
     */
    private static function normalizeMarqueeTextValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sync image metadata for one marquee row using repeater item order.
     * @param \DOMElement $rowNode
     * @param array<int, array<string, mixed>> $repeater
     * @return void
     */
    private static function syncScrollingMarqueeImageRow(\DOMElement $rowNode, array $repeater): void
    {
        $itemIndex = 0;
        foreach ($rowNode->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement || strtolower($childNode->tagName) !== 'div') {
                continue;
            }
            if (!isset($repeater[$itemIndex]) || !is_array($repeater[$itemIndex])) {
                $itemIndex++;
                continue;
            }

            $repeaterItem = $repeater[$itemIndex];
            $image = $repeaterItem['image']['image'] ?? null;
            if (!is_array($image)) {
                $itemIndex++;
                continue;
            }

            $imgNode = null;
            foreach ($childNode->childNodes as $innerNode) {
                if ($innerNode instanceof \DOMElement && strtolower($innerNode->tagName) === 'img') {
                    $imgNode = $innerNode;
                    break;
                }
            }

            if ($imgNode instanceof \DOMElement) {
                if (array_key_exists('alt', $image)) {
                    $imgNode->setAttribute('alt', (string) ($image['alt'] ?? ''));
                }
                if (array_key_exists('title', $image)) {
                    $imgNode->setAttribute('title', (string) ($image['title'] ?? ''));
                }
            }

            $itemIndex++;
        }
    }

    /**
     * Update one marquee row's item text content.
     * @param \DOMDocument $doc
     * @param \DOMElement $rowNode
     * @param array<int, array{type:string,value:string}> $rowItems
     * @param bool $withSpan
     * @return void
     */
    private static function syncScrollingMarqueeTextRow(
        \DOMDocument $doc,
        \DOMElement $rowNode,
        array $rowItems,
        bool $withSpan
    ): void {
        $itemIndex = 0;
        foreach ($rowNode->childNodes as $childNode) {
            if (!$childNode instanceof \DOMElement || strtolower($childNode->tagName) !== 'div') {
                continue;
            }
            if (!isset($rowItems[$itemIndex])) {
                break;
            }

            $item = $rowItems[$itemIndex];
            if (($item['type'] ?? '') !== 'text') {
                $itemIndex++;
                continue;
            }
            $newText = (string) ($item['value'] ?? '');
            while ($childNode->firstChild) {
                $childNode->removeChild($childNode->firstChild);
            }

            if ($withSpan) {
                $span = $doc->createElement('span');
                $span->setAttribute('aria-hidden', 'true');
                $span->setAttribute('role', 'presentation');
                $span->appendChild($doc->createTextNode($newText));
                $childNode->appendChild($span);
            } else {
                $childNode->appendChild($doc->createTextNode($newText));
            }

            $itemIndex++;
        }
    }

    /**
     * Extract translatable metadata strings from imageGalleryImages.
     * Field order per item is fixed to keep mapping stable.
     * @param array $block
     * @return array<int, string>
     */
    private static function extractImageGalleryMetadataTexts(array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        if (!is_array($attrs)) {
            return [];
        }

        $images = $attrs['imageGalleryImages'] ?? [];
        if (!is_array($images)) {
            return [];
        }

        $texts = [];
        foreach ($images as $imageItem) {
            if (!is_array($imageItem)) {
                continue;
            }
            foreach (['alt', 'caption', 'description', 'title'] as $field) {
                $value = trim((string) ($imageItem[$field] ?? ''));
                if ($value !== '') {
                    $texts[] = $value;
                }
            }
        }

        return $texts;
    }

    /**
     * Replace image-grid metadata values and sync rendered HTML.
     * @param array $block
     * @param array $originals
     * @param array $translated
     * @return array
     */
    private static function replaceImageGridMetadataTexts(array $block, array $originals, array $translated): array
    {
        $replacement = self::replaceImageGalleryMetadataInAttrs($block, $translated);
        $block = $replacement['block'];
        return self::syncImageGridInnerHtml($block, $replacement['images']);
    }

    /**
     * Replace image-gallery metadata values and sync rendered HTML.
     * @param array $block
     * @param array $originals
     * @param array $translated
     * @return array
     */
    private static function replaceImageGalleryMetadataTexts(array $block, array $originals, array $translated): array
    {
        $replacement = self::replaceImageGalleryMetadataInAttrs($block, $translated);
        $block = $replacement['block'];
        return self::syncImageGalleryInnerHtml($block, $replacement['images']);
    }

    /**
     * Apply translated metadata values to attrs.imageGalleryImages in deterministic order.
     * @param array $block
     * @param array $translated
     * @return array{block: array, images: array<int, array<string, mixed>>}
     */
    private static function replaceImageGalleryMetadataInAttrs(array $block, array $translated): array
    {
        if (!isset($block['attrs']) || !is_array($block['attrs'])) {
            return ['block' => $block, 'images' => []];
        }

        $images = $block['attrs']['imageGalleryImages'] ?? [];
        if (!is_array($images)) {
            return ['block' => $block, 'images' => []];
        }

        $index = 0;
        foreach ($images as $imageIndex => $imageItem) {
            if (!is_array($imageItem)) {
                continue;
            }
            foreach (['alt', 'caption', 'description', 'title'] as $field) {
                $currentValue = trim((string) ($imageItem[$field] ?? ''));
                if ($currentValue === '') {
                    continue;
                }
                if (!isset($translated[$index])) {
                    $index++;
                    continue;
                }
                $newValue = trim((string) $translated[$index]);
                if ($newValue !== '') {
                    $images[$imageIndex][$field] = $newValue;
                }
                $index++;
            }
        }

        $block['attrs']['imageGalleryImages'] = $images;
        return ['block' => $block, 'images' => $images];
    }

    /**
     * Sync image-grid rendered markup from translated metadata attrs.
     * @param array $block
     * @param array<int, array<string, mixed>> $images
     * @return array
     */
    private static function syncImageGridInnerHtml(array $block, array $images): array
    {
        if (!self::hasDomSupport()) {
            return $block;
        }

        $innerHTML = self::getBlockInnerHtml($block);
        if ($innerHTML === '') {
            return $block;
        }

        $wrapperHtml = '<div id="nct-image-grid-root-wrapper">' . $innerHTML . '</div>';
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8">' . $wrapperHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            return $block;
        }

        $xpath = new \DOMXPath($doc);
        $items = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-image-grid__grid__item ")]');
        if ($items instanceof \DOMNodeList) {
            foreach ($items as $itemIndex => $itemNode) {
                if (!$itemNode instanceof \DOMElement || !isset($images[$itemIndex]) || !is_array($images[$itemIndex])) {
                    continue;
                }
                $imageData = $images[$itemIndex];

                $imgNode = $xpath->query('.//img', $itemNode)->item(0);
                if ($imgNode instanceof \DOMElement) {
                    $imgNode->setAttribute('alt', (string) ($imageData['alt'] ?? ''));
                }

                $linkNode = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-image-grid__link ")]', $itemNode)->item(0);
                if ($linkNode instanceof \DOMElement) {
                    $linkNode->setAttribute('data-sub-html', (string) ($imageData['description'] ?? ''));
                }

                $titleNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " nectar-blocks-image-grid__grid__item__title ")]', $itemNode)->item(0);
                if ($titleNode instanceof \DOMElement) {
                    while ($titleNode->firstChild) {
                        $titleNode->removeChild($titleNode->firstChild);
                    }
                    $titleNode->appendChild($doc->createTextNode((string) ($imageData['title'] ?? '')));
                }
            }
        }

        $wrapper = $doc->getElementById('nct-image-grid-root-wrapper');
        if (!$wrapper instanceof \DOMElement) {
            return $block;
        }

        $newInnerHTML = '';
        foreach ($wrapper->childNodes as $childNode) {
            $newInnerHTML .= $doc->saveHTML($childNode);
        }
        if ($newInnerHTML !== '') {
            self::setBlockInnerHtml($block, $newInnerHTML);
        }

        return $block;
    }

    /**
     * Sync image-gallery rendered markup from translated metadata attrs.
     * @param array $block
     * @param array<int, array<string, mixed>> $images
     * @return array
     */
    private static function syncImageGalleryInnerHtml(array $block, array $images): array
    {
        if (!self::hasDomSupport()) {
            return $block;
        }

        $innerHTML = self::getBlockInnerHtml($block);
        if ($innerHTML === '') {
            return $block;
        }

        $wrapperHtml = '<div id="nct-image-gallery-root-wrapper">' . $innerHTML . '</div>';
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $loaded = @$doc->loadHTML('<?xml encoding="UTF-8">' . $wrapperHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            return $block;
        }

        $xpath = new \DOMXPath($doc);
        $slides = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " swiper-slide ")]');
        if ($slides instanceof \DOMNodeList) {
            foreach ($slides as $slideIndex => $slideNode) {
                if (!$slideNode instanceof \DOMElement || !isset($images[$slideIndex]) || !is_array($images[$slideIndex])) {
                    continue;
                }
                $imgNode = $xpath->query('.//img', $slideNode)->item(0);
                if ($imgNode instanceof \DOMElement) {
                    $imgNode->setAttribute('alt', (string) ($images[$slideIndex]['alt'] ?? ''));
                }
            }
        }

        $wrapper = $doc->getElementById('nct-image-gallery-root-wrapper');
        if (!$wrapper instanceof \DOMElement) {
            return $block;
        }

        $newInnerHTML = '';
        foreach ($wrapper->childNodes as $childNode) {
            $newInnerHTML .= $doc->saveHTML($childNode);
        }
        if ($newInnerHTML !== '') {
            self::setBlockInnerHtml($block, $newInnerHTML);
        }

        return $block;
    }

    /**
     * Build chunks of texts that respect DeepL limits: max 50 texts and max request size (128 KiB; we use 100 KiB)
     * @param array $texts Flat list of strings to translate
     * @return array<int, array<int, string>> Chunks of texts
     */
    private static function chunkTextsByDeepLLimits(array $texts): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentBytes = 0;

        foreach ($texts as $text) {
            $textBytes = strlen($text);
            $wouldExceedSize = ($currentBytes + $textBytes) > self::DEEPL_MAX_REQUEST_BYTES;
            $wouldExceedCount = count($currentChunk) >= self::DEEPL_BATCH_SIZE;

            if ($currentChunk !== [] && ($wouldExceedSize || $wouldExceedCount)) {
                $chunks[] = $currentChunk;
                $currentChunk = [];
                $currentBytes = 0;
            }

            // Single text larger than limit: put in its own chunk to avoid oversized request
            if ($textBytes > self::DEEPL_MAX_REQUEST_BYTES) {
                if ($currentChunk !== []) {
                    $chunks[] = $currentChunk;
                    $currentChunk = [];
                    $currentBytes = 0;
                }
                $chunks[] = [$text];
                continue;
            }

            $currentChunk[] = $text;
            $currentBytes += $textBytes;
        }

        if ($currentChunk !== []) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Count translatable strings in block content (for preflight / progress UI)
     * Uses the same collect logic as translatePostContent but does not translate
     * @param string $content Post content with blocks
     * @return int Number of translatable strings in block content
     */
    public static function countTranslatableBlockStrings(string $content): int
    {
        if (!has_blocks($content)) {
            return 0;
        }
        $blocks = parse_blocks($content);
        if (empty($blocks)) {
            return 0;
        }
        $texts = [];
        $descriptors = [];
        self::collectBlockTexts($blocks, $texts, $descriptors, []);
        return count($texts);
    }

    /**
     * Collect unique unsupported block names from Gutenberg content.
     * A block is considered supported when it exists in the translation mapping.
     *
     * @param string $content Post content with blocks
     * @return array<int, string>
     */
    public static function getUnsupportedBlockNames(string $content): array
    {
        if (!has_blocks($content)) {
            return [];
        }

        $blocks = parse_blocks($content);
        if (empty($blocks) || !is_array($blocks)) {
            return [];
        }

        $supportedConfigs = self::getBlockConfigs();
        $unsupportedMap = [];
        self::collectUnsupportedBlockNames($blocks, $supportedConfigs, $unsupportedMap);

        $unsupported = array_keys($unsupportedMap);
        sort($unsupported, SORT_NATURAL);

        return $unsupported;
    }

    /**
     * Collect unique reusable block reference IDs used by core/block in Gutenberg content.
     *
     * @param string $content Post content with blocks
     * @return array<int, int>
     */
    public static function getReusableBlockRefs(string $content): array
    {
        if (!has_blocks($content)) {
            return [];
        }

        $blocks = parse_blocks($content);
        if (empty($blocks) || !is_array($blocks)) {
            return [];
        }

        $refMap = [];
        self::collectReusableBlockRefs($blocks, $refMap);
        $refs = array_keys($refMap);
        sort($refs, SORT_NUMERIC);

        return array_map('intval', $refs);
    }

    /**
     * Recursively collect unsupported block names.
     *
     * @param array $blocks
     * @param array $supportedConfigs
     * @param array $unsupportedMap
     * @return void
     */
    private static function collectUnsupportedBlockNames(array $blocks, array $supportedConfigs, array &$unsupportedMap): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockName = (string) ($block['blockName'] ?? '');
            if ($blockName !== '' && !array_key_exists($blockName, $supportedConfigs)) {
                $unsupportedMap[$blockName] = true;
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::collectUnsupportedBlockNames($block['innerBlocks'], $supportedConfigs, $unsupportedMap);
            }
        }
    }

    /**
     * Recursively collect core/block reference IDs.
     *
     * @param array $blocks
     * @param array<int|string, bool> $refMap
     * @return void
     */
    private static function collectReusableBlockRefs(array $blocks, array &$refMap): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockName = (string) ($block['blockName'] ?? '');
            if ($blockName === 'core/block') {
                $ref = $block['attrs']['ref'] ?? null;
                if (is_numeric($ref) && (int) $ref > 0) {
                    $refMap[(int) $ref] = true;
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::collectReusableBlockRefs($block['innerBlocks'], $refMap);
            }
        }
    }

    /**
     * Collect all translatable texts from blocks into a flat array and build descriptors for apply phase
     * @param array $blocks Block array from parse_blocks()
     * @param array $texts Output: flat list of strings to translate
     * @param array $descriptors Output: list of descriptors (path, type, start, count, strategy data)
     * @param array $path Current path (indices from root)
     */
    private static function collectBlockTexts(array $blocks, array &$texts, array &$descriptors, array $path): void
    {
        foreach ($blocks as $index => $block) {
            $currentPath = array_merge($path, [$index]);

            // Recurse into inner blocks first
            if (!empty($block['innerBlocks'])) {
                self::collectBlockTexts($block['innerBlocks'], $texts, $descriptors, $currentPath);
            }

            $blockName = $block['blockName'] ?? null;
            if (empty($blockName)) {
                continue;
            }

            $config = self::getBlockTranslationConfig($blockName);
            if (!$config) {
                continue;
            }

            $start = count($texts);

            switch ($config['strategy']) {
                case 'html':
                    $innerHTML = $block['innerHTML'] ?? '';
                    $innerContent = $block['innerContent'] ?? [];
                    if (empty($innerHTML) && !empty($innerContent) && is_array($innerContent)) {
                        $placeholder = '<!-- INNER_BLOCK_PLACEHOLDER -->';
                        $contentParts = array_map(function ($item) use ($placeholder) {
                            return is_string($item) ? $item : $placeholder;
                        }, $innerContent);
                        $innerHTML = implode('', $contentParts);
                    }
                    if (!empty(trim($innerHTML))) {
                        $texts[] = $innerHTML;
                        $descriptors[] = [
                            'path' => $currentPath,
                            'type' => 'html',
                            'start' => $start,
                            'count' => 1,
                        ];
                    }
                    break;

                case 'regex':
                    $pattern = $config['rules']['pattern'] ?? null;
                    if (!$pattern) {
                        break;
                    }
                    $innerHTML = $block['innerHTML'] ?? '';
                    $innerContent = $block['innerContent'] ?? [];
                    if (empty($innerHTML) && !empty($innerContent) && is_array($innerContent)) {
                        $innerHTML = implode('', array_filter($innerContent, function ($item) {
                            return is_string($item);
                        }));
                    }
                    if (empty($innerHTML)) {
                        break;
                    }
                    $contentGroup = $config['rules']['content_group'] ?? null;
                    $extracted = self::extractTextsWithRegex($innerHTML, $pattern, $contentGroup);
                    if (!empty($extracted['html'])) {
                        foreach ($extracted['html'] as $html) {
                            $texts[] = $html;
                        }
                        $descriptors[] = [
                            'path' => $currentPath,
                            'type' => 'regex',
                            'start' => $start,
                            'count' => count($extracted['html']),
                            'pattern' => $pattern,
                            'content_group' => $contentGroup,
                            'innerHTML' => $innerHTML,
                            'innerContent' => $innerContent,
                            'extracted_html' => $extracted['html'],
                            'text_only' => $extracted['text'] ?? [],
                        ];
                    } else {
                        // Raw content fallback (no wrapper tags)
                        $tagInPattern = '';
                        if (preg_match('/<([a-z0-9]+)/i', $pattern, $tagMatch)) {
                            $tagInPattern = strtolower($tagMatch[1]);
                        }
                        $hasExpectedTag = $tagInPattern && stripos($innerHTML, '<' . $tagInPattern) !== false;
                        if (!$hasExpectedTag && !empty(trim($innerHTML))) {
                            $texts[] = trim($innerHTML);
                            $descriptors[] = [
                                'path' => $currentPath,
                                'type' => 'regex_raw',
                                'start' => $start,
                                'count' => 1,
                                'innerContent' => $block['innerContent'] ?? [],
                            ];
                        }
                    }
                    break;

                case 'acf_fields':
                    $data = $block['attrs']['data'] ?? [];
                    $fieldsConfig = $config['fields'] ?? [];
                    if (empty($fieldsConfig)) {
                        break;
                    }
                    $usedFields = [];
                    foreach ($fieldsConfig as $field) {
                        $type = $field['type'] ?? 'string';
                        if ($type === 'repeater') {
                            self::expandRepeaterFields($data, $field, $texts, $usedFields);
                        } else {
                            $key = $field['key'] ?? '';
                            if ($key === '') {
                                continue;
                            }
                            $handlers = self::getAcfFieldHandlers();
                            if (!isset($handlers[$type])) {
                                continue;
                            }
                            $extracted = call_user_func($handlers[$type]['extract'], $data, $key);
                            if (is_string($extracted) && trim($extracted) !== '') {
                                $texts[] = trim($extracted);
                                $usedFields[] = ['key' => $key, 'type' => $type];
                            }
                        }
                    }
                    if (!empty($usedFields)) {
                        $descriptors[] = [
                            'path' => $currentPath,
                            'type' => 'acf_fields',
                            'start' => $start,
                            'count' => count($usedFields),
                            'fields' => $usedFields,
                        ];
                    }
                    break;

                case 'attrs_first':
                    $forceSync = !empty($config['always_sync']);
                    $fields = $config['fields'] ?? [];
                    if (!is_array($fields)) {
                        break;
                    }
                    $attrs = $block['attrs'] ?? [];
                    if (!is_array($attrs)) {
                        $attrs = [];
                    }
                    $toAdd = [];
                    foreach ($fields as $pathStr) {
                        $val = self::getAttrStringByPath($attrs, (string) $pathStr);
                        $val = trim((string) $val);
                        if ($val !== '') {
                            $toAdd[] = $val;
                        } else {
                            $toAdd[] = '';
                        }
                    }
                    //only add descriptor if at least one field has non-empty content
                    $nonEmpty = array_values(array_filter($toAdd, function ($v) {
                        return trim((string) $v) !== '';
                    }));
                    if ($nonEmpty !== [] || $forceSync) {
                        $descriptorStart = $start;
                        $descriptorCount = 0;
                        $descriptorOriginals = [];
                        if ($nonEmpty !== []) {
                            foreach ($toAdd as $str) {
                                $texts[] = (string) $str;
                            }
                            $descriptorCount = count($toAdd);
                            $descriptorOriginals = $toAdd;
                        }
                        $descriptors[] = [
                            'path' => $currentPath,
                            'type' => 'attrs_first',
                            'start' => $descriptorStart,
                            'count' => $descriptorCount,
                            'config' => $config,
                            'originals' => $descriptorOriginals,
                        ];
                    }
                    break;

                case 'callback':
                    if (!isset($config['extract']) || !is_callable($config['extract'])) {
                        break;
                    }
                    $extracted = call_user_func($config['extract'], $block);
                    if (!is_array($extracted)) {
                        $extracted = $extracted !== '' ? [$extracted] : [];
                    }
                    $toAdd = [];
                    foreach ($extracted as $str) {
                        if (trim((string) $str) !== '') {
                            $toAdd[] = is_string($str) ? $str : (string) $str;
                        }
                    }
                    if (!empty($toAdd)) {
                        foreach ($toAdd as $str) {
                            $texts[] = $str;
                        }
                        $descriptors[] = [
                            'path' => $currentPath,
                            'type' => 'callback',
                            'start' => $start,
                            'count' => count($toAdd),
                            'config' => $config,
                            'originals' => $toAdd,
                        ];
                    }
                    break;
            }
        }
    }

    /**
     * Apply translated texts back to blocks using descriptors
     * @param array $blocks Block tree (modified in place)
     * @param array $translatedTexts Flat array of translated strings (same order as collect)
     * @param array $descriptors Descriptors from collectBlockTexts
     * @param array $path Current path
     */
    private static function applyBlockTranslations(array &$blocks, array $translatedTexts, array $descriptors, array $path): void
    {
        $pathKey = implode('.', $path);
        $descriptorByPath = [];
        foreach ($descriptors as $d) {
            $descriptorByPath[implode('.', $d['path'])] = $d;
        }

        foreach (array_keys($blocks) as $index) {
            $currentPath = array_merge($path, [$index]);
            $currentPathKey = implode('.', $currentPath);
            $block = &$blocks[$index];

            if (!empty($block['innerBlocks'])) {
                self::applyBlockTranslations($block['innerBlocks'], $translatedTexts, $descriptors, $currentPath);
            }

            $desc = $descriptorByPath[$currentPathKey] ?? null;
            if (!$desc) {
                continue;
            }

            $slice = array_slice($translatedTexts, $desc['start'], $desc['count']);

            switch ($desc['type']) {
                case 'html':
                    if (isset($slice[0])) {
                        $translatedHTML = $slice[0];
                        $rewritten = InternalLinkTranslator::rewriteAnchorsInHtmlFragment(
                            (string) $translatedHTML,
                            (string) (self::$currentTargetLang ?? ''),
                            [
                                'prefer_anchor_id' => true,
                                'update_anchor_id' => true,
                                'source_lang' => (string) (self::$currentSourceLang ?? ''),
                            ]
                        );
                        if (is_array($rewritten) && isset($rewritten['html']) && is_string($rewritten['html']) && $rewritten['html'] !== '') {
                            $translatedHTML = $rewritten['html'];
                        }
                        $block['innerHTML'] = $translatedHTML;
                        if (empty($block['innerBlocks'])) {
                            $block['innerContent'] = [$translatedHTML];
                        } else {
                            $innerContent = $block['innerContent'] ?? [];
                            $placeholder = '<!-- INNER_BLOCK_PLACEHOLDER -->';
                            $translatedParts = explode($placeholder, $translatedHTML);
                            $newInnerContent = [];
                            $partIndex = 0;
                            foreach ($innerContent as $originalPart) {
                                if (is_string($originalPart)) {
                                    $newInnerContent[] = $translatedParts[$partIndex] ?? $originalPart;
                                    $partIndex++;
                                } else {
                                    $newInnerContent[] = null;
                                }
                            }
                            while ($partIndex < count($translatedParts)) {
                                if (!empty($translatedParts[$partIndex])) {
                                    $newInnerContent[] = $translatedParts[$partIndex];
                                }
                                $partIndex++;
                            }
                            $block['innerContent'] = $newInnerContent;
                            $block['innerHTML'] = implode('', array_map(function ($item) {
                                return is_string($item) ? $item : '';
                            }, $block['innerContent']));
                        }
                    }
                    break;

                case 'regex':
                    $innerHTML = $desc['innerHTML'];
                    $pattern = $desc['pattern'];
                    $textOnly = $desc['text_only'] ?? [];
                    $originalHtml = $desc['extracted_html'] ?? $slice;
                    $translatedHTML = self::replaceTextsWithRegex($innerHTML, $originalHtml, $slice, $pattern, $textOnly);
                    $block['innerHTML'] = $translatedHTML;
                    if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                        if (empty($block['innerBlocks'])) {
                            $block['innerContent'] = [$translatedHTML];
                        } else {
                            foreach ($block['innerContent'] as $i => $contentPart) {
                                if (is_string($contentPart) && !empty($contentPart)) {
                                    $block['innerContent'][$i] = self::replaceTextsWithRegex(
                                        $contentPart,
                                        $originalHtml,
                                        $slice,
                                        $pattern,
                                        $textOnly
                                    );
                                }
                            }
                        }
                    } else {
                        $block['innerContent'] = [$translatedHTML];
                    }
                    break;

                case 'regex_raw':
                    if (isset($slice[0])) {
                        $block['innerHTML'] = $slice[0];
                        if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                            foreach ($block['innerContent'] as $i => $contentPart) {
                                if (is_string($contentPart) && !empty(trim($contentPart))) {
                                    $block['innerContent'][$i] = $slice[0];
                                    break;
                                }
                            }
                        } else {
                            $block['innerContent'] = [$slice[0]];
                        }
                    }
                    break;

                case 'attrs_first':
                    $config = $desc['config'] ?? null;
                    if (!is_array($config)) {
                        break;
                    }
                    $originals = $desc['originals'] ?? [];
                    if (!is_array($originals)) {
                        $originals = [];
                    }
                    $block = self::syncAttrsFirstBlock($block, $config, $originals, $slice);
                    break;

                case 'acf_fields':
                    if (!isset($block['attrs'])) {
                        $block['attrs'] = [];
                    }
                    if (!isset($block['attrs']['data'])) {
                        $block['attrs']['data'] = [];
                    }
                    $data = &$block['attrs']['data'];
                    $fields = $desc['fields'] ?? [];
                    $handlers = self::getAcfFieldHandlers();
                    foreach ($fields as $i => $fieldDesc) {
                        $key = $fieldDesc['key'] ?? '';
                        $type = $fieldDesc['type'] ?? 'string';
                        if ($key === '' || !isset($handlers[$type]) || !isset($slice[$i])) {
                            continue;
                        }
                        $translated = is_string($slice[$i]) ? trim($slice[$i]) : (string) $slice[$i];
                        if ($translated !== '') {
                            $handlers[$type]['apply']($data, $key, $slice[$i]);
                        }
                    }
                    break;

                case 'callback':
                    if (isset($desc['config']['replace']) && is_callable($desc['config']['replace'])) {
                        $originals = $desc['originals'] ?? array_slice($translatedTexts, $desc['start'], $desc['count']);
                        $block = call_user_func($desc['config']['replace'], $block, $originals, $slice);
                    }
                    break;
            }
        }
    }

    /**
     * Recursively translate blocks array
     * @param array $blocks Array of block arrays
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @return array Translated blocks array
     */
    private static function translateBlocks(array $blocks, string $sourceLang, string $targetLang): array
    {
        $translatedBlocks = [];

        foreach ($blocks as $block) {
            $translatedBlocks[] = self::translateBlock($block, $sourceLang, $targetLang);
        }

        return $translatedBlocks;
    }

    /**
     * Translate a single block
     * @param array $block Block array from parse_blocks()
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @return array Translated block array
     */
    private static function translateBlock(array $block, string $sourceLang, string $targetLang): array
    {
        // Process inner blocks first (recursively)
        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = self::translateBlocks($block['innerBlocks'], $sourceLang, $targetLang);
        }

        // Get block name
        $blockName = $block['blockName'] ?? null;
        
        if (empty($blockName)) {
            // Core block (empty blockName) - skip translation
            return $block;
        }

        // Get translation config for this block
        $config = self::getBlockTranslationConfig($blockName);
        
        if (!$config) {
            // No translation config for this block - return as is
            return $block;
        }

        // Translate based on strategy
        switch ($config['strategy']) {
            case 'html':
                return self::translateBlockWithHtml($block, $sourceLang, $targetLang);
            
            case 'regex':
                return self::translateBlockWithRegex($block, $config, $sourceLang, $targetLang);
            
            case 'xpath':
                // Future implementation
                return $block;
            
            case 'callback':
                // Future implementation
                if (isset($config['extract']) && is_callable($config['extract'])) {
                    return self::translateBlockWithCallback($block, $config, $sourceLang, $targetLang);
                }
                return $block;
            
            case 'attributes':
                // Future implementation
                return $block;
            
            default:
                return $block;
        }
    }

    /**
     * Translate block using HTML strategy
     * Passes entire innerHTML to DeepL with HTML tag handling enabled
     * DeepL automatically preserves HTML tags while translating text content
     * @param array $block Block array
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @return array Translated block array
     */
    private static function translateBlockWithHtml(array $block, string $sourceLang, string $targetLang): array
    {
        // Get block content
        // For blocks with inner blocks, reconstruct content from innerContent with placeholders
        // For simple blocks, use innerHTML directly
        $innerHTML = $block['innerHTML'] ?? '';
        $innerContent = $block['innerContent'] ?? [];
        
        // Reconstruct from innerContent if needed (handles inner blocks)
        // Replace null values (inner block placeholders) with a placeholder string
        if (empty($innerHTML) && !empty($innerContent) && is_array($innerContent)) {
            $placeholder = '<!-- INNER_BLOCK_PLACEHOLDER -->';
            $contentParts = array_map(function($item) use ($placeholder) {
                return is_string($item) ? $item : $placeholder;
            }, $innerContent);
            $innerHTML = implode('', $contentParts);
        }
        
        if (empty(trim($innerHTML))) {
            return $block;
        }
        
        // Translate entire HTML with DeepL HTML mode
        // DeepL will preserve HTML tags and translate only text content
        $translationResult = DeepLTranslator::translateTexts(
            [$innerHTML], 
            $sourceLang, 
            $targetLang,
            ['tag_handling' => 'html'] // Enable HTML mode
        );
        
        if (!$translationResult['success'] || empty($translationResult['translations'][0])) {
            error_log('Novi content translator: HTML translation failed for block ' . ($block['blockName'] ?? 'unknown') . ': ' . ($translationResult['error'] ?? 'Unknown error'));
            return $block;
        }
        
        $translatedHTML = $translationResult['translations'][0];

        $rewritten = InternalLinkTranslator::rewriteAnchorsInHtmlFragment(
            (string) $translatedHTML,
            $targetLang,
            [
                'prefer_anchor_id' => true,
                'update_anchor_id' => true,
                'source_lang' => $sourceLang,
            ]
        );
        if (is_array($rewritten) && isset($rewritten['html']) && is_string($rewritten['html']) && $rewritten['html'] !== '') {
            $translatedHTML = $rewritten['html'];
        }
        
        // Update block
        $block['innerHTML'] = $translatedHTML;
        
        // Update innerContent to match innerHTML
        // For simple blocks without inner blocks, innerContent is just [innerHTML]
        if (empty($block['innerBlocks'])) {
            $block['innerContent'] = [$translatedHTML];
        } else {
            // Block with inner blocks - split translated HTML back using placeholder
            $placeholder = '<!-- INNER_BLOCK_PLACEHOLDER -->';
            $translatedParts = explode($placeholder, $translatedHTML);
            
            // Reconstruct innerContent array with null placeholders for inner blocks
            $newInnerContent = [];
            $partIndex = 0;
            foreach ($innerContent as $originalPart) {
                if (is_string($originalPart)) {
                    // This was a string part - use translated version
                    if (isset($translatedParts[$partIndex])) {
                        $newInnerContent[] = $translatedParts[$partIndex];
                    } else {
                        $newInnerContent[] = $originalPart; // Fallback
                    }
                    $partIndex++;
                } else {
                    // This was null (inner block placeholder) - keep as null
                    $newInnerContent[] = null;
                }
            }
            
            // Add any remaining translated parts
            while ($partIndex < count($translatedParts)) {
                if (!empty($translatedParts[$partIndex])) {
                    $newInnerContent[] = $translatedParts[$partIndex];
                }
                $partIndex++;
            }
            
            $block['innerContent'] = $newInnerContent;
            // Reconstruct innerHTML from updated innerContent
            $block['innerHTML'] = implode('', array_map(function($item) {
                return is_string($item) ? $item : '';
            }, $block['innerContent']));
        }
        
        return $block;
    }

    /**
     * Translate block using regex strategy
     * @param array $block Block array
     * @param array $config Block translation config
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @return array Translated block array
     */
    private static function translateBlockWithRegex(array $block, array $config, string $sourceLang, string $targetLang): array
    {
        $pattern = $config['rules']['pattern'] ?? null;
        
        if (!$pattern) {
            return $block;
        }

        // Get block content to translate
        // For blocks with render_callback, innerHTML might be raw content without wrapper tags
        // The render template adds the wrapper tags (<p>, <li>, etc.)
        $innerHTML = $block['innerHTML'] ?? '';
        $innerContent = $block['innerContent'] ?? [];
        
        // If innerHTML is empty but innerContent exists, reconstruct it
            if (empty($innerHTML) && !empty($innerContent) && is_array($innerContent)) {
            $innerHTML = implode('', array_filter($innerContent, function($item) {
                return is_string($item);
            }));
        }
        
        if (empty($innerHTML)) {
            return $block;
        }

        // For blocks with render_callback, innerHTML might not have wrapper tags
        // Try to extract with the pattern first, but if it fails, try translating raw content
        $contentGroup = $config['rules']['content_group'] ?? null;
        $extracted = self::extractTextsWithRegex($innerHTML, $pattern, $contentGroup);
        
        // If pattern doesn't match, the content might be raw (without wrapper tags)
        // This is common for blocks with render_callback where the template adds the wrapper tags
        if (empty($extracted['html']) || empty($extracted['text'])) {
            // Extract the expected tag from the pattern (e.g., 'p' from '/<p([^>]*)>(.*?)<\/p>/s')
            $tagInPattern = '';
            if (preg_match('/<([a-z0-9]+)/i', $pattern, $tagMatch)) {
                $tagInPattern = strtolower($tagMatch[1]);
            }
            
            // Check if innerHTML contains the expected opening tag
            // For blocks with render_callback, innerHTML is often just the raw content
            $hasExpectedTag = $tagInPattern && stripos($innerHTML, '<' . $tagInPattern) !== false;
            
            // If the expected tag is not found, treat innerHTML as raw content
            if (!$hasExpectedTag) {
                // Raw content without wrapper tags - translate directly
                // This preserves HTML like <br> tags that might be in the content
                $textToTranslate = trim($innerHTML);
                if (!empty($textToTranslate)) {
                    $translationResult = DeepLTranslator::translateTexts([$textToTranslate], $sourceLang, $targetLang);
                    
                    if ($translationResult['success'] && !empty($translationResult['translations'][0])) {
                        $translatedContent = $translationResult['translations'][0];
                        $block['innerHTML'] = $translatedContent;
                        
                        // Update innerContent to match innerHTML
                        if (isset($block['innerContent']) && is_array($block['innerContent'])) {
                            // Replace string parts in innerContent
                            foreach ($block['innerContent'] as $index => $contentPart) {
                                if (is_string($contentPart) && !empty(trim($contentPart))) {
                                    $block['innerContent'][$index] = $translatedContent;
                                    break; // Usually only one content part for simple blocks
                                }
                            }
                        } else {
                            $block['innerContent'] = [$translatedContent];
                        }
                    } else {
                        error_log('Novi content translator: Translation failed for raw content in block ' . ($block['blockName'] ?? 'unknown') . ': ' . ($translationResult['error'] ?? 'Unknown error'));
                    }
                }
                return $block;
            }
            
            // Pattern should have matched but didn't - log for debugging
            error_log('Novi content translator: No matches found for block ' . ($block['blockName'] ?? 'unknown') . ' with pattern. innerHTML: ' . substr($innerHTML, 0, 200));
            return $block;
        }

        // Use HTML content for translation (DeepL preserves HTML tags)
        $textsToTranslate = $extracted['html'];
        $textOnly = $extracted['text'];

        // Translate texts in batch
        $translationResult = DeepLTranslator::translateTexts($textsToTranslate, $sourceLang, $targetLang);
        
        if (!$translationResult['success']) {
            error_log('Novi content translator: Block translation failed for ' . ($block['blockName'] ?? 'unknown') . ': ' . ($translationResult['error'] ?? 'Unknown error'));
            return $block;
        }

        $translatedTexts = $translationResult['translations'] ?? $textsToTranslate;

        // Replace texts in HTML (using HTML content for matching)
        $translatedHTML = self::replaceTextsWithRegex($innerHTML, $textsToTranslate, $translatedTexts, $pattern, $textOnly);

        // Update block with translated content
        $block['innerHTML'] = $translatedHTML;
        
        // Update innerContent to match innerHTML
        // innerContent is an array where strings are content and null values are placeholders for inner blocks
        if (isset($block['innerContent']) && is_array($block['innerContent'])) {
            // Reconstruct innerContent from translated innerHTML
            // For simple blocks without inner blocks, innerContent might just be [innerHTML]
            if (empty($block['innerBlocks'])) {
                // Simple block - replace the entire innerContent
                $block['innerContent'] = [$translatedHTML];
            } else {
                // Block with inner blocks - need to preserve null placeholders
                // Find the string parts in innerContent and update them
                foreach ($block['innerContent'] as $index => $contentPart) {
                    if (is_string($contentPart) && !empty($contentPart)) {
                        // Update string parts in innerContent
                        $block['innerContent'][$index] = self::replaceTextsWithRegex($contentPart, $textsToTranslate, $translatedTexts, $pattern, $textOnly);
                    }
                }
            }
        } else {
            // If innerContent doesn't exist, create it from innerHTML
            $block['innerContent'] = [$translatedHTML];
        }

        return $block;
    }

    /**
     * Translate block using callback strategy
     * @param array $block Block array
     * @param array $config Block translation config
     * @param string $sourceLang Source language code
     * @param string $targetLang Target language code
     * @return array Translated block array
     */
    private static function translateBlockWithCallback(array $block, array $config, string $sourceLang, string $targetLang): array
    {
        if (!isset($config['extract']) || !is_callable($config['extract'])) {
            return $block;
        }

        // Extract texts using custom callback
        $textsToTranslate = call_user_func($config['extract'], $block);
        
        if (empty($textsToTranslate)) {
            return $block;
        }

        // Ensure textsToTranslate is an array
        if (!is_array($textsToTranslate)) {
            $textsToTranslate = [$textsToTranslate];
        }

        // Translate texts in batch
        $translationResult = DeepLTranslator::translateTexts($textsToTranslate, $sourceLang, $targetLang);
        
        if (!$translationResult['success']) {
            error_log('Novi content translator: Block translation failed for ' . ($block['blockName'] ?? 'unknown') . ': ' . ($translationResult['error'] ?? 'Unknown error'));
            return $block;
        }

        $translatedTexts = $translationResult['translations'] ?? $textsToTranslate;

        // Replace texts using custom callback if provided, otherwise use default replacement
        if (isset($config['replace']) && is_callable($config['replace'])) {
            return call_user_func($config['replace'], $block, $textsToTranslate, $translatedTexts);
        }

        return $block;
    }

    /**
     * Extract texts from HTML using regex pattern
     * Returns both the full HTML content and text-only versions for translation
     * @param string $html HTML content
     * @param string $pattern Regex pattern
     * @return array Array with 'html' and 'text' keys, each containing arrays of content to translate
     */
    private static function extractTextsWithRegex(string $html, string $pattern, ?int $contentGroup = null): array
    {
        $htmlContents = [];
        $textContents = [];
        $matches = [];

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Find the content capture group
                // For heading: /<(h[1-6]|span)([^>]*)>(.*?)<\/\1>/s -> match[3] is content
                // For paragraph: /<p([^>]*)>(.*?)<\/p>/s -> match[2] is content
                // For list-item: /<li([^>]*)>(.*?)<\/li>/s -> match[2] is content
                $htmlContent = null;
                
                // If content group is explicitly specified, use it directly
                if ($contentGroup !== null && isset($match[$contentGroup])) {
                    $htmlContent = $match[$contentGroup];
                } else {
                    // Auto-detect content group if not explicitly specified
                    // Determine which capture group contains the content based on pattern structure
                    // For patterns with 2 capture groups (tag + attributes, then content), content is match[2]
                    // For patterns with 3 capture groups (tag type, attributes, content), content is match[3]
                    $totalGroups = count($match) - 1; // Subtract 1 for match[0] which is the full match
                    
                    // Check from the end backwards to find the content group
                    // The last non-empty group is usually the content
                    for ($i = count($match) - 1; $i > 0; $i--) {
                        if (isset($match[$i]) && $match[$i] !== '') {
                            $matchValue = $match[$i];
                        
                            // Skip match[0] (full match) - we want capture groups only
                            if ($i === 0) {
                                continue;
                            }
                            
                            // Attributes typically:
                            // - Don't start with '<' (they're like ' class="..."')
                            // - Start with a space or contain '=' (like ' class="..."' or 'id="..."')
                            // - Are usually shorter
                            // Content typically:
                            // - Starts with '<' (HTML tags) or is plain text
                            // - Can be longer
                            // - May contain '=' but within HTML attributes (like <a href="...">)
                            // - For simple patterns, if it's the last group and not clearly attributes, it's content
                            
                            // If it starts with '<', it's definitely content (HTML)
                            if (strpos(trim($matchValue), '<') === 0) {
                                $htmlContent = $matchValue;
                                break;
                            }
                            
                            // If it doesn't start with '<' and doesn't contain '=', it's likely plain text content
                            if (strpos($matchValue, '=') === false && strlen($matchValue) > 0) {
                                $htmlContent = $matchValue;
                                break;
                            }
                            
                            // If it doesn't start with '<' but contains '=':
                            // - If it starts with a space or is short, it's likely attributes (like ' class="..."')
                            // - If it's longer and contains '<' somewhere, it's likely content with HTML
                            // - If it's the last group and other groups are clearly attributes, assume it's content
                            if (strpos($matchValue, '=') !== false) {
                                // Check if it looks like attributes (starts with space, or is short and simple)
                                $isLikelyAttributes = (
                                    strpos(trim($matchValue), ' ') === 0 || 
                                    (strlen($matchValue) < 100 && preg_match('/^[\s\w\-="\']+$/', $matchValue))
                                );
                                
                                // If it's not clearly attributes and contains HTML tags, it's content
                                if (!$isLikelyAttributes && strpos($matchValue, '<') !== false) {
                                    $htmlContent = $matchValue;
                                    break;
                                }
                                
                                // If it's the last capture group and we haven't found content yet, 
                                // and it doesn't look like simple attributes, assume it's content
                                if ($i === $totalGroups && $htmlContent === null && !$isLikelyAttributes) {
                                    $htmlContent = $matchValue;
                                    break;
                                }
                                
                                // Otherwise, it's likely attributes, continue searching
                            }
                        }
                    }
                    
                    // Fallback: if we still haven't found content and there are groups, 
                    // use the last non-empty group (excluding match[0])
                    if ($htmlContent === null && count($match) > 1) {
                        for ($i = count($match) - 1; $i > 0; $i--) {
                            if (isset($match[$i]) && $match[$i] !== '') {
                                $htmlContent = $match[$i];
                                break;
                            }
                        }
                    }
                }
                
                if ($htmlContent !== null) {
                    $textContent = wp_strip_all_tags($htmlContent);
                    $textContent = trim($textContent);
                    
                    if (!empty($textContent)) {
                        // Store both HTML and text versions
                        // DeepL can translate HTML directly, so we'll use HTML version
                        $htmlContents[] = $htmlContent;
                        $textContents[] = $textContent;
                    }
                }
            }
        }

        return [
            'html' => $htmlContents,
            'text' => $textContents,
        ];
    }

    /**
     * Replace texts in HTML using regex pattern
     * @param string $html Original HTML
     * @param array $originalHtmlContents Original HTML contents (with tags)
     * @param array $translatedTexts Translated texts (from DeepL, may contain HTML)
     * @param string $pattern Regex pattern
     * @param array $textOnly Optional text-only versions for matching (not used in replacement, kept for compatibility)
     * @return string Translated HTML
     */
    private static function replaceTextsWithRegex(string $html, array $originalHtmlContents, array $translatedTexts, string $pattern, array $textOnly = []): string
    {
        $matches = [];
        $replacements = [];

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $totalMatches = count($matches);
            // Process matches in reverse order to maintain offsets
            $matches = array_reverse($matches);
            
            foreach ($matches as $reversedIndex => $match) {
                // Calculate original index (before reversal)
                $originalIndex = $totalMatches - 1 - $reversedIndex;
                
                // Find the content capture group (same logic as extraction)
                $originalHtmlContent = null;
                
                // Check from the end backwards to find the content group
                // Use the same improved logic as extractTextsWithRegex
                for ($i = count($match) - 1; $i > 0; $i--) {
                    if (isset($match[$i]) && is_array($match[$i]) && !empty($match[$i][0])) {
                        $matchValue = $match[$i][0];
                        
                        // If it starts with '<', it's likely content (HTML)
                        if (strpos(trim($matchValue), '<') === 0) {
                            $originalHtmlContent = $matchValue;
                            break;
                        }
                        
                        // If it doesn't start with '<' and doesn't contain '=', it might be plain text content
                        if (strpos($matchValue, '=') === false && strlen($matchValue) > 0) {
                            $originalHtmlContent = $matchValue;
                            break;
                        }
                        
                        // If it doesn't start with '<' but contains '=' and is short, it's likely attributes
                        // Skip this and continue to find content
                    }
                }
                
                if ($originalHtmlContent !== null && isset($translatedTexts[$originalIndex])) {
                    $translatedHtml = $translatedTexts[$originalIndex];
                    
                    // DeepL returns HTML with tags preserved, so we can use it directly
                    // Replace the inner content with the translated content
                    $fullMatch = $match[0][0];
                    $replacement = str_replace($originalHtmlContent, $translatedHtml, $fullMatch);
                    
                    $replacements[] = [
                        'offset' => $match[0][1],
                        'length' => strlen($fullMatch),
                        'replacement' => $replacement,
                    ];
                }
            }
        }

        // Apply replacements in reverse order to maintain offsets
        foreach ($replacements as $replacement) {
            $html = substr_replace($html, $replacement['replacement'], $replacement['offset'], $replacement['length']);
        }

        return $html;
    }

    /**
     * Get translation configuration for a block type
     * @param string $blockName Block name (e.g., 'novionline/heading')
     * @return array|null Block configuration or null if not configured
     */
    private static function getBlockTranslationConfig(string $blockName): ?array
    {
        $configs = self::getBlockConfigs();
        return $configs[$blockName] ?? null;
    }

    /**
     * Get ACF field type handlers (lazy-init registry with string and link_title)
     * @return array<string, array{extract: callable, apply: callable}>
     */
    private static function getAcfFieldHandlers(): array
    {
        if (self::$acfFieldHandlers !== []) {
            return self::$acfFieldHandlers;
        }
        self::$acfFieldHandlers = [
            'string' => [
                'extract' => [self::class, 'extractAcfFieldString'],
                'apply' => [self::class, 'applyAcfFieldString'],
            ],
            'link_title' => [
                'extract' => [self::class, 'extractAcfFieldLinkTitle'],
                'apply' => [self::class, 'applyAcfFieldLinkTitle'],
            ],
        ];
        return self::$acfFieldHandlers;
    }

    /**
     * Extract string value from ACF data (text/textarea)
     * @param array $data attrs.data
     * @param string $key Field key
     * @return string Value to translate or empty to skip
     */
    private static function extractAcfFieldString(array $data, string $key): string
    {
        $v = $data[$key] ?? '';
        return trim((string) $v);
    }

    /**
     * Apply translated string to ACF data
     * @param array $data attrs.data (by reference)
     * @param string $key Field key
     * @param string $translated Translated value
     */
    private static function applyAcfFieldString(array &$data, string $key, string $translated): void
    {
        $data[$key] = $translated;
    }

    /**
     * Extract link title from ACF link field (data[key]['title'])
     * @param array $data attrs.data
     * @param string $key Field key
     * @return string Title to translate or empty to skip
     */
    private static function extractAcfFieldLinkTitle(array $data, string $key): string
    {
        $v = $data[$key] ?? null;
        if (!is_array($v) || !isset($v['title'])) {
            return '';
        }
        return trim((string) $v['title']);
    }

    /**
     * Apply translated title to ACF link field
     * @param array $data attrs.data (by reference)
     * @param string $key Field key
     * @param string $translated Translated title
     */
    private static function applyAcfFieldLinkTitle(array &$data, string $key, string $translated): void
    {
        if (isset($data[$key]) && is_array($data[$key])) {
            $data[$key]['title'] = $translated;
        }
    }

    /**
     * Expand repeater field into list of (key, type) and extract values into $texts / $usedFields
     * @param array $data attrs.data
     * @param array $field Field config with repeater + subfields
     * @param array $texts Append extracted strings
     * @param array $usedFields Append ['key' => fullKey, 'type' => type]
     */
    private static function expandRepeaterFields(array $data, array $field, array &$texts, array &$usedFields): void
    {
        $repeaterName = $field['repeater'] ?? '';
        $subfields = $field['subfields'] ?? [];
        if ($repeaterName === '' || empty($subfields)) {
            return;
        }
        $handlers = self::getAcfFieldHandlers();
        $entries = [];
        foreach (array_keys($data) as $fullKey) {
            foreach ($subfields as $subIndex => $subfield) {
                $subKey = $subfield['key'] ?? '';
                $subType = $subfield['type'] ?? 'string';
                if ($subKey === '' || !isset($handlers[$subType])) {
                    continue;
                }
                $pattern = '/^' . preg_quote($repeaterName, '/') . '_(\d+)_' . preg_quote($subKey, '/') . '$/';
                if (preg_match($pattern, $fullKey, $m)) {
                    $rowIndex = (int) $m[1];
                    $extracted = call_user_func($handlers[$subType]['extract'], $data, $fullKey);
                    if (is_string($extracted) && trim($extracted) !== '') {
                        $entries[] = [
                            'row' => $rowIndex,
                            'subindex' => $subIndex,
                            'key' => $fullKey,
                            'type' => $subType,
                            'value' => trim($extracted),
                        ];
                    }
                }
            }
        }
        usort($entries, function ($a, $b) {
            if ($a['row'] !== $b['row']) {
                return $a['row'] <=> $b['row'];
            }
            return $a['subindex'] <=> $b['subindex'];
        });
        foreach ($entries as $e) {
            $texts[] = $e['value'];
            $usedFields[] = ['key' => $e['key'], 'type' => $e['type']];
        }
    }

    /**
     * Add or update block translation configuration
     * Useful for extending the translator with new block types
     * @param string $blockName Block name
     * @param array $config Block translation configuration
     * @return void
     */
    public static function registerBlockConfig(string $blockName, array $config): void
    {
        self::$blockConfigs[$blockName] = $config;
    }

    /**
     * Get all registered block configurations
     * @return array All block configurations
     */
    public static function getBlockConfigs(): array
    {
        $configs = apply_filters('nct_block_translation_configs', self::$blockConfigs);
        if (!is_array($configs)) {
            $configs = self::$blockConfigs;
        }

        $supportedNoTextBlocks = apply_filters('nct_supported_no_text_blocks', self::$supportedNoTextBlocks);
        if (!is_array($supportedNoTextBlocks)) {
            $supportedNoTextBlocks = self::$supportedNoTextBlocks;
        }

        foreach ($supportedNoTextBlocks as $blockName) {
            $blockName = trim((string) $blockName);
            if ($blockName === '') {
                continue;
            }
            if (!isset($configs[$blockName]) || !is_array($configs[$blockName])) {
                $configs[$blockName] = ['strategy' => 'passthrough'];
            }
        }

        return $configs;
    }

}

