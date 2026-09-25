<?php

namespace NoviOnline\ContentTranslator\Core;

use NoviOnline\ContentTranslator\Core\DeepLTranslator;
use NoviOnline\ContentTranslator\Core\BlockContentTranslator;
use NoviOnline\ContentTranslator\Core\TermDuplicator;
use NoviOnline\ContentTranslator\ContentTranslatorComponent;

/**
 * Class PostDuplicator
     * @package NoviOnline\ContentTranslator\Core
 */
class PostDuplicator
{
    /**
     * Post types where we don't translate the title via DeepL.
     * Instead we prefix the target language code (eg "NL - ").
     * @var array
     */
    private static array $titlePrefixOnlyPostTypes = [
        'nectar_sections',
        'nectar_templates',
        'wp_block',
    ];

    /**
     * Post types where the title is copied as-is (no DeepL, no language prefix).
     * Used for person names on `team`, brand/project names on `project`, etc.
     * @var array<int, string>
     */
    private static array $titleKeepAsIsPostTypes = [
        'team',
        'project',
    ];

    /**
     * Allow overriding the list of prefix-only post types.
     * Hook name: nct_prefix_post_types
     *
     * @return array
     */
    protected static function getTitlePrefixOnlyPostTypes(): array
    {
        $postTypes = apply_filters('nct_prefix_post_types', self::$titlePrefixOnlyPostTypes);
        if (!is_array($postTypes)) {
            return self::$titlePrefixOnlyPostTypes;
        }
        // normalize to strings
        return array_values(array_filter(array_map('strval', $postTypes), static function ($v) {
            return $v !== '';
        }));
    }

    /**
     * Allow overriding post types whose titles are kept unchanged.
     * Hook name: nct_keep_title_post_types
     *
     * Also used by StringOverrules to auto-protect those titles in body text.
     *
     * @return array<int, string>
     */
    public static function getTitleKeepAsIsPostTypes(): array
    {
        $postTypes = apply_filters('nct_keep_title_post_types', self::$titleKeepAsIsPostTypes);
        if (!is_array($postTypes)) {
            return self::$titleKeepAsIsPostTypes;
        }

        return array_values(array_filter(array_map('strval', $postTypes), static function ($v) {
            return $v !== '';
        }));
    }

    /**
     * Debug logging helper (only when WP_DEBUG is enabled)
     * @param string $message
     * @param array $context
     * @return void
     */
    protected static function debugLog(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $suffix = $context !== [] ? ' ' . wp_json_encode($context) : '';
        error_log('Novi content translator: ' . $message . $suffix);
    }

    /**
     * Resolve which author to use on the translated post.
     * We default to the original author to preserve attribution across translations.
     */
    private static function resolveTargetAuthorId(int $sourceAuthorId): int
    {
        $sourceAuthorId = (int) $sourceAuthorId;
        if ($sourceAuthorId > 0) {
            return $sourceAuthorId;
        }

        if (function_exists('wp_get_current_user')) {
            $u = wp_get_current_user();
            if (is_object($u) && isset($u->ID) && is_numeric($u->ID) && (int) $u->ID > 0) {
                return (int) $u->ID;
            }
        }

        return 0;
    }

    /**
     * Meta keys that should be translated
     * Add meta keys here to enable translation for them
     * @var array
     */
    public static array $translatableMetaKeys = [
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_focuskw',
        '_yoast_wpseo_bctitle',
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_twitter-title',
        '_yoast_wpseo_twitter-description',
        '_nectar_portfolio_description',
        '_nectar_portfolio_client',
        '_nectar_portfolio_extra_content',
        '_nectar_quote',
        '_nectar_quote_author',
        '_nectar_header_title',
        '_nectar_header_subtitle',
        'team_function',
        'team_quote',
        'novi_page_service_type',
    ];

    /**
     * ACF relationship-style meta keys whose post IDs should be remapped via Polylang.
     * @var array<int, string>
     */
    public static array $relationshipMetaKeys = [
        'connected_team_members',
    ];

    /**
     * Allow overriding translatable meta keys.
     * Hook name: nct_translatable_meta_keys
     *
     * @return array
     */
    public static function getTranslatableMetaKeys(): array
    {
        $metaKeys = apply_filters('nct_translatable_meta_keys', self::$translatableMetaKeys);
        if (!is_array($metaKeys)) {
            return self::$translatableMetaKeys;
        }

        $normalized = array_filter(
            array_map('strval', $metaKeys),
            static function ($v) {
                return $v !== '';
            }
        );

        return array_values($normalized);
    }

    /**
     * Allow overriding relationship meta keys that need Polylang post-ID remapping.
     * Hook name: nct_relationship_meta_keys
     *
     * @return array<int, string>
     */
    public static function getRelationshipMetaKeys(): array
    {
        $metaKeys = apply_filters('nct_relationship_meta_keys', self::$relationshipMetaKeys);
        if (!is_array($metaKeys)) {
            return self::$relationshipMetaKeys;
        }

        $normalized = array_filter(
            array_map('strval', $metaKeys),
            static function ($v) {
                return $v !== '';
            }
        );

        return array_values($normalized);
    }

    /**
     * Remap ACF relationship post IDs in collected post meta to the target language.
     * Missing translations keep the source ID (debug-logged). Field-key meta (`_…`) is untouched.
     *
     * @param array<string, array<int, mixed>> $sourcePostMeta
     * @param string $targetLanguageSlug
     * @return array<string, array<int, mixed>>
     */
    public static function remapRelationshipMeta(array $sourcePostMeta, string $targetLanguageSlug): array
    {
        $targetLanguageSlug = strtolower(trim($targetLanguageSlug));
        if ($targetLanguageSlug === '' || !function_exists('pll_get_post')) {
            return $sourcePostMeta;
        }

        foreach (self::getRelationshipMetaKeys() as $metaKey) {
            if (!isset($sourcePostMeta[$metaKey]) || !is_array($sourcePostMeta[$metaKey])) {
                continue;
            }

            foreach ($sourcePostMeta[$metaKey] as $rowIndex => $rowValue) {
                $sourcePostMeta[$metaKey][$rowIndex] = self::remapRelationshipValue(
                    $rowValue,
                    $targetLanguageSlug,
                    $metaKey
                );
            }
        }

        return $sourcePostMeta;
    }

    /**
     * @param mixed $value
     * @param string $targetLanguageSlug
     * @param string $metaKey
     * @return mixed
     */
    private static function remapRelationshipValue($value, string $targetLanguageSlug, string $metaKey)
    {
        if (is_numeric($value) && !is_array($value)) {
            return self::resolveRelationshipPostId((int) $value, $targetLanguageSlug, $metaKey);
        }

        if (!is_array($value)) {
            return $value;
        }

        $remapped = [];
        foreach ($value as $key => $item) {
            if (is_numeric($item) && !is_array($item)) {
                $remapped[$key] = self::resolveRelationshipPostId((int) $item, $targetLanguageSlug, $metaKey);
                continue;
            }
            $remapped[$key] = $item;
        }

        return $remapped;
    }

    /**
     * @param int $sourceId
     * @param string $targetLanguageSlug
     * @param string $metaKey
     * @return int
     */
    private static function resolveRelationshipPostId(int $sourceId, string $targetLanguageSlug, string $metaKey): int
    {
        if ($sourceId <= 0) {
            return $sourceId;
        }

        $targetId = (int) pll_get_post($sourceId, $targetLanguageSlug);
        if ($targetId > 0) {
            return $targetId;
        }

        self::debugLog('Relationship meta: no translation; keeping source ID', [
            'meta_key' => $metaKey,
            'source_id' => $sourceId,
            'target_lang' => $targetLanguageSlug,
        ]);

        return $sourceId;
    }

    /**
     * Last error message
     * @var string|null
     */
    private static ?string $lastError = null;

    /**
     * Used to allow same slug across languages during Polylang insert.
     */
    private static string $currentSlugInsertLang = '';

    /**
     * Last unsupported blocks encountered during duplication.
     * @var array<int, string>
     */
    private static array $lastUnsupportedBlocks = [];

    /**
     * Last block integrity check after save (lost \uXXXX backslashes).
     * @var array{ok:bool,repaired:bool,post_id:int}|null
     */
    private static ?array $lastBlockIntegrity = null;

    /**
     * Resolve the translated parent ID for hierarchical post types (notably pages).
     * If there is no translated parent in the target language, returns 0.
     */
    private static function resolveTargetParentId(\WP_Post $sourcePost, string $targetLanguageSlug): int
    {
        $sourceParentId = isset($sourcePost->post_parent) ? (int) $sourcePost->post_parent : 0;
        if ($sourceParentId <= 0) {
            return 0;
        }

        $postType = isset($sourcePost->post_type) ? (string) $sourcePost->post_type : '';
        $isHierarchical = false;
        if (function_exists('is_post_type_hierarchical')) {
            $isHierarchical = (bool) is_post_type_hierarchical($postType);
        } elseif (function_exists('get_post_type_object')) {
            $obj = get_post_type_object($postType);
            $isHierarchical = is_object($obj) && !empty($obj->hierarchical);
        } else {
            //fallback for minimal runtimes/tests
            $isHierarchical = ($postType === 'page');
        }

        if (!$isHierarchical) {
            return 0;
        }

        if (!function_exists('pll_get_post')) {
            return 0;
        }

        $translatedParentId = (int) pll_get_post($sourceParentId, $targetLanguageSlug);
        return $translatedParentId > 0 ? $translatedParentId : 0;
    }

    /**
     * Sync hierarchical post_parent on an existing translation without re-running DeepL.
     * Useful when a child was translated before its parent existed (only-missing skips).
     *
     * @return string One of: skipped, unchanged, updated, failed
     */
    public static function syncHierarchicalParent(int $sourcePostId, int $targetPostId, string $targetLanguageSlug): string
    {
        $sourcePostId = (int) $sourcePostId;
        $targetPostId = (int) $targetPostId;
        $targetLanguageSlug = strtolower(trim($targetLanguageSlug));
        if ($sourcePostId <= 0 || $targetPostId <= 0 || $targetLanguageSlug === '') {
            return 'skipped';
        }
        if (!function_exists('get_post') || !function_exists('wp_update_post')) {
            return 'skipped';
        }

        $sourcePost = get_post($sourcePostId);
        $targetPost = get_post($targetPostId);
        if (!$sourcePost || !$targetPost) {
            return 'failed';
        }

        $desiredParentId = self::resolveTargetParentId($sourcePost, $targetLanguageSlug);
        $currentParentId = isset($targetPost->post_parent) ? (int) $targetPost->post_parent : 0;
        if ($currentParentId === $desiredParentId) {
            return 'unchanged';
        }

        $result = wp_update_post([
            'ID' => $targetPostId,
            'post_parent' => $desiredParentId,
        ], true);

        if (function_exists('is_wp_error') && is_wp_error($result)) {
            self::debugLog('syncHierarchicalParent failed', [
                'source_post_id' => $sourcePostId,
                'target_post_id' => $targetPostId,
                'desired_parent' => $desiredParentId,
                'error' => method_exists($result, 'get_error_message') ? (string) $result->get_error_message() : 'wp_error',
            ]);
            return 'failed';
        }

        self::debugLog('syncHierarchicalParent updated', [
            'source_post_id' => $sourcePostId,
            'target_post_id' => $targetPostId,
            'from_parent' => $currentParentId,
            'to_parent' => $desiredParentId,
        ]);

        return 'updated';
    }

    /**
     * Get the last error message
     * @return string|null
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Get last unsupported block names from the most recent duplication call.
     * @return array<int, string>
     */
    public static function getLastUnsupportedBlocks(): array
    {
        return self::$lastUnsupportedBlocks;
    }

    /**
     * @return array{ok:bool,repaired:bool,post_id:int}|null
     */
    public static function getLastBlockIntegrity(): ?array
    {
        return self::$lastBlockIntegrity;
    }

    /**
     * Set the last error message
     * @param string|null $error
     * @return void
     */
    private static function setLastError(?string $error): void
    {
        self::$lastError = $error;
    }

    /**
     * Set last unsupported block names.
     * @param array<int, string> $unsupportedBlocks
     * @return void
     */
    private static function setLastUnsupportedBlocks(array $unsupportedBlocks): void
    {
        self::$lastUnsupportedBlocks = array_values(array_unique(array_filter(array_map('strval', $unsupportedBlocks))));
    }

    /**
     * Persist post_content safely for Gutenberg JSON unicode escapes (\u003c, \u0022, …).
     * wp_update_post()/wp_insert_post() always wp_unslash() array input — callers must pass
     * intended (unslashed) content; this helper repairs known corruption then wp_slash()s once.
     *
     * @param int $postId
     * @param string $content
     * @return int|\WP_Error
     */
    public static function persistPostContent(int $postId, string $content)
    {
        if ($postId <= 0) {
            return function_exists('is_wp_error')
                ? new \WP_Error('invalid_post', 'Invalid post ID')
                : 0;
        }
        if (!function_exists('wp_update_post')) {
            return function_exists('is_wp_error')
                ? new \WP_Error('missing_wp_update_post', 'wp_update_post unavailable')
                : 0;
        }

        if (BlockContentTranslator::hasLostUnicodeEscapeBackslashes($content)) {
            $content = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($content);
        }

        $result = wp_update_post(wp_slash([
            'ID' => $postId,
            'post_content' => $content,
        ]), true);

        if (function_exists('is_wp_error') && is_wp_error($result)) {
            return $result;
        }
        if ((int) $result <= 0) {
            return function_exists('is_wp_error')
                ? new \WP_Error('update_failed', 'Failed to update post content')
                : 0;
        }

        if (!self::ensurePersistedBlockIntegrity($postId, $content)) {
            return function_exists('is_wp_error')
                ? new \WP_Error(
                    'block_integrity',
                    __('Saved content has broken Gutenberg unicode escapes (lost backslashes before \\uXXXX).', 'novi-content-translator')
                )
                : 0;
        }

        return (int) $result;
    }

    /**
     * After wp_insert_post/wp_update_post, verify JSON unicode escapes survived.
     * If stripslashes ate "\uXXXX" backslashes, re-save intended content with wp_slash().
     *
     * @return bool true when stored content is free of lost-escape corruption
     */
    private static function ensurePersistedBlockIntegrity(int $postId, string $intendedContent): bool
    {
        self::$lastBlockIntegrity = [
            'ok' => true,
            'repaired' => false,
            'post_id' => $postId,
        ];

        if ($postId <= 0 || !function_exists('get_post') || !function_exists('wp_update_post')) {
            return true;
        }

        $saved = get_post($postId);
        if (!$saved || !is_object($saved)) {
            return true;
        }

        $savedContent = (string) ($saved->post_content ?? '');
        if (!BlockContentTranslator::hasLostUnicodeEscapeBackslashes($savedContent)) {
            return true;
        }

        self::debugLog('Block integrity: lost unicode escape backslashes after save; repairing', [
            'post_id' => $postId,
        ]);

        $contentToSave = $intendedContent;
        if (BlockContentTranslator::hasLostUnicodeEscapeBackslashes($contentToSave)) {
            $contentToSave = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($contentToSave);
        }

        wp_update_post(wp_slash([
            'ID' => $postId,
            'post_content' => $contentToSave,
        ]), true);

        $savedAgain = get_post($postId);
        $stillBroken = $savedAgain && is_object($savedAgain)
            && BlockContentTranslator::hasLostUnicodeEscapeBackslashes((string) ($savedAgain->post_content ?? ''));

        self::$lastBlockIntegrity = [
            'ok' => !$stillBroken,
            'repaired' => !$stillBroken,
            'post_id' => $postId,
        ];

        if ($stillBroken) {
            self::setLastError(__('Translated content has broken Gutenberg unicode escapes (lost backslashes before \\uXXXX).', 'novi-content-translator'));
            return false;
        }

        return true;
    }

    /**
     * Ensure reusable block (wp_block) translations exist for target language.
     * Returns per-ref status and source->target map for immediate relinking.
     *
     * @param array<int, int> $sourceRefs
     * @param string $targetLanguageSlug
     * @return array{
     *   map: array<int, int>,
     *   created: array<int, array<string, mixed>>,
     *   existing: array<int, array<string, mixed>>,
     *   failed: array<int, array<string, mixed>>
     * }
     */
    public static function ensureReusableBlockTranslations(array $sourceRefs, string $targetLanguageSlug): array
    {
        self::debugLog('Reusable ensure: start', [
            'target_lang' => $targetLanguageSlug,
            'source_refs' => array_values(array_map('intval', $sourceRefs)),
        ]);

        $summary = [
            'map' => [],
            'created' => [],
            'existing' => [],
            'failed' => [],
        ];

        if (!function_exists('get_post') || !function_exists('pll_get_post') || !function_exists('pll_get_post_translations') || !function_exists('pll_save_post_translations')) {
            foreach ($sourceRefs as $sourceRef) {
                if (!is_numeric($sourceRef) || (int) $sourceRef <= 0) {
                    continue;
                }
                $summary['failed'][] = [
                    'source_ref' => (int) $sourceRef,
                    'error' => __('Polylang is not available.', 'novi-content-translator'),
                ];
            }
            return $summary;
        }

        $normalizedRefs = array_values(array_unique(array_filter(array_map('intval', $sourceRefs), static function ($id) {
            return $id > 0;
        })));

        foreach ($normalizedRefs as $sourceRef) {
            $sourcePost = get_post($sourceRef);
            if (!$sourcePost || (string) $sourcePost->post_type !== 'wp_block') {
                self::debugLog('Reusable ensure: source ref invalid', [
                    'source_ref' => (int) $sourceRef,
                    'post_type' => $sourcePost ? (string) $sourcePost->post_type : null,
                ]);
                $summary['failed'][] = [
                    'source_ref' => (int) $sourceRef,
                    'error' => __('Reusable block not found.', 'novi-content-translator'),
                ];
                continue;
            }

            $sourceTitle = (string) ($sourcePost->post_title ?? '');
            $existingTranslationId = (int) pll_get_post($sourceRef, $targetLanguageSlug);
            self::debugLog('Reusable ensure: lookup existing', [
                'source_ref' => (int) $sourceRef,
                'target_lang' => $targetLanguageSlug,
                'existing_target_ref' => $existingTranslationId,
            ]);
            if ($existingTranslationId > 0) {
                $summary['map'][(int) $sourceRef] = $existingTranslationId;
                $existingPost = get_post($existingTranslationId);
                $summary['existing'][] = [
                    'source_ref' => (int) $sourceRef,
                    'target_ref' => $existingTranslationId,
                    'source_title' => $sourceTitle,
                    'target_title' => (string) ($existingPost ? $existingPost->post_title : ''),
                ];
                continue;
            }

            $newTranslationId = self::duplicatePost($sourceRef, $targetLanguageSlug, null);
            if ($newTranslationId === false || !is_numeric($newTranslationId) || (int) $newTranslationId <= 0) {
                self::debugLog('Reusable ensure: create failed', [
                    'source_ref' => (int) $sourceRef,
                    'target_lang' => $targetLanguageSlug,
                    'error' => self::getLastError(),
                ]);
                $summary['failed'][] = [
                    'source_ref' => (int) $sourceRef,
                    'source_title' => $sourceTitle,
                    'error' => self::getLastError() ?: __('Failed to create reusable block translation.', 'novi-content-translator'),
                ];
                continue;
            }

            $newTranslationId = (int) $newTranslationId;
            $summary['map'][(int) $sourceRef] = $newTranslationId;

            // Link reusable block translations in Polylang immediately.
            $translations = (array) pll_get_post_translations($sourceRef);
            $sourceLangSlug = function_exists('pll_get_post_language')
                ? (string) pll_get_post_language($sourceRef, 'slug')
                : '';
            if ($sourceLangSlug !== '') {
                $translations[$sourceLangSlug] = (int) $sourceRef;
            }
            $translations[$targetLanguageSlug] = $newTranslationId;
            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($newTranslationId, $targetLanguageSlug);
            }
            pll_save_post_translations($translations);

            $postSaveLookup = (int) pll_get_post($sourceRef, $targetLanguageSlug);
            $postSaveMap = (array) pll_get_post_translations($sourceRef);
            self::debugLog('Reusable ensure: linked translation', [
                'source_ref' => (int) $sourceRef,
                'target_lang' => $targetLanguageSlug,
                'new_target_ref' => $newTranslationId,
                'source_lang' => $sourceLangSlug,
                'post_save_lookup' => $postSaveLookup,
                'post_save_map' => $postSaveMap,
            ]);

            $newPost = get_post($newTranslationId);
            $summary['created'][] = [
                'source_ref' => (int) $sourceRef,
                'target_ref' => $newTranslationId,
                'source_title' => $sourceTitle,
                'target_title' => (string) ($newPost ? $newPost->post_title : ''),
            ];
        }

        self::debugLog('Reusable ensure: done', [
            'target_lang' => $targetLanguageSlug,
            'created' => count($summary['created']),
            'existing' => count($summary['existing']),
            'failed' => count($summary['failed']),
            'map' => $summary['map'],
        ]);

        return $summary;
    }

    /**
     * Decide how to build the translated title for a given post type.
     *
     * @param string $postType
     * @param string $sourceTitle
     * @param string $targetLanguageSlug
     * @param string $sourceLanguageSlug optional; used for glossary lookup on keep-as-is titles
     * @return array{shouldTranslateTitle: bool, targetTitle: string}
     */
    public static function buildTargetTitleForPostType(
        string $postType,
        string $sourceTitle,
        string $targetLanguageSlug,
        string $sourceLanguageSlug = ''
    ): array {
        $trimmedTitle = trim($sourceTitle);

        //person names / brand/project titles: copy unchanged, unless glossary maps the title
        if (in_array($postType, self::getTitleKeepAsIsPostTypes(), true)) {
            $glossaryTarget = '';
            if ($trimmedTitle !== '' && $sourceLanguageSlug !== '') {
                $glossaryTarget = StringOverrules::getGlossaryTargetForSource(
                    $trimmedTitle,
                    $sourceLanguageSlug,
                    $targetLanguageSlug
                );
            }
            if ($glossaryTarget !== '') {
                return [
                    'shouldTranslateTitle' => false,
                    'targetTitle' => $glossaryTarget,
                ];
            }

            return [
                'shouldTranslateTitle' => false,
                'targetTitle' => $trimmedTitle,
            ];
        }

        $prefixOnlyPostTypes = self::getTitlePrefixOnlyPostTypes();
        $shouldTranslateTitle = !in_array($postType, $prefixOnlyPostTypes, true);

        if ($shouldTranslateTitle) {
            return [
                'shouldTranslateTitle' => true,
                'targetTitle' => $trimmedTitle,
            ];
        }

        $langCode = strtoupper(trim($targetLanguageSlug));
        $prefix = $langCode . ' - ';

        if ($trimmedTitle === '') {
            return [
                'shouldTranslateTitle' => false,
                'targetTitle' => '',
            ];
        }

        // Avoid stacking prefixes when translating back and forth (e.g. "NL - EN - Title").
        // Only strip prefixes that match known Polylang language slugs for this site.
        $trimmedTitle = self::stripKnownLanguagePrefixes($trimmedTitle);

        // idempotency: avoid double-prefixing
        if (strpos($trimmedTitle, $prefix) === 0) {
            return [
                'shouldTranslateTitle' => false,
                'targetTitle' => $trimmedTitle,
            ];
        }

        return [
            'shouldTranslateTitle' => false,
            'targetTitle' => $prefix . $trimmedTitle,
        ];
    }

    /**
     * Build the target post_name by translating the source slug, not the translated title.
     * Example: NL post_name "team" → EN "team"; "over-ons" → "about-us".
     *
     * @param string $sourcePostName Source post_name (leaf slug)
     * @param string $sourceLanguage Source Polylang language slug
     * @param string $targetLanguage Target Polylang language slug
     * @return string Sanitized target slug, or empty when source slug is empty
     */
    public static function buildTargetPostName(string $sourcePostName, string $sourceLanguage, string $targetLanguage): string
    {
        $sourcePostName = trim($sourcePostName);
        if ($sourcePostName === '') {
            return '';
        }

        $sourceLang = strtolower(trim($sourceLanguage));
        $targetLang = strtolower(trim($targetLanguage));

        //same language: keep sanitized source slug
        if ($sourceLang !== '' && $sourceLang === $targetLang) {
            return self::sanitizePostNameSlug($sourcePostName);
        }

        //translate slug as human-readable words (hyphens/underscores → spaces)
        $plainText = str_replace(['-', '_'], ' ', $sourcePostName);
        $translated = DeepLTranslator::translateText($plainText, $sourceLanguage, $targetLanguage, ['context' => 'plain']);
        $translatedSlug = self::sanitizePostNameSlug((string) $translated);

        if ($translatedSlug === '') {
            //fallback to sanitized source slug when translation yields nothing usable
            return self::sanitizePostNameSlug($sourcePostName);
        }

        return $translatedSlug;
    }

    /**
     * Sanitize a slug candidate the same way WordPress does for post_name.
     *
     * @param string $text
     * @return string
     */
    private static function sanitizePostNameSlug(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('sanitize_title')) {
            return (string) sanitize_title($text);
        }

        $lower = function_exists('mb_strtolower') ? (string) mb_strtolower($text) : strtolower($text);
        $lower = preg_replace('/[^a-z0-9\\s\\-]/', '', $lower);
        $lower = preg_replace('/[\\s\\-]+/', '-', (string) $lower);
        return trim((string) $lower, '-');
    }

    /**
     * Strip one or more leading "{LANG} - " prefixes when {LANG} is a known Polylang language slug.
     * @param string $title
     * @return string
     */
    private static function stripKnownLanguagePrefixes(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $known = [];
        if (function_exists('pll_languages_list')) {
            $slugs = pll_languages_list(['fields' => 'slug']);
            if (is_array($slugs)) {
                foreach ($slugs as $slug) {
                    $slug = strtoupper(trim((string) $slug));
                    if ($slug !== '') {
                        $known[$slug] = true;
                    }
                }
            }
        }

        if (empty($known)) {
            return $title;
        }

        // Strip repeatedly to handle stacked prefixes: "NL - EN - Title".
        while (preg_match('/^([A-Z0-9_]+)\s-\s+/u', $title, $m)) {
            $candidate = strtoupper((string) ($m[1] ?? ''));
            if (!isset($known[$candidate])) {
                break;
            }
            $title = trim(substr($title, strlen($m[0])));
        }

        return $title;
    }

    /**
     * Duplicate or update a post for a target Polylang language
     * @param int $sourcePostId
     * @param string $targetLanguageSlug
     * @param int|null $existingPostId
     * @return int|false Returns post ID on success, false on failure. Also sets last error via getLastError()
     */
    public static function duplicatePost(int $sourcePostId, string $targetLanguageSlug, ?int $existingPostId = null): int|false
    {
        self::setLastError(null);
        self::$lastBlockIntegrity = null;
        self::setLastUnsupportedBlocks([]);
        $post = get_post($sourcePostId);

        if (!$post) {
            self::setLastError(__('Post not found.', 'novi-content-translator'));
            return false;
        }

        //never create/update a translation from a trashed source
        if ((string) $post->post_status === 'trash') {
            self::setLastError(__('Source post is in trash; skipping translation.', 'novi-content-translator'));
            return false;
        }

        //attach same-title orphans in the target language (e.g. team members created out of band)
        if (($existingPostId === null || (int) $existingPostId <= 0) && function_exists('pll_get_post')) {
            $linked = (int) pll_get_post($sourcePostId, $targetLanguageSlug);
            if ($linked > 0) {
                $existingPostId = $linked;
            } else {
                $orphanId = self::findOrphanTranslationByTitle($post, $targetLanguageSlug);
                if ($orphanId > 0) {
                    $existingPostId = $orphanId;
                }
            }
        }

        // Get languages for translation (WordPress locales)
        $sourceLanguage = function_exists('pll_get_post_language')
            ? (string) pll_get_post_language($sourcePostId, 'slug')
            : (string) get_locale();
        $targetLanguage = $targetLanguageSlug;

        // Get terms from source blog BEFORE switching
        $sourceTaxonomies = [];
        $postTaxonomies = get_object_taxonomies($post->post_type);
        if (post_type_supports($post->post_type, 'post-formats') && !in_array('post_format', $postTaxonomies, true)) {
            $postTaxonomies[] = 'post_format';
        }
        
        foreach ($postTaxonomies as $taxonomy) {
            //never sync Polylang internal taxonomies
            if ($taxonomy === 'language' || $taxonomy === 'post_translations') {
                continue;
            }
            $postTerms = wp_get_object_terms($sourcePostId, $taxonomy, ['orderby' => 'term_order']);
            if (!is_wp_error($postTerms) && !empty($postTerms)) {
                $terms = [];
                foreach ($postTerms as $term) {
                    if (!is_object($term)) {
                        continue;
                    }
                    $termId = isset($term->term_id) ? (int) $term->term_id : 0;

                    // Ensure we treat terms as coming from the source post language.
                    // Sometimes terms on a post can be in a different language (e.g. mis-assigned in admin),
                    // and then pll_get_term(termId, targetLang) won't find the correct translation chain.
                    if (
                        $termId > 0
                        && $sourceLanguage !== ''
                        && function_exists('pll_get_term_language')
                        && function_exists('pll_get_term')
                        && function_exists('get_term')
                    ) {
                        $termLang = (string) pll_get_term_language($termId, 'slug');
                        if ($termLang !== '' && $termLang !== $sourceLanguage) {
                            $sourceLangTermId = (int) TermDuplicator::resolveCanonicalSourceTermId($termId, $sourceLanguage, $taxonomy);
                            if ($sourceLangTermId > 0) {
                                $termObj = get_term($sourceLangTermId, $taxonomy);
                                if (is_object($termObj) && isset($termObj->term_id)) {
                                    $term = $termObj;
                                    $termId = (int) $termObj->term_id;
                                }
                            }
                        }
                    }

                    $slug = isset($term->slug) ? (string) $term->slug : '';
                    $name = isset($term->name) ? (string) $term->name : '';
                    $description = isset($term->description) ? (string) $term->description : '';
                    $parent = isset($term->parent) ? (int) $term->parent : 0;

                    if ($termId <= 0 || $slug === '') {
                        continue;
                    }

                    $terms[] = [
                        'termId' => $termId,
                        'slug' => $slug,
                        'name' => $name,
                        'description' => $description,
                        'parent' => $parent,
                    ];
                }
                if ($terms !== []) {
                    $sourceTaxonomies[$taxonomy] = $terms;
                }
            }
        }

        // Get featured image ID from source
        $thumbnailId = get_post_thumbnail_id($sourcePostId);

        self::debugLog('Duplicate post: start', [
            'source_post_id' => $sourcePostId,
            'target_lang' => $targetLanguageSlug,
            'existing_post_id' => $existingPostId,
        ]);

        if (has_blocks((string) $post->post_content)) {
            self::setLastUnsupportedBlocks(BlockContentTranslator::getUnsupportedBlockNames((string) $post->post_content));
        }

        //what to translate: hardcoded defaults (only formality is stored in settings)
        $translateMeta = true;

        $titlePolicy = self::buildTargetTitleForPostType(
            (string) $post->post_type,
            (string) $post->post_title,
            $targetLanguage,
            (string) $sourceLanguage
        );

        $translateTitles = (bool) $titlePolicy['shouldTranslateTitle'];
        $translatedTitle = (string) $titlePolicy['targetTitle'];

        // Collect translatable post fields: we always translate excerpt,
        // and optionally translate the title (or prefix it instead).
        $translatablePostFields = [
            'post_excerpt' => $post->post_excerpt ?: '',
        ];
        if ($translateTitles) {
            $translatablePostFields = [
                'post_title' => $post->post_title,
                'post_excerpt' => $post->post_excerpt ?: '',
            ];
        }

        // Collect translatable meta fields based on whitelist (respect settings)
        $translatableMetaFields = [];
        if ($translateMeta) {
            foreach (self::getTranslatableMetaKeys() as $metaKey) {
                $metaValue = get_post_meta($sourcePostId, $metaKey, true);
                if (!empty($metaValue)) {
                    $translatableMetaFields[$metaKey] = $metaValue;
                }
            }
        }

        // Combine all translatable strings
        $allTextsToTranslate = array_merge(
            array_values($translatablePostFields),
            array_values($translatableMetaFields)
        );

        // Translate all texts in batch
        $translationResult = DeepLTranslator::translateTexts($allTextsToTranslate, $sourceLanguage, $targetLanguage);

        // Check for translation errors
        if (!$translationResult['success']) {
            self::setLastError($translationResult['error'] ?? __('Translation failed.', 'novi-content-translator'));
            // Continue with duplication using original texts
        }

        $translatedTexts = $translationResult['translations'] ?? $allTextsToTranslate;

        // Extract translated values for post fields, respecting settings
        $translatedExcerpt = $post->post_excerpt;
        $fieldCursor = 0;
        if ($translateTitles) {
            $translatedTitle = $translatedTexts[$fieldCursor] ?? $post->post_title;
            $fieldCursor++;
        }
        $translatedExcerpt = $translatedTexts[$fieldCursor] ?? $post->post_excerpt;

        // Extract translated values for meta fields
        $translatedMetaFields = [];
        $metaIndex = count($translatablePostFields);
        foreach ($translatableMetaFields as $metaKey => $originalValue) {
            if ($translateMeta) {
                $translatedMetaFields[$metaKey] = $translatedTexts[$metaIndex] ?? $originalValue;
            } else {
                $translatedMetaFields[$metaKey] = $originalValue;
            }
            $metaIndex++;
        }

        // Get all post meta from source BEFORE switching
        // This includes ALL meta keys (featured image, ACF fields, custom fields, etc.)
        // Only specific keys in $translatableMetaKeys will be translated later
        // All other meta keys are preserved as-is
        $sourcePostMeta = [];
        $postMetaKeys = get_post_custom_keys($sourcePostId);
        if (!empty($postMetaKeys)) {
            $metaBlacklist = [
                '_edit_lock',
                '_edit_last',
                '_dp_original',
                '_dp_is_rewrite_republish_copy',
                '_dp_has_rewrite_republish_copy',
            ];
            
            foreach ($postMetaKeys as $metaKey) {
                if (in_array($metaKey, $metaBlacklist, true)) {
                    continue;
                }
                // Keep legacy msls_* metadata out of duplicated records
                if (strpos($metaKey, 'msls_') === 0) {
                    continue;
                }
                
                $metaValues = get_post_custom_values($metaKey, $sourcePostId);
                if (!empty($metaValues)) {
                    $sourcePostMeta[$metaKey] = array_map(function($value) {
                        return maybe_unserialize($value);
                    }, $metaValues);
                }
            }
        }

        // Update translatable meta fields with translated values
        // Only update meta keys that are in the translatable list
        // All other meta keys (like _thumbnail_id, featured image, etc.) are preserved as-is
        foreach ($translatedMetaFields as $metaKey => $translatedValue) {
            // Only update if this meta key exists in sourcePostMeta (was collected above)
            // This ensures we don't accidentally add new meta keys that weren't in the source
            if (isset($sourcePostMeta[$metaKey])) {
                if (!empty($translatedValue)) {
                    // Use translated value, preserving array structure
                    $sourcePostMeta[$metaKey] = [$translatedValue];
                } else {
                    // Keep original if translation failed or was empty
                    $originalValue = $translatableMetaFields[$metaKey] ?? '';
                    // Preserve original structure if translation is empty
                    if (!empty($originalValue)) {
                        $sourcePostMeta[$metaKey] = [$originalValue];
                    }
                    // If both are empty, keep the original meta structure from sourcePostMeta
                }
            }
        }

        //remap ACF relationship post IDs to target-language counterparts
        $sourcePostMeta = self::remapRelationshipMeta($sourcePostMeta, $targetLanguageSlug);

        // Translate block content if post has blocks
        $translatedContent = $post->post_content;
        if (has_blocks($post->post_content)) {
            // Ensure translated terms exist before translating content so link rewriting
            // has a better chance of resolving term URLs to translated counterparts.
            TermDuplicator::syncPostTaxonomies(0, $sourceTaxonomies, $sourceLanguage, $targetLanguage);

            $blockStringCount = BlockContentTranslator::countTranslatableBlockStrings($post->post_content);
            self::debugLog('Duplicate post: translating block content', [
                'block_strings' => $blockStringCount,
                'source' => $sourceLanguage,
                'target' => $targetLanguage,
            ]);
            $translatedContent = BlockContentTranslator::translatePostContent(
                $post->post_content,
                $sourceLanguage,
                $targetLanguage
            );

            // If block translation failed, abort duplication for this language so the UI can show a proper error.
            $blockTranslationError = BlockContentTranslator::getLastError();
            if ($blockTranslationError) {
                self::setLastError($blockTranslationError);
                return false;
            }

            //debug: confirm nectar-blocks/text attrs are actually updated in the serialized content (recursive)
            $translatedBlocks = parse_blocks($translatedContent);
            $nectarTextBlocks = [];
            $walk = function (array $blocks) use (&$walk, &$nectarTextBlocks): void {
                foreach ($blocks as $b) {
                    if (!is_array($b)) {
                        continue;
                    }
                    if (($b['blockName'] ?? null) === 'nectar-blocks/text') {
                        $nectarTextBlocks[] = $b;
                    }
                    if (!empty($b['innerBlocks']) && is_array($b['innerBlocks'])) {
                        $walk($b['innerBlocks']);
                    }
                }
            };
            $walk($translatedBlocks);
            self::debugLog('Duplicate post: block translation result snapshot', [
                'nectar_text_blocks' => count($nectarTextBlocks),
                'first_attrs_content' => isset($nectarTextBlocks[0]['attrs']['content']) ? $nectarTextBlocks[0]['attrs']['content'] : null,
                'first_attrs' => isset($nectarTextBlocks[0]['attrs']) ? array_intersect_key($nectarTextBlocks[0]['attrs'], array_flip(['blockId', 'customId', 'textElement', 'className', 'typography'])) : null,
                'serialized_snippet' => substr($translatedContent, 0, 220),
            ]);
        }

        // Update existing post or create new one
        if ($existingPostId) {
            $existingPost = get_post($existingPostId);
            if ($existingPost) {
                // Update existing post (pass translated meta with translatable fields already translated)
                $newPostId = self::updateExistingPost($existingPostId, $post, $sourceTaxonomies, $thumbnailId, $sourceLanguage, $targetLanguage, $translatedTitle, $translatedExcerpt, $sourcePostMeta);
                if ($newPostId === false && !self::getLastError()) {
                    self::setLastError(__('Failed to update existing post.', 'novi-content-translator'));
                }
                self::markNeedsNectarBlocksCssRegen((int) $newPostId, (string) $post->post_type);
                return $newPostId;
            }
        }

        // Get all post data as array
        $postData = (array) $post;
        
        // Apply translated title, excerpt, and content
        $postData['post_title'] = $translatedTitle;
        $postData['post_excerpt'] = $translatedExcerpt;
        if (BlockContentTranslator::hasLostUnicodeEscapeBackslashes($translatedContent)) {
            $translatedContent = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($translatedContent);
        }
        $postData['post_content'] = $translatedContent;
        
        // Remove fields that shouldn't be copied
        unset($postData['ID']);
        unset($postData['guid']);
        unset($postData['post_parent']); // Reset parent; we will set translated parent for hierarchical post types below
        
        // Preserve original author attribution.
        $postData['post_author'] = self::resolveTargetAuthorId((int) ($post->post_author ?? 0));
        
        // Keep original post status for new translations so Polylang can link them without status side effects
        
        //generate slug from translated source post_name (not from translated title)
        $translatedSlug = self::buildTargetPostName(
            (string) ($post->post_name ?? ''),
            (string) $sourceLanguage,
            (string) $targetLanguageSlug
        );
        if ($translatedSlug !== '') {
            $postData['post_name'] = $translatedSlug;
        } elseif (!empty($postData['post_name'])) {
            //fallback to sanitized source slug if translation yielded nothing
            $postData['post_name'] = self::sanitizePostNameSlug((string) $postData['post_name']);
        }
        
        // Prepare new post data (all fields from source post)
        $newPost = $postData;

        // Preserve hierarchy for pages: set translated parent when available, otherwise remove parent (0)
        $newPost['post_parent'] = self::resolveTargetParentId($post, $targetLanguageSlug);

        // Insert new post, preferring Polylang helper when available so language is set at insert time.
        // Important: WordPress slug uniqueness is global per post_type; Polylang allows same slugs across
        // languages. So we must avoid pre-uniquifying (which causes "-2") and let Polylang/WP insert with
        // language context. We also add a narrow filter during the insert to keep the base slug when the
        // only collision is with a different language.
        if (function_exists('pll_insert_post')) {
            self::$currentSlugInsertLang = (string) $targetLanguageSlug;
            if (function_exists('add_filter') && function_exists('remove_filter')) {
                add_filter('wp_unique_post_slug', [self::class, 'allowDuplicateSlugAcrossLanguages'], 10, 6);
            }
            $newPostId = pll_insert_post(wp_slash($newPost), $targetLanguageSlug);
            if (function_exists('remove_filter')) {
                remove_filter('wp_unique_post_slug', [self::class, 'allowDuplicateSlugAcrossLanguages'], 10);
            }
            self::$currentSlugInsertLang = '';
        } else {
            // Non-Polylang environments: enforce uniqueness.
            if (!empty($newPost['post_name']) && function_exists('wp_unique_post_slug')) {
                $newPost['post_name'] = wp_unique_post_slug((string) $newPost['post_name'], 0, (string) $newPost['post_status'], (string) $newPost['post_type'], 0);
            }
            $newPostId = wp_insert_post(wp_slash($newPost), true);
        }

        if (is_wp_error($newPostId) || $newPostId === 0) {
            $errorMessage = is_wp_error($newPostId) ? $newPostId->get_error_message() : __('Failed to create post.', 'novi-content-translator');
            self::setLastError($errorMessage);
            return false;
        }

        //debug: confirm what was actually persisted
        $saved = get_post($newPostId);
        if ($saved instanceof \WP_Post) {
            self::debugLog('Duplicate post: saved post_content snapshot', [
                'new_post_id' => $newPostId,
                'has_blocks' => has_blocks($saved->post_content),
                'snippet' => substr((string) $saved->post_content, 0, 220),
            ]);
        }
        if (!self::ensurePersistedBlockIntegrity((int) $newPostId, $translatedContent)) {
            return false;
        }

        // Ensure Polylang language is set for the new post (safety net when pll_insert_post is not available)
        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($newPostId, $targetLanguageSlug);
        }

        // Link source + target in Polylang translation group (pll_insert_post does NOT connect translations).
        self::linkPostTranslationsInPolylang($sourcePostId, (int) $newPostId, (string) $sourceLanguage, (string) $targetLanguageSlug);

        // Copy post meta (using pre-fetched meta from source)
        self::copyPostMeta($newPostId, $sourcePostMeta);

        // Popup Maker: normalize cookie names / reset analytics on the twin
        if ((string) $post->post_type === 'popup') {
            self::normalizePopupMakerMeta((int) $newPostId, (int) $sourcePostId);
        }

        // Sync taxonomy terms (disconnect+reconnect, translate terms when configured in Polylang)
        TermDuplicator::syncPostTaxonomies($newPostId, $sourceTaxonomies, $sourceLanguage, $targetLanguage);

        // Set featured image if it exists
        if ($thumbnailId) {
            update_post_meta($newPostId, '_thumbnail_id', $thumbnailId);
        }

        self::markNeedsNectarBlocksCssRegen((int) $newPostId, (string) $post->post_type);

        return $newPostId;
    }

    /**
     * Filter callback for wp_unique_post_slug.
     * If the slug collision is only with posts in other languages, keep the original slug.
     *
     * @param string $slug
     * @param int $postId
     * @param string $postStatus
     * @param string $postType
     * @param int $postParent
     * @param string $originalSlug
     * @return string
     */
    public static function allowDuplicateSlugAcrossLanguages(string $slug, int $postId, string $postStatus, string $postType, int $postParent, string $originalSlug): string
    {
        $lang = trim((string) self::$currentSlugInsertLang);
        if ($lang === '' || !function_exists('pll_get_post_language')) {
            return $slug;
        }

        // If WP already changed it to something else, we might be in a recursive call; don't fight that.
        $originalSlug = trim((string) $originalSlug);
        if ($originalSlug === '') {
            return $slug;
        }

        $existingId = 0;
        if (function_exists('get_page_by_path')) {
            $outType = defined('OBJECT') ? OBJECT : 'OBJECT';
            $existing = get_page_by_path($originalSlug, $outType, $postType);
            if ($existing instanceof \WP_Post) {
                $existingId = (int) $existing->ID;
            }
        }
        if ($existingId <= 0) {
            return $slug;
        }

        $existingLang = (string) pll_get_post_language($existingId, 'slug');
        if ($existingLang !== '' && $existingLang !== $lang) {
            return $originalSlug;
        }

        return $slug;
    }

    /**
     * Find an unlinked publish post in the target language with the same title
     * (and preferably same slug) so we attach instead of creating a duplicate.
     */
    public static function findOrphanTranslationByTitle(object $sourcePost, string $targetLanguageSlug): int
    {
        $targetLanguageSlug = trim($targetLanguageSlug);
        $title = trim((string) ($sourcePost->post_title ?? ''));
        $postType = (string) ($sourcePost->post_type ?? '');
        if ($targetLanguageSlug === '' || $title === '' || $postType === '' || !function_exists('pll_get_post_language')) {
            return 0;
        }

        $candidates = [];
        if (function_exists('get_posts')) {
            $found = get_posts([
                'post_type' => $postType,
                'post_status' => 'publish',
                'numberposts' => 50,
                'orderby' => 'ID',
                'order' => 'ASC',
                'lang' => $targetLanguageSlug,
            ]);
            if (is_array($found)) {
                $candidates = $found;
            }
        } elseif (class_exists('\\WP_Query')) {
            $query = new \WP_Query([
                'post_type' => $postType,
                'post_status' => ['publish'],
                'posts_per_page' => 50,
                'orderby' => 'ID',
                'order' => 'ASC',
                'lang' => $targetLanguageSlug,
                'suppress_filters' => false,
            ]);
            $candidates = is_array($query->posts) ? $query->posts : [];
        }

        $sourceSlug = trim((string) ($sourcePost->post_name ?? ''));
        $exactTitle = [];
        foreach ($candidates as $candidate) {
            if (!($candidate instanceof \WP_Post) && !is_object($candidate)) {
                continue;
            }
            $candId = (int) ($candidate->ID ?? 0);
            if ($candId <= 0) {
                continue;
            }
            $candLang = (string) pll_get_post_language($candId, 'slug');
            if ($candLang !== $targetLanguageSlug) {
                continue;
            }
            if (strcasecmp(trim((string) ($candidate->post_title ?? '')), $title) !== 0) {
                continue;
            }
            //skip if already linked to another source language group that isn't empty
            if (function_exists('pll_get_post_translations')) {
                $map = (array) pll_get_post_translations($candId);
                $otherLangs = 0;
                foreach ($map as $lang => $id) {
                    if ((string) $lang === $targetLanguageSlug) {
                        continue;
                    }
                    if ((int) $id > 0) {
                        $otherLangs++;
                    }
                }
                //orphan = only itself (or empty map)
                if ($otherLangs > 0) {
                    continue;
                }
            }
            $exactTitle[] = $candidate;
        }

        if ($exactTitle === []) {
            return 0;
        }

        //prefer matching slug
        if ($sourceSlug !== '') {
            foreach ($exactTitle as $candidate) {
                if ((string) $candidate->post_name === $sourceSlug) {
                    return (int) $candidate->ID;
                }
            }
        }

        return (int) $exactTitle[0]->ID;
    }

    /**
     * Ensure the source and new target post are connected in Polylang translations.
     * Must be merge-safe (bulk runs across multiple languages should never blow away existing translation maps).
     */
    private static function linkPostTranslationsInPolylang(int $sourcePostId, int $targetPostId, string $sourceLangSlug, string $targetLangSlug): void
    {
        if (
            $sourcePostId <= 0 ||
            $targetPostId <= 0 ||
            trim($sourceLangSlug) === '' ||
            trim($targetLangSlug) === '' ||
            !function_exists('pll_get_post_translations') ||
            !function_exists('pll_save_post_translations')
        ) {
            return;
        }

        $sourceLangSlug = trim($sourceLangSlug);
        $targetLangSlug = trim($targetLangSlug);

        // Start from existing map (by source ID).
        $translations = (array) pll_get_post_translations($sourcePostId);
        $normalized = [];
        foreach ($translations as $lang => $id) {
            $lang = is_string($lang) ? trim($lang) : '';
            $id = is_numeric($id) ? (int) $id : 0;
            if ($lang === '' || $id <= 0) {
                continue;
            }
            $normalized[$lang] = $id;
        }

        // Ensure source and target are present.
        $normalized[$sourceLangSlug] = (int) $sourcePostId;
        $normalized[$targetLangSlug] = (int) $targetPostId;

        // Merge with any pre-existing group attached to the target post (safety).
        $targetExisting = (array) pll_get_post_translations($targetPostId);
        foreach ($targetExisting as $lang => $id) {
            $lang = is_string($lang) ? trim($lang) : '';
            $id = is_numeric($id) ? (int) $id : 0;
            if ($lang === '' || $id <= 0) {
                continue;
            }
            if (!isset($normalized[$lang])) {
                $normalized[$lang] = $id;
            }
        }

        pll_save_post_translations($normalized);
    }

    private static function markNeedsNectarBlocksCssRegen(int $postId, string $postType): void
    {
        if ($postId <= 0) {
            return;
        }
        $type = trim((string) $postType);
        if ($type === 'wp_block' || $type === 'nectar_sections') {
            update_post_meta($postId, ContentTranslatorComponent::NB_CSS_REGEN_META_KEY, true);
        }
    }

    /**
     * Copy post meta from source to target
     * @param int $newPostId
     * @param array $sourcePostMeta Array of meta_key => [meta_values]
     * @return void
     */
    protected static function copyPostMeta(int $newPostId, array $sourcePostMeta): void
    {
        if (empty($sourcePostMeta)) {
            return;
        }

        foreach ($sourcePostMeta as $metaKey => $metaValues) {
            // Delete existing meta first (important for non-unique keys)
            delete_post_meta($newPostId, $metaKey);

            foreach ($metaValues as $metaValue) {
                $slashedValue = self::recursivelySlashStrings($metaValue);
                add_post_meta($newPostId, $metaKey, $slashedValue);
            }
        }
    }

    /**
     * Copy taxonomy terms from source to target
     * @param int $newPostId
     * @param array $sourceTaxonomies Array of taxonomy => [term slugs]
     * @return void
     */
    protected static function copyPostTaxonomies(int $newPostId, array $sourceTaxonomies): void
    {
        //legacy wrapper: term syncing moved to TermDuplicator
        TermDuplicator::syncPostTaxonomies($newPostId, $sourceTaxonomies, '', '');
    }

    /**
     * Update existing post with new content
     * @param int $existingPostId
     * @param \WP_Post $sourcePost
     * @param array $sourceTaxonomies
     * @param int|null $thumbnailId
     * @param string $translatedTitle
     * @param string $translatedExcerpt
     * @param array $translatedPostMeta Already translated post meta (including Yoast fields)
     * @return int|false
     */
    protected static function updateExistingPost(int $existingPostId, \WP_Post $sourcePost, array $sourceTaxonomies, ?int $thumbnailId, string $sourceLanguage, string $targetLanguage, string $translatedTitle = '', string $translatedExcerpt = '', array $translatedPostMeta = []): int|false
    {
        // if no translated title was passed, still apply prefix policy for certain post types
        if ($translatedTitle === '' && isset($sourcePost->post_type, $sourcePost->post_title)) {
            $titlePolicy = self::buildTargetTitleForPostType(
                (string) $sourcePost->post_type,
                (string) $sourcePost->post_title,
                $targetLanguage,
                (string) $sourceLanguage
            );
            if (!$titlePolicy['shouldTranslateTitle']) {
                $translatedTitle = (string) $titlePolicy['targetTitle'];
            }
        }

        // Translate block content if post has blocks
        $translatedContent = $sourcePost->post_content;
        if (has_blocks($sourcePost->post_content)) {
            // Ensure translated terms exist before translating content so link rewriting
            // has a better chance of resolving term URLs to translated counterparts.
            TermDuplicator::syncPostTaxonomies(0, $sourceTaxonomies, $sourceLanguage, $targetLanguage);

            $blockStringCount = BlockContentTranslator::countTranslatableBlockStrings($sourcePost->post_content);
            self::debugLog('Update existing post: translating block content', [
                'post_id' => $existingPostId,
                'block_strings' => $blockStringCount,
                'source' => $sourceLanguage,
                'target' => $targetLanguage,
            ]);
            $translatedContent = BlockContentTranslator::translatePostContent(
                $sourcePost->post_content,
                $sourceLanguage,
                $targetLanguage
            );

            //debug: confirm nectar-blocks/text attrs are updated before update_post (recursive)
            $translatedBlocks = parse_blocks($translatedContent);
            $nectarTextBlocks = [];
            $walk = function (array $blocks) use (&$walk, &$nectarTextBlocks): void {
                foreach ($blocks as $b) {
                    if (!is_array($b)) {
                        continue;
                    }
                    if (($b['blockName'] ?? null) === 'nectar-blocks/text') {
                        $nectarTextBlocks[] = $b;
                    }
                    if (!empty($b['innerBlocks']) && is_array($b['innerBlocks'])) {
                        $walk($b['innerBlocks']);
                    }
                }
            };
            $walk($translatedBlocks);
            self::debugLog('Update existing post: block translation result snapshot', [
                'nectar_text_blocks' => count($nectarTextBlocks),
                'first_attrs_content' => isset($nectarTextBlocks[0]['attrs']['content']) ? $nectarTextBlocks[0]['attrs']['content'] : null,
                'first_attrs' => isset($nectarTextBlocks[0]['attrs']) ? array_intersect_key($nectarTextBlocks[0]['attrs'], array_flip(['blockId', 'customId', 'textElement', 'className', 'typography'])) : null,
                'serialized_snippet' => substr($translatedContent, 0, 220),
            ]);
        }

        // Get all post data as array
        $postData = (array) $sourcePost;
        
        // Set ID for update
        $postData['ID'] = $existingPostId;
        
        // Apply translated title, excerpt, and content if provided
        if (!empty($translatedTitle)) {
            $postData['post_title'] = $translatedTitle;
        }
        if (!empty($translatedExcerpt)) {
            $postData['post_excerpt'] = $translatedExcerpt;
        }
        if (BlockContentTranslator::hasLostUnicodeEscapeBackslashes($translatedContent)) {
            $translatedContent = BlockContentTranslator::repairLostUnicodeEscapeBackslashes($translatedContent);
        }
        $postData['post_content'] = $translatedContent;
        
        // Remove fields that shouldn't be updated
        unset($postData['guid']);
        unset($postData['post_parent']); // We'll explicitly set translated parent for hierarchical types below
        
        // Preserve original author attribution.
        $postData['post_author'] = self::resolveTargetAuthorId((int) ($sourcePost->post_author ?? 0));
        
        // Keep existing post status when updating translations
        
        //generate slug from translated source post_name (not from translated title)
        $translatedSlug = self::buildTargetPostName(
            (string) ($sourcePost->post_name ?? ''),
            (string) $sourceLanguage,
            (string) $targetLanguage
        );
        if ($translatedSlug !== '') {
            $postData['post_name'] = $translatedSlug;
        }
        
        // Prepare post data for update (all fields from source post)
        $updatePost = $postData;

        // Preserve/correct hierarchy for pages: set translated parent when available, otherwise remove parent (0)
        $updatePost['post_parent'] = self::resolveTargetParentId($sourcePost, $targetLanguage);

        // Update the post. When Polylang is present, allow duplicate slugs across languages.
        if (function_exists('pll_get_post_language')) {
            self::$currentSlugInsertLang = (string) $targetLanguage;
            if (function_exists('add_filter') && function_exists('remove_filter')) {
                add_filter('wp_unique_post_slug', [self::class, 'allowDuplicateSlugAcrossLanguages'], 10, 6);
            }
        }
        $updatedPostId = wp_update_post(wp_slash($updatePost), true);
        if (function_exists('remove_filter')) {
            remove_filter('wp_unique_post_slug', [self::class, 'allowDuplicateSlugAcrossLanguages'], 10);
        }
        self::$currentSlugInsertLang = '';

        if (is_wp_error($updatedPostId) || $updatedPostId === 0) {
            return false;
        }

        //debug: confirm persisted content
        $saved = get_post($updatedPostId);
        if ($saved instanceof \WP_Post) {
            self::debugLog('Update existing post: saved post_content snapshot', [
                'post_id' => $updatedPostId,
                'has_blocks' => has_blocks($saved->post_content),
                'snippet' => substr((string) $saved->post_content, 0, 220),
            ]);
        }
        if (!self::ensurePersistedBlockIntegrity((int) $updatedPostId, $translatedContent)) {
            return false;
        }

        // Copy post meta (using pre-translated meta passed as parameter)
        self::copyPostMeta($updatedPostId, $translatedPostMeta);

        // Popup Maker: normalize cookie names / reset analytics on the twin
        if (isset($sourcePost->post_type) && (string) $sourcePost->post_type === 'popup') {
            self::normalizePopupMakerMeta((int) $updatedPostId, (int) $sourcePost->ID);
        }

        // Sync taxonomy terms (disconnect+reconnect, translate terms when configured in Polylang)
        TermDuplicator::syncPostTaxonomies($updatedPostId, $sourceTaxonomies, $sourceLanguage, $targetLanguage);

        // Set featured image if it exists
        // Use update_post_meta directly instead of set_post_thumbnail() to avoid
        // validation issues with network media library (attachments may be on different site)
        if ($thumbnailId) {
            // Ensure _thumbnail_id is set in meta (copyPostMeta should have done this, but ensure it)
            update_post_meta($updatedPostId, '_thumbnail_id', $thumbnailId);
        }

        return $updatedPostId;
    }

    /**
     * After copying Popup Maker popup meta, rewrite pum-{sourceId} cookie names to the twin id
     * and reset open-count analytics so the EN popup starts clean.
     */
    protected static function normalizePopupMakerMeta(int $newPostId, int $sourcePostId): void
    {
        if ($newPostId <= 0 || $sourcePostId <= 0) {
            return;
        }

        //reset analytics counters copied from the source popup
        $metaKeys = function_exists('get_post_custom_keys') ? get_post_custom_keys($newPostId) : [];
        if (is_array($metaKeys)) {
            foreach ($metaKeys as $metaKey) {
                $metaKey = (string) $metaKey;
                if ($metaKey === 'popup_last_opened' || str_starts_with($metaKey, 'popup_open_count')) {
                    delete_post_meta($newPostId, $metaKey);
                }
            }
        }

        $settings = get_post_meta($newPostId, 'popup_settings', true);
        if (!is_array($settings) || $settings === []) {
            return;
        }

        $rewritten = self::rewritePumIdReferences($settings, $sourcePostId, $newPostId);
        if ($rewritten !== $settings) {
            update_post_meta($newPostId, 'popup_settings', $rewritten);
        }
    }

    /**
     * Recursively replace pum-{oldId} string tokens with pum-{newId} inside popup_settings.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function rewritePumIdReferences($value, int $oldId, int $newId)
    {
        if ($oldId <= 0 || $newId <= 0 || $oldId === $newId) {
            return $value;
        }

        $from = 'pum-' . $oldId;
        $to = 'pum-' . $newId;

        if (is_string($value)) {
            return str_replace($from, $to, $value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $value[$key] = self::rewritePumIdReferences($child, $oldId, $newId);
            }
        }

        return $value;
    }

    /**
     * Recursively add slashes only to strings (not arrays/objects)
     * Based on duplicate-post plugin pattern
     * @param mixed $value
     * @return mixed
     */
    protected static function recursivelySlashStrings($value)
    {
        if (function_exists('map_deep')) {
            return map_deep($value, function ($item) {
                return is_string($item) ? addslashes($item) : $item;
            });
        }

        // Fallback for older WordPress versions
        return wp_slash($value);
    }
}

