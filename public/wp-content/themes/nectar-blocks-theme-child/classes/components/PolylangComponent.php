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

        //sync posts-per-page ACF field across all languages (store in plain option, load/save via ACF hooks)
        add_action('acf/save_post', [$this, 'syncPostsPerPageOptionOnSave'], 20, 1);
        add_filter('acf/load_value', [$this, 'loadSyncedPostsPerPageValue'], 10, 3);

        //make Nectar Customizer strings translatable (e.g. search overlay placeholder)
        add_action('init', [$this, 'registerNectarCustomizerStrings'], 20);

        //handle front-end
        if (!is_admin()) {

            //tell ACF which (Polylang) language is active for loading correct settings
            add_filter('acf/settings/current_language', function () {
                return !defined('REST_API') ? pll_current_language('locale') : get_locale();
            });
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
     * Option page slug => field name, ACF field key and synced option name for "posts per page" (synced across all languages)
     * @var array<string, array{field: string, field_key: string, option: string}>
     */
    private static array $postsPerPageSyncedMap = [];

    /**
     * Get original options page id (without Polylang locale suffix) when it is one of our post type settings pages
     * @param string $postId
     * @return string|null
     */
    private static function getOriginalOptionIdForSyncedPages(string $postId): ?string {
        $postId = is_string($postId) ? $postId : '';
        if (isset(self::$postsPerPageSyncedMap[$postId])) {
            return $postId;
        }
        foreach (array_keys(self::$postsPerPageSyncedMap) as $slug) {
            if (strpos($postId, $slug . '_') === 0) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * On save of a post type settings options page, copy posts-per-page field to synced option so it applies to all languages.
     * Read from $_POST so we use the value just submitted; get_field() can return a cached/old value during save_post.
     * @param int|string $postId
     * @return void
     */
    public function syncPostsPerPageOptionOnSave($postId): void {
        $originalId = self::getOriginalOptionIdForSyncedPages((string)$postId);
        if ($originalId === null) {
            return;
        }
        $config = self::$postsPerPageSyncedMap[$originalId];
        $submitted = isset($_POST['acf'][$config['field_key']]) ? $_POST['acf'][$config['field_key']] : null;
        if ($submitted !== null && $submitted !== '') {
            update_option($config['option'], (int)$submitted);
        }
    }

    /**
     * Load synced posts-per-page value so the same value is shown in the form for all languages
     * @param mixed $value
     * @param int|string $postId
     * @param array $field
     * @return mixed
     */
    public function loadSyncedPostsPerPageValue($value, $postId, array $field) {
        $originalId = self::getOriginalOptionIdForSyncedPages((string)$postId);
        if ($originalId === null) {
            return $value;
        }
        $config = self::$postsPerPageSyncedMap[$originalId];
        if (($field['name'] ?? '') !== $config['field']) {
            return $value;
        }
        $synced = get_option($config['option'], null);
        if ($synced !== null && $synced !== '') {
            return (int)$synced;
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