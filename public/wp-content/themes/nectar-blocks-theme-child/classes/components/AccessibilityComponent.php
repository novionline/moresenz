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
}
