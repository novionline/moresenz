<?php

namespace NoviOnline\ContentTranslator;

use NoviOnline\Core\Singleton;
use NoviOnline\Core\Enqueue;
use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\DeepLTranslationCache;
use NoviOnline\ContentTranslator\Core\PostDuplicator;
use NoviOnline\ContentTranslator\Core\StringOverrules;
use NoviOnline\ContentTranslator\NoviContentTranslator;
use function pll_languages_list;
use function pll_the_languages;
use function pll_get_post_language;
use function pll_get_post_translations;
use function pll_is_translated_post_type;

/**
 * Class ContentTranslatorComponent
 * @package NoviOnline\ContentTranslator
 */
class ContentTranslatorComponent extends Singleton
{
    private const SETTINGS_OPTION_KEY = 'novi_content_translator_settings';
    public const NB_CSS_REGEN_META_KEY = '_nct_needs_nb_css_regen';

    /**
     * Safely resolve translated post types from Polylang internals.
     * Falls back to pll_is_translated_post_type checks when internals are unavailable.
     * @return array<int, string>
     */
    private function getTranslatedPostTypesSafe(): array
    {
        if (function_exists('PLL')) {
            $pll = PLL();
            if (
                is_object($pll)
                && isset($pll->model)
                && is_object($pll->model)
                && isset($pll->model->post_types)
                && is_object($pll->model->post_types)
                && method_exists($pll->model->post_types, 'get_translated')
            ) {
                $types = (array) $pll->model->post_types->get_translated(true);
                return array_values(array_filter($types, static fn ($type) => is_string($type) && $type !== ''));
            }
        }

        if (!function_exists('pll_is_translated_post_type')) {
            return [];
        }

        $translatedPostTypes = [];
        $publicPostTypes = get_post_types(['public' => true], 'names');
        foreach ($publicPostTypes as $type) {
            if (pll_is_translated_post_type($type)) {
                $translatedPostTypes[] = $type;
            }
        }

        return $translatedPostTypes;
    }

    /**
     * Resolve webpack asset URL with a safe fallback if Enqueue helper is unavailable.
     * @param string $assetKey
     * @return string
     */
    private function getWebpackAssetUrlSafe(string $assetKey): string
    {
        if (class_exists(Enqueue::class) && method_exists(Enqueue::class, 'getWebpackAssetUrlByKey')) {
            $url = Enqueue::getWebpackAssetUrlByKey(NCT_MANIFEST_PATH, $assetKey);
            return is_string($url) ? $url : '';
        }

        return '';
    }

    /**
     * Debug logging helper (only when WP_DEBUG is enabled)
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    private function debugLog(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $suffix = $context !== [] ? ' ' . wp_json_encode($context) : '';
        error_log('Novi content translator: ' . $message . $suffix);
    }

    /**
     * Get DeepL key status for UI.
     * Includes validation/usage when key exists (cached/throttled server-side).
     *
     * @return array{valid: bool, identifier: string, usage: array<string, int|string|null>, last_checked: int}
     */
    private function getDeeplStatus(): array
    {
        return DeepLTranslator::getDeepLKeyStatus();
    }

    /**
     * ContentTranslatorComponent constructor.
     */
    protected function __construct()
    {
        add_action('init', [$this, 'registerPostMeta']);

        // Ensure post type archive links respect Polylang translated slugs (full-path archive mappings),
        // e.g. vacancy archive: /vacancies/ -> /nl/over-ons/vacatures/.
        add_filter('post_type_archive_link', [$this, 'filterPostTypeArchiveLink'], 20, 2);

        // Add metabox hook - works for both classic and Gutenberg editors
        add_action('add_meta_boxes', [$this, 'addMetabox'], 10, 2);
        
        // AJAX handlers (only in admin)
        if (is_admin()) {
            add_action('wp_ajax_novi_create_translations', [$this, 'handleAjaxRequest']);
            add_action('wp_ajax_novi_check_existing_translations', [$this, 'checkExistingTranslations']);
            add_action('wp_ajax_novi_count_translation_strings', [$this, 'countTranslationStrings']);
            add_action('wp_ajax_novi_get_languages', [$this, 'getLanguages']);
            add_action('wp_ajax_novi_get_settings', [$this, 'getSettingsAjax']);
            add_action('wp_ajax_novi_save_settings', [$this, 'saveSettingsAjax']);

            // Classic metabox + admin JS/CSS.
            add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

            // React editor sidebar (post editor + Site Editor), mirroring Polylang's Editors module:
            // load via admin_enqueue_scripts and gate by current screen.
            add_action('admin_enqueue_scripts', [$this, 'enqueueBlockEditorAssets']);
        }
    }

    public function filterPostTypeArchiveLink(string $link, string $postType): string
    {
        $postType = trim((string) $postType);
        if ($postType === '' || $link === '' || !function_exists('PLL') || !function_exists('pll_home_url')) {
            return $link;
        }

        $lang = '';
        if (function_exists('pll_current_language')) {
            $lang = (string) pll_current_language('slug');
        }
        if ($lang === '' && function_exists('pll_default_language')) {
            $lang = (string) pll_default_language('slug');
        }
        $lang = trim($lang);
        if ($lang === '') {
            return $link;
        }

        try {
            $pll = PLL();
            $model = null;
            if (is_object($pll) && isset($pll->translate_slugs) && is_object($pll->translate_slugs) && isset($pll->translate_slugs->slugs_model)) {
                $model = $pll->translate_slugs->slugs_model;
            }
            if (!is_object($model) || !isset($model->translated_slugs) || !is_array($model->translated_slugs)) {
                return $link;
            }

            $keys = [
                'archive_' . $postType,
                'slug_archive_' . $postType,
                'archive_' . $postType . 's',
                'slug_archive_' . $postType . 's',
            ];
            foreach ($keys as $k) {
                if (!isset($model->translated_slugs[$k]) || !is_array($model->translated_slugs[$k])) {
                    continue;
                }
                $entry = (array) $model->translated_slugs[$k];
                $trs = isset($entry['translations']) && is_array($entry['translations']) ? $entry['translations'] : [];
                $to = isset($trs[$lang]) ? trim((string) $trs[$lang], '/') : '';
                if ($to === '') {
                    continue;
                }
                $base = rtrim((string) pll_home_url($lang), '/') . '/';
                return $base . $to . '/';
            }
        } catch (\Throwable) {
            return $link;
        }

        return $link;
    }

    public function registerPostMeta(): void
    {
        // used by editor JS to auto-regenerate nectar-blocks dynamic css meta after translation
        $postTypes = ['wp_block', 'nectar_sections'];
        foreach ($postTypes as $postType) {
            register_post_meta($postType, self::NB_CSS_REGEN_META_KEY, [
                'show_in_rest' => true,
                'single' => true,
                'type' => 'boolean',
                'default' => false,
                'auth_callback' => static function (): bool {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }

    /**
     * Ensure the current user is logged in for an AJAX request. Call after check_ajax_referer().
     * Sends JSON error and exits if not logged in.
     * @return void
     */
    private function requireLoggedInForAjax(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to perform this action.', NoviContentTranslator::TEXT_DOMAIN)]);
        }
    }

    /**
     * Ensure the current user has a capability for an AJAX request. Sends JSON error and exits if not.
     * @param string $capability Capability to check (e.g. 'manage_options', 'edit_post').
     * @param int|string|null $object Optional object ID for capabilities that use it (e.g. post ID for edit_post).
     * @return void
     */
    private function requireCapabilityForAjax(string $capability, $object = null): void
    {
        if ($object !== null) {
            if (!current_user_can($capability, $object)) {
                wp_send_json_error(['message' => __('You do not have permission to perform this action.', NoviContentTranslator::TEXT_DOMAIN)]);
            }
            return;
        }
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', NoviContentTranslator::TEXT_DOMAIN)]);
        }
    }

    /**
     * Decode HTML entities for strings sent in JSON (so "&amp;" displays as "&" in the UI).
     * @param string $str
     * @return string
     */
    private function decodeForJson(string $str): string
    {
        return html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Parse target_languages request param (JSON string, CSV string, or array).
     *
     * @return array<int, string>
     */
    private function parseTargetLanguagesFromRequest(): array
    {
        $targetLanguages = [];
        if (!isset($_POST['target_languages'])) {
            return $targetLanguages;
        }

        $raw = $_POST['target_languages'];
        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
            if (is_array($decoded)) {
                $targetLanguages = array_map('strval', $decoded);
            } else {
                $trimmed = trim($raw);
                if ($trimmed !== '') {
                    if ($trimmed[0] === '[' && substr($trimmed, -1) === ']') {
                        $trimmed = trim($trimmed, "[]\"' ");
                    }
                    $parts = array_filter(array_map('trim', explode(',', $trimmed)));
                    if (!empty($parts)) {
                        $targetLanguages = array_map('strval', $parts);
                    }
                }
            }
        } elseif (is_array($raw)) {
            $targetLanguages = array_map('strval', $raw);
        }

        return array_values(array_unique(array_filter($targetLanguages, static function ($v) {
            return $v !== '';
        })));
    }

    /**
     * Build missing reusable-block translation payload by language for preflight UX.
     *
     * @param \WP_Post $post
     * @param array<int, string> $targetLanguages
     * @return array{has_missing: bool, total_missing: int, by_language: array<string, array<int, array<string, mixed>>>}
     */
    private function getMissingReusableBlocksByLanguage(\WP_Post $post, array $targetLanguages): array
    {
        $empty = [
            'has_missing' => false,
            'total_missing' => 0,
            'by_language' => [],
        ];

        if (!function_exists('pll_get_post')) {
            return $empty;
        }

        $sourceRefs = BlockContentTranslator::getReusableBlockRefs((string) ($post->post_content ?? ''));
        $this->debugLog('Reusable preflight: collected source refs', [
            'post_id' => (int) $post->ID,
            'target_langs' => $targetLanguages,
            'source_refs' => $sourceRefs,
        ]);
        if (empty($sourceRefs)) {
            return $empty;
        }

        $byLanguage = [];
        foreach ($targetLanguages as $langSlug) {
            $missing = [];
            foreach ($sourceRefs as $sourceRef) {
                $translatedRef = (int) pll_get_post((int) $sourceRef, (string) $langSlug);
                if ($translatedRef > 0) {
                    continue;
                }

                $sourceReusable = get_post((int) $sourceRef);
                if (!$sourceReusable || (string) $sourceReusable->post_type !== 'wp_block') {
                    continue;
                }

                $missing[] = [
                    'source_ref' => (int) $sourceRef,
                    'source_title' => $this->decodeForJson((string) ($sourceReusable->post_title ?? '')),
                    'edit_link' => get_edit_post_link((int) $sourceRef, ''),
                ];
            }

            if (!empty($missing)) {
                $byLanguage[(string) $langSlug] = $missing;
            }

            $this->debugLog('Reusable preflight: language summary', [
                'post_id' => (int) $post->ID,
                'lang' => (string) $langSlug,
                'missing_count' => count($missing),
                'missing_refs' => array_map(static function ($row) {
                    return (int) ($row['source_ref'] ?? 0);
                }, $missing),
            ]);
        }

        $total = 0;
        foreach ($byLanguage as $items) {
            $total += count($items);
        }

        return [
            'has_missing' => !empty($byLanguage),
            'total_missing' => $total,
            'by_language' => $byLanguage,
        ];
    }

    /**
     * Ensure DeepL key is configured and valid before doing translation work.
     * @return void
     */
    private function requireValidDeeplKeyForAjax(bool $strict = false): void
    {
        $deeplStatus = DeepLTranslator::getDeepLKeyStatus($strict);

        if (empty($deeplStatus['valid'])) {
            wp_send_json_error([
                'message' => __('DeepL API key is missing or invalid. Open Settings to set a valid key.', NoviContentTranslator::TEXT_DOMAIN),
                'deepl' => $deeplStatus,
            ]);
        }

        if (!empty($deeplStatus['quota_exhausted'])) {
            wp_send_json_error([
                'message' => __('DeepL quota is exhausted. Please wait for quota refresh or increase your quota.', NoviContentTranslator::TEXT_DOMAIN),
                'deepl' => $deeplStatus,
            ]);
        }
    }

    /**
     * Get Novi content translator settings with sane defaults applied.
     * @return array
     */
    private function getSettings(): array
    {
        $raw = function_exists('get_option')
            ? (array) \get_option(self::SETTINGS_OPTION_KEY, [])
            : [];

        $formality = 'default';
        if (isset($raw['formality']) && in_array($raw['formality'], ['default', 'more', 'less'], true)) {
            $formality = $raw['formality'];
        }

        $dontTranslateWords = [];
        if (isset($raw['dont_translate_words']) && is_array($raw['dont_translate_words'])) {
            $dontTranslateWords = StringOverrules::normalizeStoredRules($raw['dont_translate_words']);
        } elseif (isset($raw['string_overrules']) && is_array($raw['string_overrules'])) {
            //legacy migrate: normalize into dont_translate_words shape (targets kept when present)
            $dontTranslateWords = StringOverrules::normalizeStoredRules($raw['string_overrules']);
        }

        //hardcoded defaults: sentence splitting, preserve formatting, and translate everything
        return [
            'formality' => $formality,
            'split_sentences' => 'nonewlines',
            'preserve_formatting' => true,
            'translate_titles' => true,
            'translate_content' => true,
            'translate_meta' => true,
            'dont_translate_words' => $dontTranslateWords,
        ];
    }

    /**
     * Polylang languages for don’t-translate words UI (slug, name, flag).
     *
     * @return array<int, array{slug:string, name:string, flag:string}>
     */
    private function getDontTranslateLanguages(): array
    {
        if (!function_exists('pll_the_languages')) {
            return [];
        }
        $languagesData = pll_the_languages(['raw' => 1]);
        if (!is_array($languagesData) || $languagesData === []) {
            return [];
        }

        $out = [];
        foreach ($languagesData as $langSlug => $langData) {
            $slug = strtolower(trim((string) $langSlug));
            if ($slug === '') {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'name' => $this->decodeForJson((string) ($langData['name'] ?? $slug)),
                'flag' => isset($langData['flag']) ? (string) $langData['flag'] : '',
            ];
        }
        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function getDontTranslateLanguageSlugs(): array
    {
        return array_values(array_map(static function (array $lang): string {
            return (string) ($lang['slug'] ?? '');
        }, $this->getDontTranslateLanguages()));
    }

    /**
     * AJAX: Get Novi content translator settings for the React sidebar Settings tab.
     * @return void
     */
    public function getSettingsAjax(): void
    {
        check_ajax_referer('novi_content_translator_ajax', 'nonce');
        $this->requireLoggedInForAjax();
        $this->requireCapabilityForAjax('manage_options');

        wp_send_json_success([
            'settings' => $this->getSettings(),
            'deepl' => $this->getDeeplStatus(),
            'languages' => $this->getDontTranslateLanguages(),
        ]);
    }

    /**
     * AJAX: Save Novi content translator settings from the React sidebar Settings tab.
     * @return void
     */
    public function saveSettingsAjax(): void
    {
        check_ajax_referer('novi_content_translator_ajax', 'nonce');
        $this->requireLoggedInForAjax();
        $this->requireCapabilityForAjax('manage_options');

        $raw = isset($_POST['settings']) ? wp_unslash((string) $_POST['settings']) : '';
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            wp_send_json_error(['message' => __('Invalid settings payload.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $formality = null;
        if (isset($decoded['formality']) && in_array($decoded['formality'], ['default', 'more', 'less'], true)) {
            $formality = $decoded['formality'];
        }

        $deeplApiKey = null;
        if (array_key_exists('deepl_api_key', $decoded)) {
            $deeplApiKey = trim((string) $decoded['deepl_api_key']);
        }

        $hasDontTranslate = array_key_exists('dont_translate_words', $decoded);
        $validatedWords = null;
        if ($hasDontTranslate) {
            $validated = StringOverrules::validateAndNormalize(
                $decoded['dont_translate_words'],
                $this->getDontTranslateLanguageSlugs()
            );
            if (!$validated['ok']) {
                wp_send_json_error([
                    'message' => $validated['message'] ?: __('Invalid don’t-translate words.', NoviContentTranslator::TEXT_DOMAIN),
                ]);
            }
            $validatedWords = $validated['rules'];
        }

        $current = (array) \get_option(self::SETTINGS_OPTION_KEY, []);
        $currentFormality = isset($current['formality']) && in_array($current['formality'], ['default', 'more', 'less'], true)
            ? $current['formality']
            : 'default';
        $currentEncryptedKey = isset($current['deepl_api_key']) ? (string) $current['deepl_api_key'] : '';
        $currentDeeplApiKey = $currentEncryptedKey !== ''
            ? DeepLTranslator::decryptApiKeyFromStorage($currentEncryptedKey)
            : '';
        $currentWords = [];
        if (isset($current['dont_translate_words']) && is_array($current['dont_translate_words'])) {
            $currentWords = StringOverrules::normalizeStoredRules($current['dont_translate_words']);
        } elseif (isset($current['string_overrules']) && is_array($current['string_overrules'])) {
            $currentWords = StringOverrules::normalizeStoredRules($current['string_overrules']);
        }

        $shouldSaveDeepl = $deeplApiKey !== null && $currentDeeplApiKey !== $deeplApiKey;
        $shouldSaveFormality = $formality !== null && $currentFormality !== $formality;
        $shouldSaveWords = $hasDontTranslate && $validatedWords !== null
            && wp_json_encode($validatedWords) !== wp_json_encode($currentWords);

        if (!$shouldSaveFormality && !$shouldSaveDeepl && !$shouldSaveWords) {
            // no change: treat as success so the UI does not show an error
            wp_send_json_success([
                'settings' => $this->getSettings(),
                'deepl' => $this->getDeeplStatus(),
                'languages' => $this->getDontTranslateLanguages(),
                'message' => __('Settings saved successfully.', NoviContentTranslator::TEXT_DOMAIN),
            ]);
        }

        $toSave = [];
        if ($shouldSaveFormality && $formality !== null) {
            $toSave['formality'] = $formality;
        }
        if ($deeplApiKey !== null) {
            $toSave['deepl_api_key'] = DeepLTranslator::encryptApiKeyForStorage($deeplApiKey);
        }
        if ($shouldSaveWords) {
            $toSave['dont_translate_words'] = $validatedWords;
        }
        // preserve any future settings keys stored in the same option
        $toSave = array_merge($current, $toSave);
        // fully remove legacy plain-text key storage if present
        unset($toSave['deepl_api_key_plain']);
        if ($shouldSaveWords) {
            //drop legacy overrules key once migrated
            unset($toSave['string_overrules']);
        }
        $updated = \update_option(self::SETTINGS_OPTION_KEY, $toSave);
        if (false === $updated) {
            wp_send_json_error(['message' => __('Unable to save. Please try again or contact support if the problem persists.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        if ($shouldSaveWords) {
            $needles = StringOverrules::collectNeedlesFromRules($validatedWords);
            $needles = array_values(array_unique(array_merge(
                $needles,
                StringOverrules::collectNeedlesFromRules($currentWords)
            )));
            DeepLTranslationCache::deleteTransientsContaining($needles);
        }

        $deeplStatus = $this->getDeeplStatus();

        wp_send_json_success([
            'settings' => $this->getSettings(),
            'deepl' => $deeplStatus,
            'languages' => $this->getDontTranslateLanguages(),
            'message' => __('Settings saved successfully.', NoviContentTranslator::TEXT_DOMAIN),
        ]);
    }

    /**
     * Add metabox to all post types managed by Polylang
     * @param string $postType
     * @param \WP_Post|null $post
     * @return void
     */
    public function addMetabox(string $postType = '', ?\WP_Post $post = null): void
    {
        // Bail out early if Polylang is not available
        if (!function_exists('pll_is_translated_post_type')) {
            return;
        }

        // Let Polylang tell us which post types are translatable.
        // This includes core types (post, page, wp_block) plus any configured CPTs and filters.
        $translatedPostTypes = $this->getTranslatedPostTypesSafe();

        if (empty($translatedPostTypes)) {
            return;
        }

        foreach ($translatedPostTypes as $type) {
            // For post types that use the block editor, we render the Novi Content Translator
            // as a dedicated React sidebar panel instead of a classic PHP metabox.
            if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type($type)) {
                continue;
            }

            add_meta_box(
                'novi-content-translator',
                __('Novi Content Translator', NoviContentTranslator::TEXT_DOMAIN),
                [$this, 'renderMetabox'],
                $type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render metabox
     * @param \WP_Post $post
     * @return void
     */
    public function renderMetabox(\WP_Post $post): void
    {
        if (!$post || !$post->ID) {
            return;
        }

        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        
        if (!function_exists('pll_languages_list') || !function_exists('pll_the_languages')) {
            echo '<p>' . esc_html__('Polylang is not available. Please activate Polylang Pro to use the Novi Content Translator.', NoviContentTranslator::TEXT_DOMAIN) . '</p>';
            return;
        }

        // Get all languages with details (slug, name, flag, current_lang, etc.)
        $languagesData = pll_the_languages(['raw' => 1]);
        if (empty($languagesData) || !is_array($languagesData)) {
            echo '<p>' . esc_html__('No languages available for translation.', NoviContentTranslator::TEXT_DOMAIN) . '</p>';
            return;
        }

        // Determine current post language
        $currentLangSlug = function_exists('pll_get_post_language')
            ? (string) pll_get_post_language($post->ID, 'slug')
            : '';

        // Get existing translations map: lang slug => post ID
        $existingTranslationsMap = function_exists('pll_get_post_translations')
            ? (array) pll_get_post_translations($post->ID)
            : [];

        // Build list of target languages (all languages except the current one)
        $targetLanguages = [];
        foreach ($languagesData as $langSlug => $langData) {
            if ($langSlug === $currentLangSlug) {
                continue;
            }
            $targetLanguages[$langSlug] = $langData;
        }

        if (empty($targetLanguages)) {
            echo '<p>' . esc_html__('No other languages available for translation.', NoviContentTranslator::TEXT_DOMAIN) . '</p>';
            return;
        }

        wp_nonce_field('novi_content_translator', 'novi_content_translator_nonce');
        ?>
        <div id="novi-content-translator-metabox">
            <fieldset class="novi-translator-fieldset">
                <legend class="screen-reader-text">
                    <?php echo esc_html__('Novi Content Translator target languages', NoviContentTranslator::TEXT_DOMAIN); ?>
                </legend>

                <p class="novi-metabox-summary">
                    <?php
                    $sourceName = $languagesData[$currentLangSlug]['name'] ?? strtoupper($currentLangSlug);
                    $sourceFlag = $languagesData[$currentLangSlug]['flag'] ?? '';
                    ?>
                    <span class="novi-source-label">
                        <small class="novi-source-prefix">
                            <?php echo esc_html__('Current language:', NoviContentTranslator::TEXT_DOMAIN); ?>
                        </small>
                        <?php if (!empty($sourceFlag)): ?>
                            <span class="novi-site-flag">
                                <img src="<?php echo esc_url($sourceFlag); ?>" alt="<?php echo esc_attr($sourceName); ?>">
                            </span>
                        <?php endif; ?>
                        <small class="novi-site-name-label">
                            <?php echo esc_html($sourceName); ?>
                        </small>
                    </span>
                </p>

                <p class="description">
                    <?php echo esc_html__('Select one or more languages to create or update translations of this post using automatic translation via DeepL.', NoviContentTranslator::TEXT_DOMAIN); ?>
                </p>

                <div class="novi-translator-actions">
                    <button type="button" class="button-link novi-select-all">
                        <span class="novi-action-label">
                            <span class="dashicons dashicons-yes-alt novi-action-icon" aria-hidden="true"></span>
                            <span><?php echo esc_html__('Select all', NoviContentTranslator::TEXT_DOMAIN); ?></span>
                        </span>
                    </button>
                    <span class="separator">·</span>
                    <button type="button" class="button-link novi-clear-all">
                        <span class="novi-action-label">
                            <span class="dashicons dashicons-no-alt novi-action-icon" aria-hidden="true"></span>
                            <span><?php echo esc_html__('Clear selection', NoviContentTranslator::TEXT_DOMAIN); ?></span>
                        </span>
                    </button>
                </div>

                <ul class="novi-translator-sites">
                <?php foreach ($targetLanguages as $langSlug => $langData):
                    $existingTranslationId = isset($existingTranslationsMap[$langSlug]) ? (int) $existingTranslationsMap[$langSlug] : null;
                    $existingTranslationTitle = '';
                    $existingTranslationLink = '';
                    $inputId = 'novi-target-language-' . esc_attr($langSlug);

                    if ($existingTranslationId) {
                        $existingPost = get_post($existingTranslationId);
                        if ($existingPost) {
                            $existingTranslationTitle = $existingPost->post_title;
                            $existingTranslationLink = get_edit_post_link($existingTranslationId, '');
                        }
                    }
                ?>
                    <li>
                        <div class="novi-language-row">
                            <div class="novi-language-main">
                                <input
                                    id="<?php echo $inputId; ?>"
                                    class="novi-language-checkbox"
                                    type="checkbox"
                                    name="novi_target_languages[]"
                                    value="<?php echo esc_attr($langSlug); ?>"
                                    data-language="<?php echo esc_attr($langSlug); ?>"
                                >
                                <label for="<?php echo $inputId; ?>" class="novi-site-name">
                                    <span class="novi-site-label-main">
                                        <span class="novi-site-flag">
                                            <?php
                                            $flagUrl = isset($langData['flag']) ? $langData['flag'] : '';
                                            ?>
                                            <?php if ($flagUrl): ?>
                                                <img src="<?php echo esc_url($flagUrl); ?>" alt="<?php echo esc_attr($langData['name'] ?? $langSlug); ?>">
                                            <?php endif; ?>
                                        </span>
                                        <span class="novi-site-name-label">
                                            <?php echo esc_html($langData['name'] ?? $langSlug); ?>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <?php if ($existingTranslationLink): ?>
                                <div class="novi-existing-translation-link-wrapper">
                                    <small class="novi-translation-prefix">
                                        <?php echo esc_html__('Translation:', NoviContentTranslator::TEXT_DOMAIN); ?>
                                    </small>
                                    <a class="novi-existing-translation-link" href="<?php echo esc_url($existingTranslationLink); ?>" target="_blank" rel="noopener noreferrer">
                                            <span class="novi-existing-translation-link-inner">
                                            <small><?php echo esc_html($existingTranslationTitle); ?></small>
                                            <span class="dashicons dashicons-external novi-link-icon" aria-hidden="true"></span>
                                        </span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            </fieldset>

            <button type="button" id="novi-create-translations" class="button button-primary" <?php echo $post->ID ? '' : 'disabled title="' . esc_attr__('Please save the post first', NoviContentTranslator::TEXT_DOMAIN) . '"'; ?>>
                    <?php echo esc_html__('Duplicate & translate to selected language(s)', NoviContentTranslator::TEXT_DOMAIN); ?>
            </button>
            <?php if (!$post->ID): ?>
                <p class="description">
                    <em><?php echo esc_html__('Please save this post first before creating translations.', NoviContentTranslator::TEXT_DOMAIN); ?></em>
                </p>
            <?php endif; ?>
            <div id="novi-translator-messages"></div>
        </div>
        <?php
    }

    /**
     * Enqueue assets
     * @param string $hook
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        $jsUrl = $this->getWebpackAssetUrlSafe('admin.js.js');
        $cssUrl = $this->getWebpackAssetUrlSafe('admin.scss');

        if ($jsUrl) {
            wp_enqueue_script(
                'novi-content-translator',
                $jsUrl,
                [],
                NoviContentTranslator::PLUGIN_VERSION,
                true
            );

            $postId = get_the_ID();
            if (!$postId && isset($_GET['post'])) {
                $postId = intval($_GET['post']);
            }
            
            wp_localize_script('novi-content-translator', 'noviContentTranslator', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('novi_content_translator_ajax'),
                'postId' => $postId ?: 0,
                'debug' => (defined('WP_DEBUG') && WP_DEBUG),
                'strings' => [
                    'confirmOverwrite' => __('The following languages already have translations. Duplicating will overwrite the existing content. Are you sure you want to continue?', NoviContentTranslator::TEXT_DOMAIN),
                    'creating' => __('Duplicating to selected languages...', NoviContentTranslator::TEXT_DOMAIN),
                    'translatingStrings' => __('Translating %1$d strings for %2$d language(s)...', NoviContentTranslator::TEXT_DOMAIN),
                    'translatingStringsSingle' => __('Translating %d strings...', NoviContentTranslator::TEXT_DOMAIN),
                    'success' => __('Posts duplicated successfully!', NoviContentTranslator::TEXT_DOMAIN),
                    'error' => __('An error occurred while duplicating posts.', NoviContentTranslator::TEXT_DOMAIN),
                    'saveFirst' => __('Please save this post first before creating translations.', NoviContentTranslator::TEXT_DOMAIN),
                ]
            ]);
        }

        if ($cssUrl) {
            wp_enqueue_style(
                'novi-content-translator',
                $cssUrl,
                [],
                NoviContentTranslator::PLUGIN_VERSION
            );
        }
    }

    /**
     * Enqueue assets for the block editor / Site Editor
     * (post editor and Site Editor screens), mirroring Polylang's Editors module.
     *
     * @return void
     */
    public function enqueueBlockEditorAssets(): void
    {
        if (!is_admin()) {
            return;
        }

        // Only run on block-based editors.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !method_exists($screen, 'is_block_editor') || !$screen->is_block_editor()) {
            return;
        }

        $contextPostId = 0;
        $contextPostType = '';

        // Post editor (post.php / post-new.php).
        if ($screen->base === 'post') {
            global $post;
            if ($post instanceof \WP_Post) {
                $contextPostId = (int) $post->ID;
                $contextPostType = (string) $post->post_type;
            } elseif (isset($_GET['post'])) {
                $contextPostId = (int) $_GET['post'];
                $contextPostType = get_post_type($contextPostId) ?: '';
            } else {
                $contextPostType = (string) ($screen->post_type ?? '');
            }
        }

        // Site Editor (templates, patterns like /wp_block/ID).
        if ($screen->base === 'site-editor') {
            global $pagenow;
            if ($pagenow === 'site-editor.php' && !empty($_GET['p'])) {
                $raw = wp_unslash((string) $_GET['p']);
                $decoded = urldecode($raw);
                // Expected format: /post_type/post_id (e.g. /wp_block/1192)
                $parts = explode('/', trim($decoded, '/'));
                if (count($parts) >= 2) {
                    $contextPostType = sanitize_key($parts[0]);
                    $maybeId = intval($parts[count($parts) - 1]);
                    if ($maybeId > 0) {
                        $contextPostId = $maybeId;
                    }
                }
            }
        }

        // For the post editor, we need an actual post context to show the panel.
        // For the Site Editor, the app can client-side navigate from /pattern → /wp_block/ID
        // without a full refresh, so we must enqueue even when we don't yet have a specific ID.
        if ($screen->base === 'post') {
            // Bail if we still don't know what we're editing.
            if (!$contextPostId || !$contextPostType) {
                return;
            }
        }

        // Respect Polylang's translated post types so we don't render for non-translated entities.
        if (function_exists('pll_is_translated_post_type')) {
            $translatedPostTypes = $this->getTranslatedPostTypesSafe();
            if ($contextPostType) {
                if (!in_array($contextPostType, $translatedPostTypes, true) && $contextPostType !== 'wp_block') {
                    return;
                }
            }
        }

        $jsUrl = $this->getWebpackAssetUrlSafe('editor.js.js');
        $cssUrl = $this->getWebpackAssetUrlSafe('admin.scss');

        if ($jsUrl) {
            wp_enqueue_script(
                'novi-content-translator-editor',
                $jsUrl,
                ['wp-plugins', 'wp-data', 'wp-components', 'wp-element', 'wp-i18n'],
                NoviContentTranslator::PLUGIN_VERSION,
                true
            );

            wp_localize_script('novi-content-translator-editor', 'noviContentTranslator', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('novi_content_translator_ajax'),
                'postId' => $contextPostId,
                'postType' => $contextPostType,
                'debug' => (defined('WP_DEBUG') && WP_DEBUG),
                'translatedPostTypes' => function_exists('pll_is_translated_post_type')
                    ? $this->getTranslatedPostTypesSafe()
                    : [],
                'strings' => [
                    'confirmOverwrite' => __('The following languages already have translations. Duplicating will overwrite the existing content. Are you sure you want to continue?', NoviContentTranslator::TEXT_DOMAIN),
                    'creating' => __('Duplicating to selected languages...', NoviContentTranslator::TEXT_DOMAIN),
                    'translatingStrings' => __('Translating %1$d strings for %2$d language(s)...', NoviContentTranslator::TEXT_DOMAIN),
                    'translatingStringsSingle' => __('Translating %d strings...', NoviContentTranslator::TEXT_DOMAIN),
                    'success' => __('Posts duplicated successfully!', NoviContentTranslator::TEXT_DOMAIN),
                    'error' => __('An error occurred while duplicating posts.', NoviContentTranslator::TEXT_DOMAIN),
                    'saveFirst' => __('Please save this post first before creating translations.', NoviContentTranslator::TEXT_DOMAIN),
                ]
            ]);
        }

        if ($cssUrl) {
            wp_enqueue_style(
                'novi-content-translator',
                $cssUrl,
                [],
                NoviContentTranslator::PLUGIN_VERSION
            );
        }
    }

    /**
     * Check for existing translations
     * @return void
     */
    public function checkExistingTranslations(): void
    {
        check_ajax_referer('novi_content_translator_ajax', 'nonce');
        $this->requireLoggedInForAjax();

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $postType = $postId ? get_post_type($postId) : '';
        
        $targetLanguages = $this->parseTargetLanguagesFromRequest();

        if (!$postId || empty($targetLanguages)) {
            wp_send_json_error(['message' => __('Invalid request.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        // Ensure this post type is actually managed by Polylang (mirrors metabox visibility)
        if (!function_exists('pll_is_translated_post_type') || !$postType || !pll_is_translated_post_type($postType)) {
            wp_send_json_error(['message' => __('This post type is not configured as translatable in Polylang.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $this->requireCapabilityForAjax('edit_post', $postId);
        $this->requireValidDeeplKeyForAjax();

        if (!function_exists('pll_get_post_translations')) {
            wp_send_json_error(['message' => __('Polylang is not available.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $allTranslations = (array) pll_get_post_translations($postId);
        $existing = [];
        $post = get_post($postId);
        $missingReusableBlocks = ['has_missing' => false, 'total_missing' => 0, 'by_language' => []];
        if (false) {
            // temporarily disabled: on-the-fly block pattern preflight detection
            $missingReusableBlocks = $post instanceof \WP_Post
                ? $this->getMissingReusableBlocksByLanguage($post, $targetLanguages)
                : ['has_missing' => false, 'total_missing' => 0, 'by_language' => []];
        }

        foreach ($targetLanguages as $langSlug) {
            if (!isset($allTranslations[$langSlug])) {
                continue;
            }
            $existingId = (int) $allTranslations[$langSlug];
            $existingPost = get_post($existingId);
            if (!$existingPost) {
                continue;
            }

            $existing[] = [
                'language' => $langSlug,
                'post_id' => $existingId,
                'post_title' => $this->decodeForJson($existingPost->post_title ?? ''),
                'edit_link' => get_edit_post_link($existingId, ''),
            ];
        }

        wp_send_json_success([
            'existing_translations' => $existing,
            'has_existing' => !empty($existing),
            'missing_reusable_blocks' => $missingReusableBlocks,
        ]);
    }

    /**
     * Get languages and existing translations for a post (for editor panel)
     * @return void
     */
    public function getLanguages(): void
    {
        check_ajax_referer('novi_content_translator_ajax', 'nonce');
        $this->requireLoggedInForAjax();

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$postId) {
            wp_send_json_error(['message' => __('Invalid request.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $this->requireCapabilityForAjax('edit_post', $postId);

        if (!function_exists('pll_the_languages') || !function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations')) {
            wp_send_json_error(['message' => __('Polylang is not available.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $languagesData = pll_the_languages(['raw' => 1]);
        if (empty($languagesData) || !is_array($languagesData)) {
            wp_send_json_error(['message' => __('No languages available for translation.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $currentLangSlug = (string) pll_get_post_language($postId, 'slug');
        $existingTranslationsMap = (array) pll_get_post_translations($postId);

        $targetLanguages = [];
        foreach ($languagesData as $langSlug => $langData) {
            if ($langSlug === $currentLangSlug) {
                continue;
            }
            $existingTranslationId = isset($existingTranslationsMap[$langSlug]) ? (int) $existingTranslationsMap[$langSlug] : 0;
            $existingPostTitle = '';
            $existingEditLink = '';

            if ($existingTranslationId) {
                $existingPost = get_post($existingTranslationId);
                if ($existingPost) {
                    $existingPostTitle = $existingPost->post_title;
                    $existingEditLink = get_edit_post_link($existingTranslationId, '');
                }
            }

            $targetLanguages[$langSlug] = [
                'slug' => $langSlug,
                'name' => $this->decodeForJson((string) ($langData['name'] ?? $langSlug)),
                'flag' => $langData['flag'] ?? '',
                'has_translation' => isset($existingTranslationsMap[$langSlug]),
                'post_title' => $this->decodeForJson($existingPostTitle),
                'edit_link' => $existingEditLink,
            ];
        }

        $currentLanguage = [
            'slug' => $currentLangSlug,
            'name' => $this->decodeForJson((string) ($languagesData[$currentLangSlug]['name'] ?? $currentLangSlug)),
            'flag' => $languagesData[$currentLangSlug]['flag'] ?? '',
        ];

        wp_send_json_success([
            'current_language' => $currentLanguage,
            'target_languages' => $targetLanguages,
        ]);
    }

    /**
     * Count translatable strings for a post (preflight for progress UI)
     * Returns post-level strings (title, excerpt, meta) + block content strings
     * @return void
     */
    public function countTranslationStrings(): void
    {
        check_ajax_referer('novi_content_translator_ajax', 'nonce');
        $this->requireLoggedInForAjax();

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$postId) {
            wp_send_json_error(['message' => __('Invalid request.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $this->requireCapabilityForAjax('edit_post', $postId);
        // strict mode: bypass short cache when actually creating translations
        $this->requireValidDeeplKeyForAjax(true);

        $post = get_post($postId);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        // Post-level: title + excerpt + non-empty translatable meta
        $postStrings = 1; // title
        if (!empty(trim($post->post_excerpt ?? ''))) {
            $postStrings++;
        }
        foreach (PostDuplicator::$translatableMetaKeys as $metaKey) {
            $metaValue = get_post_meta($postId, $metaKey, true);
            if (!empty($metaValue)) {
                $postStrings++;
            }
        }

        $blockStrings = BlockContentTranslator::countTranslatableBlockStrings($post->post_content ?? '');
        $totalStrings = $postStrings + $blockStrings;

        wp_send_json_success([
            'total_strings' => $totalStrings,
            'post_strings' => $postStrings,
            'block_strings' => $blockStrings,
        ]);
    }

    /**
     * Handle AJAX request for creating translations
     * @return void
     */
    public function handleAjaxRequest(): void
    {
        check_ajax_referer('novi_content_translator_ajax', 'nonce');
        $this->requireLoggedInForAjax();

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $postType = $postId ? get_post_type($postId) : '';
        
        $targetLanguages = $this->parseTargetLanguagesFromRequest();
        
        $overwriteExisting = isset($_POST['overwrite_existing']) && ($_POST['overwrite_existing'] === '1' || $_POST['overwrite_existing'] === 'true');
        $createMissingReusableBlocks = isset($_POST['create_missing_reusable_blocks'])
            && ($_POST['create_missing_reusable_blocks'] === '1' || $_POST['create_missing_reusable_blocks'] === 'true');
        if (false) {
            // placeholder for future re-enable
            $createMissingReusableBlocks = $createMissingReusableBlocks;
        } else {
            // temporarily disabled: on-the-fly block pattern creation
            $createMissingReusableBlocks = false;
        }

        if (!$postId || empty($targetLanguages)) {
            wp_send_json_error(['message' => __('Invalid request.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        // Ensure this post type is actually managed by Polylang (mirrors metabox visibility)
        if (!function_exists('pll_is_translated_post_type') || !$postType || !pll_is_translated_post_type($postType)) {
            wp_send_json_error(['message' => __('This post type is not configured as translatable in Polylang.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $this->requireCapabilityForAjax('edit_post', $postId);
        $this->requireValidDeeplKeyForAjax();

        if (!function_exists('pll_get_post_translations') || !function_exists('pll_set_post_language') || !function_exists('pll_save_post_translations')) {
            wp_send_json_error(['message' => __('Polylang is not available.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        $post = get_post($postId);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found.', NoviContentTranslator::TEXT_DOMAIN)]);
        }

        // Existing translations map: lang slug => post ID
        $translations = (array) pll_get_post_translations($postId);
        $results = [];
        $errors = [];
        $unsupportedBlocksMap = [];
        $reusableBlocksSummaryByLanguage = [];
        $reusableSourceRefs = BlockContentTranslator::getReusableBlockRefs((string) ($post->post_content ?? ''));
        $this->debugLog('Reusable flow: request flags', [
            'post_id' => $postId,
            'target_langs' => $targetLanguages,
            'create_missing_reusable_blocks' => $createMissingReusableBlocks,
            'source_refs' => $reusableSourceRefs,
        ]);

        foreach ($targetLanguages as $langSlug) {
            $existingTranslationId = isset($translations[$langSlug]) ? (int) $translations[$langSlug] : null;

            if ($existingTranslationId && !$overwriteExisting) {
                $errors[] = sprintf(
                    __('Translation already exists for language %s. Please confirm overwrite.', NoviContentTranslator::TEXT_DOMAIN),
                    $langSlug
                );
                continue;
            }

            if ($createMissingReusableBlocks && !empty($reusableSourceRefs)) {
                $reusableSummary = PostDuplicator::ensureReusableBlockTranslations($reusableSourceRefs, $langSlug);
                $reusableBlocksSummaryByLanguage[$langSlug] = $reusableSummary;
                if (!empty($reusableSummary['map']) && is_array($reusableSummary['map'])) {
                    BlockContentTranslator::setRuntimeReusableRefMap($langSlug, $reusableSummary['map']);
                    $this->debugLog('Reusable flow: runtime map set', [
                        'lang' => $langSlug,
                        'map' => $reusableSummary['map'],
                    ]);
                }

                if (!empty($reusableSummary['failed']) && is_array($reusableSummary['failed'])) {
                    foreach ($reusableSummary['failed'] as $failure) {
                        $sourceRef = isset($failure['source_ref']) ? (int) $failure['source_ref'] : 0;
                        $failureMessage = isset($failure['error']) ? (string) $failure['error'] : __('Unknown error.', NoviContentTranslator::TEXT_DOMAIN);
                        $errors[] = sprintf(
                            __('Language %1$s: Reusable block %2$d was not translated (%3$s).', NoviContentTranslator::TEXT_DOMAIN),
                            $langSlug,
                            $sourceRef,
                            $failureMessage
                        );
                    }
                }
            }

            // Duplicate or update the post for this language
            $newPostId = PostDuplicator::duplicatePost($postId, $langSlug, $existingTranslationId);
            BlockContentTranslator::clearRuntimeReusableRefMap($langSlug);
            $runUnsupportedBlocks = PostDuplicator::getLastUnsupportedBlocks();
            foreach ($runUnsupportedBlocks as $unsupportedBlockName) {
                $unsupportedBlockName = trim((string) $unsupportedBlockName);
                if ($unsupportedBlockName !== '') {
                    $unsupportedBlocksMap[$unsupportedBlockName] = true;
                }
            }

            if ($newPostId === false) {
                $errorMessage = PostDuplicator::getLastError();
                if ($errorMessage) {
                    $errors[] = sprintf(
                        __('Language %s: %s', NoviContentTranslator::TEXT_DOMAIN),
                        $langSlug,
                        $errorMessage
                    );
                } else {
                    $errors[] = sprintf(
                        __('Failed to create translation for language %s.', NoviContentTranslator::TEXT_DOMAIN),
                        $langSlug
                    );
                }
                continue;
            }

            // Check if there was a translation warning (post was created but translation failed)
            $translationError = PostDuplicator::getLastError();
            $blockIntegrity = PostDuplicator::getLastBlockIntegrity();
            if (is_array($blockIntegrity) && empty($blockIntegrity['ok'])) {
                //integrity failure is fatal for this language — do not treat as soft warning
                $errors[] = sprintf(
                    __('Language %s: Saved translation has broken Gutenberg block markup (lost unicode escape backslashes).', NoviContentTranslator::TEXT_DOMAIN),
                    $langSlug
                );
                continue;
            }
            if ($translationError) {
                // Add as warning, not error, since post was created successfully
                $errors[] = sprintf(
                    __('Language %s: Post created but %s', NoviContentTranslator::TEXT_DOMAIN),
                    $langSlug,
                    $translationError
                );
            }

            // Update translations map
            $translations[$langSlug] = $newPostId;

            $newPost = get_post($newPostId);
            $results[] = [
                'language' => $langSlug,
                'post_id' => $newPostId,
                'post_title' => $newPost ? $this->decodeForJson($newPost->post_title ?? '') : '',
                'edit_link' => get_edit_post_link($newPostId, ''),
                'view_link' => get_permalink($newPostId),
                'block_integrity' => $blockIntegrity ?: [
                    'ok' => $newPost
                        ? !BlockContentTranslator::hasLostUnicodeEscapeBackslashes((string) ($newPost->post_content ?? ''))
                        : true,
                    'repaired' => false,
                    'post_id' => (int) $newPostId,
                ],
            ];
        }

        // Save translations group in Polylang
        if (!empty($translations)) {
            // Make sure each post is set to the expected Polylang language
            if (function_exists('pll_set_post_language') && function_exists('pll_get_post_language')) {
                foreach ($translations as $langCode => $linkedPostId) {
                    $currentLang = pll_get_post_language($linkedPostId, 'slug');
                    if ($currentLang !== $langCode) {
                        pll_set_post_language($linkedPostId, $langCode);
                    }
                }
            }

            pll_save_post_translations($translations);
        }

        if (!empty($errors) && empty($results)) {
            wp_send_json_error(['message' => implode(' ', $errors)]);
        }

        $unsupportedBlocks = array_keys($unsupportedBlocksMap);
        sort($unsupportedBlocks, SORT_NATURAL);

        $reusableTotals = [
            'created' => 0,
            'existing' => 0,
            'failed' => 0,
        ];
        foreach ($reusableBlocksSummaryByLanguage as $langSummary) {
            $reusableTotals['created'] += isset($langSummary['created']) && is_array($langSummary['created']) ? count($langSummary['created']) : 0;
            $reusableTotals['existing'] += isset($langSummary['existing']) && is_array($langSummary['existing']) ? count($langSummary['existing']) : 0;
            $reusableTotals['failed'] += isset($langSummary['failed']) && is_array($langSummary['failed']) ? count($langSummary['failed']) : 0;
        }

        wp_send_json_success([
            'results' => $results,
            'errors' => $errors,
            'unsupported_blocks' => $unsupportedBlocks,
            'reusable_blocks' => [
                'by_language' => $reusableBlocksSummaryByLanguage,
                'totals' => $reusableTotals,
            ],
            'message' => sprintf(
                __('Successfully created and/or updated %d translation(s).', NoviContentTranslator::TEXT_DOMAIN),
                count($results)
            )
        ]);
    }
}
