<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;
use function get_locale;

/**
 * Class PolylangComponent
 * @package NoviOnline
 */
class PolylangComponent extends Singleton {

    /**
     * PolylangComponent component
     */
    protected function __construct() {

        //filter translatable post types
        add_filter('pll_get_post_types', [$this, 'filterPllPostTypes'], 10, 2);
        add_filter('pll_get_taxonomies', [$this, 'filterPllTaxonomies'], 10, 2);

        //sync project settings ACF fields across all languages (store in plain options, load/save via ACF hooks)
        add_action('acf/save_post', [$this, 'syncProjectSettingsOptionsOnSave'], 20, 1);
        add_filter('acf/load_value', [$this, 'loadSyncedProjectSettingsValue'], 10, 3);

        //make Nectar Customizer strings translatable (e.g. search overlay placeholder)
        add_action('init', [$this, 'registerNectarCustomizerStrings'], 20);

        //handle front-end
        if (!is_admin()) {

            //tell ACF which (Polylang) language is active for loading correct settings
            add_filter('acf/settings/current_language', function () {
                return !defined('REST_API') ? pll_current_language('locale') : get_locale();
            });

            //add x-default hreflang tag pointing to the default language URL
            add_filter('pll_rel_hreflang_attributes', [$this, 'filterRelHreflangAttributes'], 10, 1);
        }

        //handle CMS
        if (is_admin()) {

            //redirect the content manager to the first language when they visit a "All Languages" settings page
            add_filter('acf/options_page/submitbox_before_major_actions', [$this, 'disableAllLanguagesSettingsPage'], 20, 1);

            //add the name of the current Polylang language after the ACF options page title
            add_filter('acf/get_options_page', [$this, 'filterAcfOptionsPageSettings'], 10, 1);

            //add the flag of the current Polylang language before the ACF options page title
            add_action('admin_head', function () {
                $currentLangCode = pll_current_language('slug');
                $currentLangConfiguration = self::getCurrentLanguageByLocale($currentLangCode);
                if ($currentLangCode && $currentLangConfiguration && $currentLangConfiguration['flag']): ?>
                    <style>
                        .acf-settings-wrap h1 {
                            display: flex;
                            align-items: center;
                            gap: .375em;
                        }

                        .acf-settings-wrap h1:before {
                            content: "";
                            display: inline-block;
                            background-image: url("<?php echo $currentLangConfiguration['flag']; ?>");
                            background-repeat: no-repeat;
                            background-size: contain;
                            background-position: center center;
                            width: 1em;
                            height: 1em;
                        }
                    </style>
                <?php endif;
            });
        }
    }

    /**
     * Get current language by locale
     * @param string $langCode
     * @return array|bool
     */
    public static function getCurrentLanguageByLocale(string $langCode): array|bool {
        $languages = pll_the_languages(['raw' => 1]);
        foreach ($languages as $languageCode => $language) {
            if ($languageCode === $langCode) {
                return $language;
            }
        }
        return false;
    }

    /**
     * Add language in the ACF options page title
     * @param array $page
     * @return array
     */
    public static function filterAcfOptionsPageSettings(array $page): array {
        $currentLangCode = pll_current_language('slug');
        $currentLangConfiguration = self::getCurrentLanguageByLocale($currentLangCode);

        if ($currentLangCode && $currentLangConfiguration) {
            $page['page_title'] = $page['page_title'] . ' (' . $currentLangConfiguration['name'] . ')';
        }

        return $page;
    }

    /**
     * Option page slug => list of field name, ACF field key and synced option (synced across all languages)
     * @var array<string, list<array{field: string, field_key: string, option: string}>>
     */
    private static array $syncedProjectSettingsMap = [
        ProjectSettings::MENU_SLUG => [
            [
                'field' => 'project_posts_per_page',
                'field_key' => 'field_novi_project_posts_per_page',
                'option' => ProjectSettings::SYNCED_OPTION_POSTS_PER_PAGE,
            ],
            [
                'field' => 'project_excerpt_word_count',
                'field_key' => 'field_novi_project_excerpt_word_count',
                'option' => ProjectSettings::SYNCED_OPTION_EXCERPT_WORD_COUNT,
            ],
        ],
    ];

    /**
     * Get original options page id (without Polylang locale suffix) when it is one of our post type settings pages
     * @param string $postId
     * @return string|null
     */
    private static function getOriginalOptionIdForSyncedPages(string $postId): ?string {
        $postId = is_string($postId) ? $postId : '';
        if (isset(self::$syncedProjectSettingsMap[$postId])) {
            return $postId;
        }
        foreach (array_keys(self::$syncedProjectSettingsMap) as $slug) {
            if (strpos($postId, $slug . '_') === 0) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * On save of a post type settings options page, copy synced fields to plain options so they apply to all languages.
     * Read from $_POST so we use the value just submitted; get_field() can return a cached/old value during save_post.
     * @param int|string $postId
     * @return void
     */
    public function syncProjectSettingsOptionsOnSave($postId): void {
        $originalId = self::getOriginalOptionIdForSyncedPages((string)$postId);
        if ($originalId === null) {
            return;
        }
        foreach (self::$syncedProjectSettingsMap[$originalId] as $config) {
            $submitted = isset($_POST['acf'][$config['field_key']]) ? $_POST['acf'][$config['field_key']] : null;
            if ($submitted !== null && $submitted !== '') {
                update_option($config['option'], (int)$submitted);
            }
        }
    }

    /**
     * Load synced project settings values so the same value is shown in the form for all languages
     * @param mixed $value
     * @param int|string $postId
     * @param array $field
     * @return mixed
     */
    public function loadSyncedProjectSettingsValue($value, $postId, array $field) {
        $originalId = self::getOriginalOptionIdForSyncedPages((string)$postId);
        if ($originalId === null) {
            return $value;
        }
        $fieldName = $field['name'] ?? '';
        foreach (self::$syncedProjectSettingsMap[$originalId] as $config) {
            if ($fieldName !== $config['field']) {
                continue;
            }
            $synced = get_option($config['option'], null);
            if ($synced !== null && $synced !== '') {
                return (int)$synced;
            }
            return $value;
        }
        return $value;
    }

    /**
     * Make custom post types translatable in Polylang
     * @param array $postTypes
     * @param bool $isSettings
     * @return array
     */
    public static function filterPllPostTypes(array $postTypes, bool $isSettings): array {
        if (!$isSettings) {
            //TODO : add custom post types here
        }
        return $postTypes;
    }

    /**
     * Make custom taxonomies translatable in Polylang
     * @param array $taxonomies
     * @param bool $isSettings
     * @return array
     */
    public static function filterPllTaxonomies(array $taxonomies, bool $isSettings): array {
        if (!$isSettings) {
            //TODO : add custom taxonomies here
        }
        return $taxonomies;
    }

    /**
     * Redirect the content manager to the first language when they visit a "All Languages" settings page
     * @param array $page
     * @return array
     */
    public static function disableAllLanguagesSettingsPage(array $page): array {
        if (pll_current_language('name') === false) {
            $pllLanguages = pll_languages_list();
            if (count($pllLanguages) > 0) {
                $firstLanguage = $pllLanguages[0];
                if ($firstLanguage) {

                    //get current cms page path
                    if ($path = strtok($_SERVER['REQUEST_URI'], '?')) {

                        //maintain existing query params but update the lang parameter
                        $queryParams = $_GET ?? [];
                        $queryParams['lang'] = $firstLanguage;

                        //redirect to first Polylang language
                        wp_redirect($path . '?' . http_build_query($queryParams));
                    }
                }
            }
        }

        return $page;
    }

    /**
     * Prepend an x-default hreflang entry pointing to the default Polylang language URL.
     * Placed at the top of the array so it renders before all other hreflang tags.
     *
     * @param array $hreflangs Hreflang URLs keyed by language code (e.g. 'en', 'nl', ...)
     * @return array
     */
    public function filterRelHreflangAttributes(array $hreflangs): array {

        //bail when Polylang is not active
        if (!function_exists('pll_default_language')) {
            return $hreflangs;
        }

        //find the URL of the default language in the already-prepared array
        $defaultLang = pll_default_language('slug');
        $defaultUrl = null;

        if ($defaultLang && isset($hreflangs[$defaultLang])) {
            $defaultUrl = $hreflangs[$defaultLang];
        } elseif ($defaultLang) {
            //fallback for the rare case where the key is a locale (e.g. en-US) instead of slug
            foreach ($hreflangs as $key => $url) {
                if (is_string($key) && strpos($key, $defaultLang . '-') === 0) {
                    $defaultUrl = $url;
                    break;
                }
            }
        }

        //nothing to do when we cannot resolve a URL for the default language
        if (!$defaultUrl) {
            return $hreflangs;
        }

        //prepend x-default; left-hand keys win in array union so any pre-existing x-default is overridden,
        //and iteration order (which determines print order) starts with x-default
        return ['x-default' => $defaultUrl] + $hreflangs;
    }

    /**
     * Register Nectar theme Customizer option strings with Polylang so they appear in
     * Languages > String translations and are translated on the frontend.
     *
     * Nectar stores Customizer values in theme mods (option key: theme_mods_<stylesheet>).
     * Add more keys to $customizerKeys to translate other Customizer text fields.
     *
     * @return void
     */
    public function registerNectarCustomizerStrings(): void {
        if (!function_exists('pll_current_language') || !class_exists('PLL_Translate_Option')) {
            return;
        }

        $optionName = 'theme_mods_' . get_option('stylesheet');
        $customizerKeys = [
            'header-search-ph-text' => 1,
            'secondary-header-text' => 1,
            'secondary-header-link' => 1,
            'footer-copyright-text' => 1,
            'cta-text' => 1,
            'cta-btn' => 1,
            'cta-btn-link' => 1,
            'carousel-title' => 1,
            'carousel-link' => 1,
            'portfolio-sortable-text' => 1,
            'main-portfolio-link' => 1,
            'header-text-widget' => 1,
            'header-slide-out-widget-area-bottom-text' => 1,
            'recent-posts-title' => 1,
            'recent-posts-link' => 1,
        ];

        new \PLL_Translate_Option($optionName, $customizerKeys, ['context' => 'Nectar Theme']);
    }
}