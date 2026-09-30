<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Accessibility helpers for third-party markup (Complianz, Akismet)
 * plus Polylang flags, decorative button icons, and footer landmark.
 *
 * SiteOne Crawler 2.3 flags form controls without aria-label/aria-labelledby as
 * critical, even when a proper <label for> already exists. These filters mirror
 * the visible/SR label onto aria-label for scanner + AT consistency.
 *
 * Decorative Nectar icon imgs keep empty alt but get aria-hidden + role=presentation
 * so empty-alt SVG icons are correctly ignored by assistive tech / a11y crawlers.
 */
class AccessibilityComponent extends Singleton {

    /**
     * AccessibilityComponent constructor.
     */
    protected function __construct() {
        if (is_admin()) {
            return;
        }

        //complianz cookie banner + manage-consent checkbox markup
        add_filter('cmplz_banner_html', [$this, 'addAriaLabelsToComplianzHtml'], 20);
        add_filter('cmplz_manage_consent_html', [$this, 'addAriaLabelsToComplianzHtml'], 20);

        //akismet honeypot inside gravity forms (and any other forms)
        add_filter('gform_get_form_filter', [$this, 'addAriaLabelToAkismetHoneypot'], 20, 2);

        //polylang flag imgs in menus + blocks that embed the switcher
        add_filter('wp_nav_menu', [$this, 'addPolylangFlagAlts'], 20, 2);
        add_filter('render_block', [$this, 'addPolylangFlagAltsToBlock'], 20, 2);

        //decorative nectar button / icon imgs: keep empty alt, hide from AT
        add_filter('render_block', [$this, 'hideDecorativeIconImages'], 20, 2);

        //footer icon-list: unwrap nested <a> inside a.nectar__link (invalid HTML → empty link name)
        add_filter('render_block', [$this, 'unwrapNestedNectarLinks'], 25, 2);

        //safety net: aria-label on a.nectar__link that still lack an accessible name
        add_filter('render_block', [$this, 'labelNamelessNectarLinks'], 30, 2);

        //video player inline-pill: drop hardcoded aria-label="Play" when visible label exists
        add_filter('render_block', [$this, 'syncVideoPlayerCenterPlayName'], 20, 2);

        //moresenz footer lives on before_footer_open (global section); wrap as contentinfo
        add_action('nectar_hook_before_footer_open', [$this, 'openFooterLandmark'], 1);
        add_action('nectar_hook_before_outer_wrap_close', [$this, 'closeFooterLandmark'], 1);
    }

    /**
     * Add aria-label on Complianz consent checkboxes from their associated labels.
     *
     * @param string $html
     * @return string
     */
    public function addAriaLabelsToComplianzHtml($html) {
        if (!is_string($html) || $html === '' || !str_contains($html, 'cmplz-consent-checkbox')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<input\b([^>]*\bclass=(["\'])[^"\']*\bcmplz-consent-checkbox\b[^"\']*\2[^>]*)>/iu',
            function (array $match) use ($html): string {
                $attrs = $match[1];

                if (preg_match('/\baria-label\s*=/iu', $attrs) || preg_match('/\baria-labelledby\s*=/iu', $attrs)) {
                    return $match[0];
                }

                //void tags often end with "/" immediately before ">"
                $selfClosing = '';
                if (preg_match('/\s*\/\s*$/u', $attrs)) {
                    $selfClosing = ' /';
                    $attrs = preg_replace('/\s*\/\s*$/u', '', $attrs) ?? $attrs;
                }

                if (!preg_match('/\bid=(["\'])([^"\']+)\1/iu', $attrs, $idMatch)) {
                    return $match[0];
                }

                $inputId = $idMatch[2];
                $labelText = $this->extractLabelTextForId($html, $inputId);

                if ($labelText === '') {
                    //fallback from data-category when label text is missing
                    if (preg_match('/\bdata-category=(["\'])cmplz_([^"\']+)\1/iu', $attrs, $catMatch)) {
                        $labelText = $this->complianzCategoryFallbackLabel($catMatch[2]);
                    }
                }

                if ($labelText === '') {
                    return $match[0];
                }

                return '<input' . rtrim($attrs) . ' aria-label="' . esc_attr($labelText) . '"' . $selfClosing . '>';
            },
            $html
        );
    }

    /**
     * Label Akismet honeypot textarea so crawlers stop flagging it as critical.
     *
     * @param string $formString
     * @param array $form
     * @return string
     */
    public function addAriaLabelToAkismetHoneypot($formString, $form) {
        if (!is_string($formString) || $formString === '' || !str_contains($formString, 'ak_hp_textarea')) {
            return $formString;
        }

        $label = esc_attr__('Do not fill in this field', Theme::TEXT_DOMAIN);

        return (string) preg_replace_callback(
            '/<textarea\b([^>]*\bname=(["\'])ak_hp_textarea\2[^>]*)>/iu',
            static function (array $match) use ($label): string {
                $attrs = $match[1];

                if (preg_match('/\baria-label\s*=/iu', $attrs) || preg_match('/\baria-labelledby\s*=/iu', $attrs)) {
                    return $match[0];
                }

                return '<textarea' . $attrs . ' aria-label="' . $label . '">';
            },
            $formString
        );
    }

    /**
     * Give Polylang flag images a language-name alt (flags sit next to visible text).
     *
     * @param string $navMenu
     * @param mixed $args
     * @return string
     */
    public function addPolylangFlagAlts($navMenu, $args = null) {
        if (!is_string($navMenu) || $navMenu === '' || !str_contains($navMenu, '/polylang/')) {
            return $navMenu;
        }

        return $this->replacePolylangFlagAlts($navMenu);
    }

    /**
     * Same flag-alt fix for block-rendered switchers (e.g. global sections).
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function addPolylangFlagAltsToBlock($blockContent, array $block) {
        if (!is_string($blockContent) || $blockContent === '' || !str_contains($blockContent, '/polylang/')) {
            return $blockContent;
        }

        return $this->replacePolylangFlagAlts($blockContent);
    }

    /**
     * Mark empty-alt nectar icon images as decorative for AT / SiteOne.
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function hideDecorativeIconImages($blockContent, array $block) {
        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        if (!str_contains($blockContent, 'nectar-component__icon__img')) {
            return $blockContent;
        }

        return $this->markDecorativeIconImgs($blockContent);
    }

    /**
     * Unwrap nested <a> tags inside a.nectar__link (footer icon-list items).
     *
     * HTML5 auto-closes the outer link at the nested <a>, leaving an empty
     * nectar__link that fails Lighthouse "Links must have discernible text".
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function unwrapNestedNectarLinks($blockContent, array $block) {
        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        if (!str_contains($blockContent, 'nectar__link') || !preg_match('/<a\b[^>]*\bnectar__link\b[^>]*>[\s\S]*?<a\b/iu', $blockContent)) {
            return $blockContent;
        }

        return $this->unwrapNestedAnchorsInNectarLinks($blockContent);
    }

    /**
     * Add aria-label on a.nectar__link that still have no accessible name.
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function labelNamelessNectarLinks($blockContent, array $block) {
        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        if (!str_contains($blockContent, 'nectar__link')) {
            return $blockContent;
        }

        return $this->injectAriaLabelsOnNamelessNectarLinks($blockContent);
    }

    /**
     * When inline-pill has a visible playButtonLabel, drop hardcoded aria-label="Play"
     * so the accessible name matches the visible text (WCAG 2.5.3 / Lighthouse).
     *
     * @param string|null $blockContent
     * @param array $block
     * @return string|null
     */
    public function syncVideoPlayerCenterPlayName($blockContent, array $block) {
        if (($block['blockName'] ?? '') !== 'nectar-blocks/video-player') {
            return $blockContent;
        }

        if (!is_string($blockContent) || $blockContent === '') {
            return $blockContent;
        }

        if (!str_contains($blockContent, 'nectar-blocks-video-player__center-play-label')) {
            return $blockContent;
        }

        return (string) preg_replace_callback(
            '/<button\b([^>]*\bnectar-blocks-video-player__center-play\b[^>]*)>(.*?)<\/button>/is',
            static function (array $match): string {
                $attrs = $match[1];
                $inner = $match[2];

                if (!preg_match(
                    '/class=(["\'])[^"\']*\bnectar-blocks-video-player__center-play-label\b[^"\']*\1[^>]*>([^<]+)/iu',
                    $inner,
                    $labelMatch
                )) {
                    return $match[0];
                }

                $visible = trim(html_entity_decode($labelMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($visible === '') {
                    return $match[0];
                }

                //remove aria-label so accessible name = visible pill text
                $attrs = preg_replace('/\s*\baria-label=(["\'])[^"\']*\1/iu', '', $attrs) ?? $attrs;

                return '<button' . $attrs . '>' . $inner . '</button>';
            },
            $blockContent
        );
    }

    /**
     * Open contentinfo landmark before the footer global section(s).
     *
     * @return void
     */
    public function openFooterLandmark(): void {
        echo '<footer role="contentinfo" class="novi-site-footer">';
    }

    /**
     * Close contentinfo landmark after the footer area (before after_footer hooks).
     *
     * @return void
     */
    public function closeFooterLandmark(): void {
        echo '</footer>';
    }

    /**
     * Extract visible/screen-reader text from label[for="id"].
     *
     * @param string $html
     * @param string $inputId
     * @return string
     */
    private function extractLabelTextForId(string $html, string $inputId): string {
        $quotedId = preg_quote($inputId, '/');

        if (!preg_match('/<label\b[^>]*\bfor=(["\'])' . $quotedId . '\1[^>]*>(.*?)<\/label>/isu', $html, $labelMatch)) {
            return '';
        }

        $text = wp_strip_all_tags($labelMatch[2]);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '') ?? '';

        return trim($text);
    }

    /**
     * Fallback category titles when label text cannot be parsed.
     *
     * @param string $categorySlug
     * @return string
     */
    private function complianzCategoryFallbackLabel(string $categorySlug): string {
        $map = [
            'functional' => __('Functional', Theme::TEXT_DOMAIN),
            'preferences' => __('Preferences', Theme::TEXT_DOMAIN),
            'statistics' => __('Statistics', Theme::TEXT_DOMAIN),
            'marketing' => __('Marketing', Theme::TEXT_DOMAIN),
        ];

        $key = strtolower($categorySlug);

        return $map[$key] ?? '';
    }

    /**
     * Replace empty alts on child-theme Polylang flag SVGs.
     *
     * @param string $html
     * @return string
     */
    private function replacePolylangFlagAlts(string $html): string {
        //fixed language names for the flag itself (not translated to the current UI locale)
        $flagAlts = [
            'nl_NL.svg' => 'Nederlands',
            'en_GB.svg' => 'English',
            'en_US.svg' => 'English',
            'de_DE.svg' => 'Deutsch',
            'es_ES.svg' => 'Español',
            'fr_FR.svg' => 'Français',
            'pt_PT.svg' => 'Português',
            'th.svg' => 'ไทย',
        ];

        return (string) preg_replace_callback(
            '/<img\b([^>]*\bsrc=(["\'])([^"\']*\/polylang\/([^"\'\/\?]+))\2[^>]*)>/iu',
            static function (array $match) use ($flagAlts): string {
                $attrs = $match[1];
                $fileName = $match[4];
                $altText = $flagAlts[$fileName] ?? '';

                if ($altText === '') {
                    return $match[0];
                }

                if (preg_match('/\balt=(["\'])\1/u', $attrs)) {
                    $attrs = preg_replace('/\balt=(["\'])\1/u', 'alt="' . esc_attr($altText) . '"', $attrs, 1) ?? $attrs;
                } elseif (!preg_match('/\balt=/iu', $attrs)) {
                    $attrs = rtrim($attrs) . ' alt="' . esc_attr($altText) . '"';
                } else {
                    return $match[0];
                }

                return '<img' . $attrs . '>';
            },
            $html
        );
    }

    /**
     * Add aria-hidden (+ role=presentation) on decorative icon imgs with empty alt.
     *
     * @param string $html
     * @return string
     */
    private function markDecorativeIconImgs(string $html): string {
        return (string) preg_replace_callback(
            '/<img\b([^>]*\bclass=(["\'])[^"\']*\bnectar-component__icon__img\b[^"\']*\2[^>]*)>/iu',
            static function (array $match): string {
                $attrs = $match[1];

                //only empty (or missing) alt — real alts stay as-is
                $hasEmptyAlt = (bool) preg_match('/\balt=(["\'])\1/u', $attrs);
                $missingAlt = !preg_match('/\balt=/iu', $attrs);

                if (!$hasEmptyAlt && !$missingAlt) {
                    return $match[0];
                }

                //void tags often end with "/" immediately before ">"
                $selfClosing = '';
                if (preg_match('/\s*\/\s*$/u', $attrs)) {
                    $selfClosing = ' /';
                    $attrs = preg_replace('/\s*\/\s*$/u', '', $attrs) ?? $attrs;
                }

                if ($missingAlt) {
                    $attrs = rtrim($attrs) . ' alt=""';
                }

                if (!preg_match('/\baria-hidden=/iu', $attrs)) {
                    $attrs = rtrim($attrs) . ' aria-hidden="true"';
                }

                if (!preg_match('/\brole=/iu', $attrs)) {
                    $attrs = rtrim($attrs) . ' role="presentation"';
                }

                return '<img' . $attrs . $selfClosing . '>';
            },
            $html
        );
    }

    /**
     * Replace nested <a>…</a> inside a.nectar__link with plain text content.
     *
     * @param string $html
     * @return string
     */
    private function unwrapNestedAnchorsInNectarLinks(string $html): string {
        //prefer the icon-list content pattern (footer mail/phone/linkedin)
        $html = (string) preg_replace(
            '/(<div\b[^>]*\bnectar-blocks-icon-list-item__content\b[^>]*>)\s*<a\b[^>]*>(.*?)<\/a>/isu',
            '$1$2',
            $html
        );

        //any remaining nested <a> inside an outer nectar__link (depth-aware)
        $offset = 0;
        $result = '';
        $length = strlen($html);

        while ($offset < $length) {
            if (!preg_match('/<a\b([^>]*\bclass=(["\'])[^"\']*\bnectar__link\b[^"\']*\2[^>]*)>/iu', $html, $openMatch, PREG_OFFSET_CAPTURE, $offset)) {
                $result .= substr($html, $offset);
                break;
            }

            $openStart = (int) $openMatch[0][1];
            $openTag = $openMatch[0][0];
            $attrs = $openMatch[1][0];
            $innerStart = $openStart + strlen($openTag);

            $result .= substr($html, $offset, $openStart - $offset);

            $depth = 1;
            $cursor = $innerStart;
            $innerEnd = null;

            while ($cursor < $length && $depth > 0) {
                if (!preg_match('/<\/?a\b[^>]*>/iu', $html, $tagMatch, PREG_OFFSET_CAPTURE, $cursor)) {
                    break;
                }

                $tag = $tagMatch[0][0];
                $tagPos = (int) $tagMatch[0][1];

                if (stripos($tag, '</') === 0) {
                    $depth--;
                    if ($depth === 0) {
                        $innerEnd = $tagPos;
                        $cursor = $tagPos + strlen($tag);
                        break;
                    }
                } else {
                    $depth++;
                }

                $cursor = $tagPos + strlen($tag);
            }

            if ($innerEnd === null) {
                //malformed — keep as-is from open tag onward
                $result .= substr($html, $openStart);
                break;
            }

            $inner = substr($html, $innerStart, $innerEnd - $innerStart);

            if (preg_match('/<a\b/iu', $inner)) {
                $inner = (string) preg_replace('/<a\b[^>]*>(.*?)<\/a>/isu', '$1', $inner);
            }

            $result .= '<a' . $attrs . '>' . $inner . '</a>';
            $offset = $cursor;
        }

        return $result !== '' ? $result : $html;
    }

    /**
     * Inject aria-label on nameless a.nectar__link anchors.
     *
     * @param string $html
     * @return string
     */
    private function injectAriaLabelsOnNamelessNectarLinks(string $html): string {
        $offset = 0;
        $result = '';
        $length = strlen($html);

        while ($offset < $length) {
            if (!preg_match('/<a\b([^>]*\bclass=(["\'])[^"\']*\bnectar__link\b[^"\']*\2[^>]*)>/iu', $html, $openMatch, PREG_OFFSET_CAPTURE, $offset)) {
                $result .= substr($html, $offset);
                break;
            }

            $openStart = (int) $openMatch[0][1];
            $openTag = $openMatch[0][0];
            $attrs = $openMatch[1][0];
            $innerStart = $openStart + strlen($openTag);

            $result .= substr($html, $offset, $openStart - $offset);

            $depth = 1;
            $cursor = $innerStart;
            $innerEnd = null;

            while ($cursor < $length && $depth > 0) {
                if (!preg_match('/<\/?a\b[^>]*>/iu', $html, $tagMatch, PREG_OFFSET_CAPTURE, $cursor)) {
                    break;
                }

                $tag = $tagMatch[0][0];
                $tagPos = (int) $tagMatch[0][1];

                if (stripos($tag, '</') === 0) {
                    $depth--;
                    if ($depth === 0) {
                        $innerEnd = $tagPos;
                        $cursor = $tagPos + strlen($tag);
                        break;
                    }
                } else {
                    $depth++;
                }

                $cursor = $tagPos + strlen($tag);
            }

            if ($innerEnd === null) {
                $result .= substr($html, $openStart);
                break;
            }

            $inner = substr($html, $innerStart, $innerEnd - $innerStart);

            if (preg_match('/\baria-label\s*=/iu', $attrs) || preg_match('/\baria-labelledby\s*=/iu', $attrs)) {
                $result .= '<a' . $attrs . '>' . $inner . '</a>';
                $offset = $cursor;
                continue;
            }

            if ($this->nectarLinkHasAccessibleName($attrs, $inner)) {
                $result .= '<a' . $attrs . '>' . $inner . '</a>';
                $offset = $cursor;
                continue;
            }

            $label = $this->deriveNectarLinkLabel($attrs, $inner);

            if ($label === '') {
                $result .= '<a' . $attrs . '>' . $inner . '</a>';
            } else {
                $result .= '<a' . rtrim($attrs) . ' aria-label="' . esc_attr($label) . '">' . $inner . '</a>';
            }

            $offset = $cursor;
        }

        return $result !== '' ? $result : $html;
    }

    /**
     * Whether a nectar__link already has a usable accessible name from content.
     *
     * @param string $attrs
     * @param string $inner
     * @return bool
     */
    private function nectarLinkHasAccessibleName(string $attrs, string $inner): bool {
        //non-empty screen-reader text
        if (preg_match('/class=(["\'])[^"\']*screen-reader-text[^"\']*\1[^>]*>([^<]+)/iu', $inner, $srMatch)) {
            if (trim(html_entity_decode($srMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
                return true;
            }
        }

        //non-empty img alt
        if (preg_match_all('/\balt=(["\'])(.*?)\1/iu', $inner, $altMatches)) {
            foreach ($altMatches[2] as $alt) {
                if (trim(html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
                    return true;
                }
            }
        }

        //visible text (strip tags / scripts)
        $text = wp_strip_all_tags($inner);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '') ?? '';

        return trim($text) !== '';
    }

    /**
     * Build an aria-label from inner text or href heuristics.
     *
     * @param string $attrs
     * @param string $inner
     * @return string
     */
    private function deriveNectarLinkLabel(string $attrs, string $inner): string {
        $text = wp_strip_all_tags($inner);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text ?? '') ?? '';
        $text = trim($text);

        if ($text !== '') {
            return $text;
        }

        if (!preg_match('/\bhref=(["\'])(.*?)\1/iu', $attrs, $hrefMatch)) {
            return '';
        }

        $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($href === '' || $href === '#') {
            return '';
        }

        if (stripos($href, 'mailto:') === 0) {
            return substr($href, 7);
        }

        if (stripos($href, 'tel:') === 0) {
            return substr($href, 4);
        }

        if (stripos($href, 'linkedin.com') !== false) {
            return 'LinkedIn';
        }

        if ($href === '#nectar-to-top') {
            return __('Back to top', Theme::TEXT_DOMAIN);
        }

        $homeUrl = untrailingslashit(home_url('/'));
        $hrefNormalized = untrailingslashit($href);

        if ($hrefNormalized === $homeUrl || $href === '/' || $href === home_url('/')) {
            $siteName = get_bloginfo('name');
            return is_string($siteName) && $siteName !== '' ? $siteName : __('Home', Theme::TEXT_DOMAIN);
        }

        //last path segment as a readable fallback
        $path = (string) (wp_parse_url($href, PHP_URL_PATH) ?? '');
        $segment = trim(basename(untrailingslashit($path)));

        if ($segment !== '' && $segment !== '/') {
            return ucwords(str_replace(['-', '_'], ' ', $segment));
        }

        return $href;
    }
}
