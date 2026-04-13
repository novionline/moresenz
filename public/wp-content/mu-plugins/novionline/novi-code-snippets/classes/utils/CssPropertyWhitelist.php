<?php

namespace NoviOnline\CodeSnippets;

/**
 * Whitelist of valid standard CSS property names for snippet validation.
 * Custom properties (--*) and vendor-prefixed (-moz-, -webkit-, etc.) are allowed separately.
 * @package NoviOnline\CodeSnippets
 */
class CssPropertyWhitelist
{
    /** @var array<string>|null */
    private static $whitelist = null;

    /**
     * Known standard CSS property names (W3C / MDN). Used to reject typos like "backgroundrrr".
     * @return array<string>
     */
    public static function getWhitelist(): array
    {
        if (self::$whitelist !== null) {
            return self::$whitelist;
        }
        self::$whitelist = [
            'accent-color', 'align-content', 'align-items', 'align-self', 'alignment-baseline', 'all', 'anchor-name',
            'anchor-scope', 'animation', 'animation-composition', 'animation-delay', 'animation-direction',
            'animation-duration', 'animation-fill-mode', 'animation-iteration-count', 'animation-name',
            'animation-play-state', 'animation-range', 'animation-range-end', 'animation-range-start',
            'animation-timeline', 'animation-timing-function', 'animation-trigger', 'appearance', 'aspect-ratio',
            'azimuth', 'backdrop-filter', 'backface-visibility', 'background', 'background-attachment', 'background-blend-mode',
            'background-clip', 'background-color', 'background-image', 'background-origin', 'background-position',
            'background-position-block', 'background-position-inline', 'background-position-x', 'background-position-y',
            'background-repeat', 'background-repeat-block', 'background-repeat-inline', 'background-repeat-x',
            'background-repeat-y', 'background-size', 'baseline-shift', 'baseline-source', 'block-ellipsis',
            'block-size', 'block-step', 'block-step-align', 'block-step-insert', 'block-step-round', 'block-step-size',
            'border', 'border-block', 'border-block-clip', 'border-block-color', 'border-block-end',
            'border-block-end-clip', 'border-block-end-color', 'border-block-end-radius', 'border-block-end-style',
            'border-block-end-width', 'border-block-start', 'border-block-start-clip', 'border-block-start-color',
            'border-block-start-radius', 'border-block-start-style', 'border-block-start-width', 'border-block-style',
            'border-block-width', 'border-bottom', 'border-bottom-clip', 'border-bottom-color',
            'border-bottom-left-radius', 'border-bottom-radius', 'border-bottom-right-radius', 'border-bottom-style',
            'border-bottom-width', 'border-boundary', 'border-clip', 'border-collapse', 'border-color',
            'border-end-end-radius', 'border-end-start-radius', 'border-image', 'border-image-outset',
            'border-image-repeat', 'border-image-slice', 'border-image-source', 'border-image-width',
            'border-inline', 'border-inline-clip', 'border-inline-color', 'border-inline-end',
            'border-inline-end-clip', 'border-inline-end-color', 'border-inline-end-radius', 'border-inline-end-style',
            'border-inline-end-width', 'border-inline-start', 'border-inline-start-clip', 'border-inline-start-color',
            'border-inline-start-radius', 'border-inline-start-style', 'border-inline-start-width',
            'border-inline-style', 'border-inline-width', 'border-left', 'border-left-clip', 'border-left-color',
            'border-left-radius', 'border-left-style', 'border-left-width', 'border-limit', 'border-radius',
            'border-right', 'border-right-clip', 'border-right-color', 'border-right-radius', 'border-right-style',
            'border-right-width', 'border-shape', 'border-spacing', 'border-start-end-radius',
            'border-start-start-radius', 'border-style', 'border-top', 'border-top-clip', 'border-top-color',
            'border-top-left-radius', 'border-top-radius', 'border-top-right-radius', 'border-top-style',
            'border-top-width', 'border-width', 'bottom', 'box-decoration-break', 'box-shadow', 'box-sizing',
            'break-after', 'break-before', 'break-inside', 'caption-side', 'caret-color', 'clear', 'clip', 'clip-path',
            'clip-rule', 'color', 'color-adjust', 'color-interpolation-filters', 'color-scheme', 'column-count',
            'column-fill', 'column-gap', 'column-rule', 'column-rule-color', 'column-rule-style',
            'column-rule-width', 'column-span', 'column-width', 'columns', 'contain', 'contain-intrinsic-block-size',
            'contain-intrinsic-height', 'contain-intrinsic-inline-size', 'contain-intrinsic-size',
            'contain-intrinsic-width', 'content', 'content-visibility', 'counter-increment', 'counter-reset',
            'counter-set', 'cursor', 'direction', 'display', 'dominant-baseline', 'empty-cells', 'fill', 'fill-opacity',
            'fill-rule', 'filter', 'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow', 'flex-shrink',
            'flex-wrap', 'float', 'flood-color', 'flood-opacity', 'font', 'font-family', 'font-feature-settings',
            'font-kerning', 'font-language-override', 'font-optical-sizing', 'font-size', 'font-size-adjust',
            'font-stretch', 'font-style', 'font-synthesis', 'font-variant', 'font-variant-alternates',
            'font-variant-caps', 'font-variant-east-asian', 'font-variant-emoji', 'font-variant-ligatures',
            'font-variant-numeric', 'font-variant-position', 'font-variation-settings', 'font-weight',
            'forced-color-adjust', 'gap', 'grid', 'grid-area', 'grid-auto-columns', 'grid-auto-flow',
            'grid-auto-rows', 'grid-column', 'grid-column-end', 'grid-column-start', 'grid-row', 'grid-row-end',
            'grid-row-start', 'grid-template', 'grid-template-areas', 'grid-template-columns', 'grid-template-rows',
            'hanging-punctuation', 'height', 'hyphenate-character', 'hyphenate-limit-chars', 'hyphens',
            'image-orientation', 'image-rendering', 'image-resolution', 'initial-letter', 'initial-letter-align',
            'inline-size', 'inset', 'inset-block', 'inset-block-end', 'inset-block-start', 'inset-inline',
            'inset-inline-end', 'inset-inline-start', 'isolation', 'justify-content', 'justify-items',
            'justify-self', 'left', 'letter-spacing', 'lighting-color', 'line-break', 'line-clamp', 'line-height',
            'line-height-step', 'list-style', 'list-style-image', 'list-style-position', 'list-style-type',
            'margin', 'margin-block', 'margin-block-end', 'margin-block-start', 'margin-bottom', 'margin-inline',
            'margin-inline-end', 'margin-inline-start', 'margin-left', 'margin-right', 'margin-top',
            'margin-trim', 'mask', 'mask-border', 'mask-border-outset', 'mask-border-repeat', 'mask-border-slice',
            'mask-border-source', 'mask-border-width', 'mask-clip', 'mask-composite', 'mask-image', 'mask-mode',
            'mask-origin', 'mask-position', 'mask-repeat', 'mask-size', 'mask-type', 'max-block-size',
            'max-height', 'max-inline-size', 'max-width', 'min-block-size', 'min-height', 'min-inline-size',
            'min-width', 'mix-blend-mode', 'object-fit', 'object-position', 'offset', 'offset-anchor',
            'offset-distance', 'offset-path', 'offset-position', 'offset-rotate', 'opacity', 'order', 'orphans',
            'outline', 'outline-color', 'outline-offset', 'outline-style', 'outline-width', 'overflow',
            'overflow-anchor', 'overflow-clip-margin', 'overflow-wrap', 'overflow-x', 'overflow-y',
            'overscroll-behavior', 'overscroll-behavior-block', 'overscroll-behavior-inline',
            'overscroll-behavior-x', 'overscroll-behavior-y', 'padding', 'padding-block', 'padding-block-end',
            'padding-block-start', 'padding-bottom', 'padding-inline', 'padding-inline-end', 'padding-inline-start',
            'padding-left', 'padding-right', 'padding-top', 'page', 'page-break-after', 'page-break-before',
            'page-break-inside', 'paint-order', 'perspective', 'perspective-origin', 'place-content',
            'place-items', 'place-self', 'pointer-events', 'position', 'print-color-adjust', 'quotes', 'resize',
            'right', 'rotate', 'row-gap', 'ruby-align', 'ruby-position', 'scale', 'scroll-behavior',
            'scroll-margin', 'scroll-margin-block', 'scroll-margin-block-end', 'scroll-margin-block-start',
            'scroll-margin-bottom', 'scroll-margin-inline', 'scroll-margin-inline-end', 'scroll-margin-inline-start',
            'scroll-margin-left', 'scroll-margin-right', 'scroll-margin-top', 'scroll-padding',
            'scroll-padding-block', 'scroll-padding-block-end', 'scroll-padding-block-start', 'scroll-padding-bottom',
            'scroll-padding-inline', 'scroll-padding-inline-end', 'scroll-padding-inline-start',
            'scroll-padding-left', 'scroll-padding-right', 'scroll-padding-top', 'scroll-snap-align',
            'scroll-snap-stop', 'scroll-snap-type', 'scrollbar-color', 'scrollbar-gutter', 'scrollbar-width',
            'shape-image-threshold', 'shape-margin', 'shape-outside', 'stop-color', 'stop-opacity', 'stroke',
            'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
            'stroke-opacity', 'stroke-width', 'tab-size', 'table-layout', 'text-align', 'text-align-last',
            'text-combine-upright', 'text-decoration', 'text-decoration-color', 'text-decoration-line',
            'text-decoration-skip-ink', 'text-decoration-style', 'text-decoration-thickness', 'text-emphasis',
            'text-emphasis-color', 'text-emphasis-position', 'text-emphasis-style', 'text-indent',
            'text-justify', 'text-orientation', 'text-overflow', 'text-rendering', 'text-shadow',
            'text-transform', 'text-underline-offset', 'text-underline-position', 'top', 'touch-action',
            'transform', 'transform-box', 'transform-origin', 'transform-style', 'transition', 'transition-delay',
            'transition-duration', 'transition-property', 'transition-timing-function', 'translate', 'unicode-bidi',
            'user-select', 'vertical-align', 'visibility', 'white-space', 'widows', 'width', 'will-change',
            'word-break', 'word-spacing', 'writing-mode', 'z-index', 'zoom',
        ];
        return self::$whitelist;
    }

    /**
     * Whether the property name is valid: custom (--*), vendor-prefixed (-x-*), or in whitelist.
     * @param string $propertyName
     * @return bool
     */
    public static function isValidPropertyName(string $propertyName): bool
    {
        $propertyName = trim($propertyName);
        if ($propertyName === '') {
            return false;
        }
        //custom properties
        if (strpos($propertyName, '--') === 0) {
            return true;
        }
        //vendor-prefixed: -moz-, -webkit-, -ms-, -o-, etc.
        if (preg_match('/^-[a-z]+-/i', $propertyName)) {
            return true;
        }
        $whitelist = self::getWhitelist();
        return in_array($propertyName, $whitelist, true);
    }
}
