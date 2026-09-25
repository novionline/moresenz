<?php

namespace NoviOnline\ContentTranslator\Core;

class TermDuplicator
{
    private const SOURCE_TERM_META_PREFIX = '_nct_source_term_';
    private const SOURCE_TERM_ID_META_KEY = self::SOURCE_TERM_META_PREFIX . 'id';
    private const SOURCE_TERM_TAX_META_KEY = self::SOURCE_TERM_META_PREFIX . 'tax';
    private const SOURCE_TERM_LANG_META_KEY = self::SOURCE_TERM_META_PREFIX . 'lang';

    /**
     * Term meta keys that should be translated.
     * @var array<int, string>
     */
    private static array $translatableTermMetaKeys = [
        'wpseo_title',
        'wpseo_desc',
        'wpseo_focuskw',
        'wpseo_metadesc',
        'wpseo_opengraph-title',
        'wpseo_opengraph-description',
        'wpseo_twitter-title',
        'wpseo_twitter-description',
    ];

    /**
     * Cache translated slugs per request.
     * @var array<string, string>
     */
    private static array $translatedSlugCache = [];

    /**
     * Cache translated plain texts per request.
     * @var array<string, string>
     */
    private static array $translatedTextCache = [];

    protected static function debugLog(string $message, array $context = []): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        $suffix = $context !== [] && function_exists('wp_json_encode') ? ' ' . wp_json_encode($context) : '';
        error_log('Novi content translator: ' . $message . $suffix);
    }

    /**
     * Sync taxonomy terms from a source post to a target post.
     * - Always disconnect then reconnect, to keep assignments in sync on updates.
     * - If taxonomy is Polylang translatable: ensure translated terms exist and are connected.
     * - If taxonomy is not Polylang translatable: reuse existing terms.
     *
     * @param int $targetPostId
     * @param array $sourceTaxonomies Array of taxonomy => list of term structs (or legacy slugs)
     * @param string $sourceLang
     * @param string $targetLang
     */
    public static function syncPostTaxonomies(int $targetPostId, array $sourceTaxonomies, string $sourceLang, string $targetLang): void
    {
        if ($sourceTaxonomies === []) {
            return;
        }

        $restoreCurlang = null;
        $pll = null;
        if ($targetPostId > 0 && $targetLang !== '' && function_exists('PLL')) {
            // Polylang uses PLL()->curlang to translate terms when syncing taxonomies.
            // We set it temporarily so wp_set_object_terms assigns the correct language term IDs.
            try {
                $pll = PLL();
                if (is_object($pll) && isset($pll->curlang)) {
                    $restoreCurlang = $pll->curlang;
                }
                if (is_object($pll) && isset($pll->model) && is_object($pll->model) && method_exists($pll->model, 'get_language')) {
                    $langObj = $pll->model->get_language($targetLang);
                    if (is_object($langObj)) {
                        $pll->curlang = $langObj;
                    }
                }
            } catch (\Throwable) {
                //ignore
            }
        }

        try {
        foreach ($sourceTaxonomies as $taxonomy => $terms) {
            $taxonomy = (string) $taxonomy;
            if ($taxonomy === '') {
                continue;
            }
            if ($taxonomy === 'language' || $taxonomy === 'post_translations') {
                continue;
            }

            //disconnect first to keep terms in sync on updates
            if ($targetPostId > 0 && function_exists('wp_set_object_terms')) {
                wp_set_object_terms($targetPostId, [], $taxonomy);
            }

            $isTranslatable = function_exists('pll_is_translated_taxonomy') ? (bool) pll_is_translated_taxonomy($taxonomy) : false;

            $finalTermIds = [];
            $finalTermSlugs = [];

            foreach ((array) $terms as $t) {
                if (is_string($t)) {
                    //legacy: term slug only
                    $finalTermSlugs[] = $t;
                    continue;
                }
                if (!is_array($t)) {
                    continue;
                }

                $termId = isset($t['termId']) ? (int) $t['termId'] : 0;
                if ($termId <= 0) {
                    continue;
                }

                // Normalize term to the source language when possible.
                // This prevents mis-assigned term languages on the source post (eg ES term on EN post)
                // from breaking pll_get_term(termId, targetLang) resolution.
                if (
                    $sourceLang !== ''
                    && function_exists('pll_get_term_language')
                    && function_exists('pll_get_term')
                ) {
                    $termLang = (string) pll_get_term_language($termId, 'slug');
                    if ($termLang !== '' && $termLang !== $sourceLang) {
                        $sourceLangTermId = self::resolveCanonicalSourceTermId($termId, $sourceLang, $taxonomy);
                        if ($sourceLangTermId > 0) {
                            $termId = $sourceLangTermId;
                            $t['termId'] = $sourceLangTermId;
                        } else {
                            // Term is in a different language but we can't map it back to the source language.
                            // If it already matches the TARGET language, we can still safely assign it.
                            // This prevents shared/acronym category terms (same slug across languages) from being dropped.
                            if ($targetLang === '' || $termLang !== $targetLang) {
                                continue;
                            }
                        }
                    }
                }

                // Prefer an existing Polylang translation whenever possible, even if taxonomy translatability
                // is misreported by configuration/custom setups.
                $existingTranslatedId = 0;
                if ($targetLang !== '' && function_exists('pll_get_term')) {
                    $existingTranslatedId = (int) pll_get_term($termId, $targetLang);
                }
                if ($existingTranslatedId > 0) {
                    $finalTermIds[] = $existingTranslatedId;
                    continue;
                }

                if (!$isTranslatable) {
                    $finalTermIds[] = $termId;
                    continue;
                }

                $translatedId = self::ensureTranslatedTerm($t, $taxonomy, $sourceLang, $targetLang);
                if ($translatedId > 0) {
                    $finalTermIds[] = $translatedId;
                }
            }

            $finalTermIds = array_values(array_filter(array_unique(array_map('intval', $finalTermIds))));
            $finalTermSlugs = array_values(array_filter(array_unique(array_map('strval', $finalTermSlugs))));

            if (function_exists('wp_set_object_terms')) {
                if ($targetPostId > 0 && $finalTermIds !== []) {
                    wp_set_object_terms($targetPostId, $finalTermIds, $taxonomy);
                } elseif ($targetPostId > 0 && $finalTermSlugs !== []) {
                    wp_set_object_terms($targetPostId, $finalTermSlugs, $taxonomy);
                }
            }
        }
        } finally {
            if ($pll !== null && is_object($pll) && $restoreCurlang !== null) {
                try {
                    $pll->curlang = $restoreCurlang;
                } catch (\Throwable) {
                    //ignore
                }
            }
        }
    }

    /**
     * Resolve a term ID in a desired language as robustly as possible.
     * Used to protect against mis-assigned term languages on posts.
     *
     * @param int $termId Any term ID in the translation group.
     * @param string $sourceLangSlug Desired language slug (e.g. en).
     */
    public static function resolveCanonicalSourceTermId(int $termId, string $sourceLangSlug, string $taxonomy = ''): int
    {
        $termId = (int) $termId;
        $sourceLangSlug = trim((string) $sourceLangSlug);
        if ($termId <= 0 || $sourceLangSlug === '' || !function_exists('pll_get_term')) {
            return 0;
        }

        $direct = (int) pll_get_term($termId, $sourceLangSlug);
        if ($direct > 0) {
            return $direct;
        }

        // Fallback: walk the translation map and try to map via another language.
        $map = [];
        if (function_exists('pll_get_term_translations')) {
            $map = (array) pll_get_term_translations($termId);
        }
        foreach ($map as $lang => $id) {
            $id = is_numeric($id) ? (int) $id : 0;
            if ($id <= 0) {
                continue;
            }
            $candidate = (int) pll_get_term($id, $sourceLangSlug);
            if ($candidate > 0) {
                return $candidate;
            }
        }

        // Last resort: scan taxonomy by slug for a term in the desired language.
        if (
            $taxonomy !== ''
            && function_exists('get_term')
            && function_exists('get_terms')
            && function_exists('pll_get_term_language')
        ) {
            $obj = get_term($termId, $taxonomy);
            $slug = is_object($obj) ? (string) ($obj->slug ?? '') : '';
            if ($slug !== '') {
                $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                if (is_array($terms)) {
                    foreach ($terms as $t) {
                        if (!is_object($t) || !isset($t->term_id)) {
                            continue;
                        }
                        if ((string) ($t->slug ?? '') !== $slug) {
                            continue;
                        }
                        $lang = (string) pll_get_term_language((int) $t->term_id, 'slug');
                        if ($lang === $sourceLangSlug) {
                            return (int) $t->term_id;
                        }
                    }
                }
            }
        }

        // Final fallback (works even when taxonomy isn't registered, e.g. WP-CLI --skip-themes):
        // query wp_terms/wp_term_taxonomy for same taxonomy+slug and pick the one in desired language.
        if (
            $taxonomy !== ''
            && function_exists('pll_get_term_language')
            && isset($GLOBALS['wpdb'])
            && is_object($GLOBALS['wpdb'])
        ) {
            try {
                /** @var \wpdb $wpdb */
                $wpdb = $GLOBALS['wpdb'];
                $row = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT t.slug AS slug FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE t.term_id=%d AND tt.taxonomy=%s LIMIT 1",
                        $termId,
                        $taxonomy
                    ),
                    ARRAY_A
                );
                $slug = is_array($row) ? (string) ($row['slug'] ?? '') : '';
                if ($slug !== '') {
                    $ids = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy=%s AND t.slug=%s",
                            $taxonomy,
                            $slug
                        )
                    );
                    if (is_array($ids)) {
                        foreach ($ids as $id) {
                            $id = is_numeric($id) ? (int) $id : 0;
                            if ($id <= 0) {
                                continue;
                            }
                            $lang = (string) pll_get_term_language($id, 'slug');
                            if ($lang === $sourceLangSlug) {
                                return $id;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return 0;
    }

    /**
     * Ensure a translated term exists for a given source term and taxonomy.
     * Creates or updates the term and connects it as a Polylang translation.
     *
     * @param array{termId:int,slug:string,name:string,description:string,parent:int} $sourceTerm
     * @param string $taxonomy
     * @param string $sourceLang
     * @param string $targetLang
     */
    public static function ensureTranslatedTerm(array $sourceTerm, string $taxonomy, string $sourceLang, string $targetLang): int
    {
        $sourceTermId = (int) ($sourceTerm['termId'] ?? 0);
        if ($sourceTermId <= 0 || $taxonomy === '' || $targetLang === '') {
            return 0;
        }

        // If the term reference does not point to a term in the source language, try to resolve
        // the canonical source-language term and translate from that.
        if ($sourceLang !== '') {
            $canonicalInSource = self::resolveCanonicalSourceTermId($sourceTermId, $sourceLang, $taxonomy);
            if ($canonicalInSource > 0 && $canonicalInSource !== $sourceTermId && function_exists('get_term')) {
                $obj = get_term($canonicalInSource, $taxonomy);
                if (is_object($obj) && isset($obj->term_id)) {
                    $sourceTermId = (int) $obj->term_id;
                    $sourceTerm['termId'] = $sourceTermId;
                    $sourceTerm['slug'] = (string) ($obj->slug ?? ($sourceTerm['slug'] ?? ''));
                    $sourceTerm['name'] = (string) ($obj->name ?? ($sourceTerm['name'] ?? ''));
                    $sourceTerm['description'] = (string) ($obj->description ?? ($sourceTerm['description'] ?? ''));
                    $sourceTerm['parent'] = (int) ($obj->parent ?? ($sourceTerm['parent'] ?? 0));
                }
            }
        }

        // If the source post has a term in a different language, prefer the term's actual language
        // when translating its fields.
        $effectiveSourceLang = $sourceLang;
        if ($sourceTermId > 0 && function_exists('pll_get_term_language')) {
            $termLang = (string) pll_get_term_language($sourceTermId, 'slug');
            if ($termLang !== '') {
                $effectiveSourceLang = $termLang;
            }
        }

        $targetTermId = function_exists('pll_get_term') ? (int) pll_get_term($sourceTermId, $targetLang) : 0;

        $translatedFields = self::translateTermFields($sourceTerm, $taxonomy, $effectiveSourceLang, $targetLang);
        if ($translatedFields === []) {
            return 0;
        }

        if ($targetTermId <= 0) {
            //connect existing by source-id meta (handles client renames)
            $matchedByMeta = self::findExistingTermBySourceMeta($sourceTermId, $taxonomy, $sourceLang, $targetLang);
            if ($matchedByMeta > 0) {
                $targetTermId = $matchedByMeta;
            }
        }

        if ($targetTermId <= 0) {
            //connect existing by intended slug as fallback
            if (function_exists('get_term_by')) {
                $existing = get_term_by('slug', (string) $translatedFields['slug'], $taxonomy);
                if (is_object($existing) && isset($existing->term_id)) {
                    $candidateId = (int) $existing->term_id;
                    // Only reuse an existing term by slug if it is already in the target language
                    // (or if term-language is unavailable). Otherwise we might accidentally reuse an ES/EN term
                    // and corrupt translation groups.
                    if (function_exists('pll_get_term_language')) {
                        $candLang = (string) pll_get_term_language($candidateId, 'slug');
                        if ($candLang === '' || $candLang === $targetLang) {
                            $targetTermId = $candidateId;
                        }
                    } else {
                        $targetTermId = $candidateId;
                    }
                }
            }
        }

        if ($targetTermId > 0) {
            // Never “translate onto itself” (shared slugs like apqp can resolve back to the source term).
            // Also avoid flipping the language of an existing term from another language.
            if (function_exists('pll_get_term_language')) {
                $existingLang = (string) pll_get_term_language($targetTermId, 'slug');
                if ($targetTermId === $sourceTermId) {
                    // Only allow reusing the same term when it is already in target language (or unlabeled).
                    if ($existingLang === '' || $existingLang === $targetLang) {
                        return $targetTermId;
                    }
                    // Otherwise force-create a new target term.
                    $targetTermId = 0;
                } elseif ($existingLang !== '' && $existingLang !== $targetLang) {
                    // Wrong-language existing term; force-create a new target term.
                    $targetTermId = 0;
                }
            } else {
                if ($targetTermId === $sourceTermId) {
                    return $targetTermId;
                }
            }
            if ($targetTermId > 0 && function_exists('wp_update_term')) {
                $args = [
                    'name' => (string) $translatedFields['name'],
                    'slug' => (string) $translatedFields['slug'],
                    'description' => (string) $translatedFields['description'],
                    'parent' => (int) $translatedFields['parent'],
                ];
                wp_update_term($targetTermId, $taxonomy, $args);
            }
        }

        if ($targetTermId <= 0) {
            if (function_exists('wp_insert_term')) {
                $args = [
                    'slug' => (string) $translatedFields['slug'],
                    'description' => (string) $translatedFields['description'],
                    'parent' => (int) $translatedFields['parent'],
                ];
                $inserted = wp_insert_term((string) $translatedFields['name'], $taxonomy, $args);

                // If a term already exists for the translated slug, WordPress returns `term_exists`.
                // This can happen when a site already has target-language terms created manually/imported,
                // but Polylang language/translation mapping was never connected.
                if (
                    $targetTermId <= 0
                    && function_exists('is_wp_error')
                    && is_wp_error($inserted)
                    && method_exists($inserted, 'get_error_code')
                    && (string) $inserted->get_error_code() === 'term_exists'
                ) {
                    $existingId = 0;
                    if (method_exists($inserted, 'get_error_data')) {
                        $data = $inserted->get_error_data('term_exists');
                        $existingId = is_numeric($data) ? (int) $data : 0;
                    }
                    if ($existingId <= 0 && function_exists('get_term_by')) {
                        $existing = get_term_by('slug', (string) $translatedFields['slug'], $taxonomy);
                        if (is_object($existing) && isset($existing->term_id)) {
                            $existingId = (int) $existing->term_id;
                        }
                    }

                    if ($existingId > 0) {
                        $existingLang = function_exists('pll_get_term_language') ? (string) pll_get_term_language($existingId, 'slug') : '';
                        if ($existingLang === '' || $existingLang === $targetLang) {
                            $targetTermId = $existingId;
                        } else {
                            // Heuristic: if the existing term matches the translated name for the target
                            // but is incorrectly labeled as another language, treat it as the target term.
                            // This repairs imported/manual terms without creating duplicates.
                            $obj = function_exists('get_term') ? get_term($existingId, $taxonomy) : null;
                            $existingName = is_object($obj) && isset($obj->name) ? (string) $obj->name : '';
                            $sourceName = (string) ($sourceTerm['name'] ?? '');
                            if (
                                $existingId !== $sourceTermId
                                && $existingName !== ''
                                && $existingName === (string) $translatedFields['name']
                                && $existingName !== $sourceName
                                && function_exists('pll_set_term_language')
                            ) {
                                pll_set_term_language($existingId, $targetLang);
                                $targetTermId = $existingId;
                            }
                        }
                    }
                }

                // Shared-term fallback:
                // WordPress enforces unique term slugs per taxonomy. If translation keeps the same slug (e.g. "APQP" -> "apqp"),
                // we cannot create a second term in another language. When insert fails with `term_exists`, treat it as a shared term:
                // reuse the existing term id and do NOT mutate its language/translation group.
                if (
                    $targetTermId <= 0
                    && function_exists('is_wp_error')
                    && is_wp_error($inserted)
                    && isset($sourceTerm['slug'])
                    && (string) ($translatedFields['slug'] ?? '') !== ''
                    && (string) $translatedFields['slug'] === (string) $sourceTerm['slug']
                    && method_exists($inserted, 'get_error_code')
                    && (string) $inserted->get_error_code() === 'term_exists'
                ) {
                    $existingId = 0;
                    if (method_exists($inserted, 'get_error_data')) {
                        $data = $inserted->get_error_data('term_exists');
                        $existingId = is_numeric($data) ? (int) $data : 0;
                    }
                    if ($existingId <= 0 && function_exists('get_term_by')) {
                        $shared = get_term_by('slug', (string) $translatedFields['slug'], $taxonomy);
                        if (is_object($shared) && isset($shared->term_id)) {
                            $existingId = (int) $shared->term_id;
                        }
                    }
                    if ($existingId > 0) {
                        return $existingId;
                    }
                }
                if (is_array($inserted) && isset($inserted['term_id'])) {
                    $targetTermId = (int) $inserted['term_id'];
                }
            }
        }

        if ($targetTermId <= 0) {
            return 0;
        }

        if (function_exists('pll_set_term_language')) {
            $existingLang = function_exists('pll_get_term_language') ? (string) pll_get_term_language($targetTermId, 'slug') : '';
            if ($existingLang === '' || $existingLang === $targetLang) {
                pll_set_term_language($targetTermId, $targetLang);
            }
        }

        //connect translations
        if (function_exists('pll_get_term_translations') && function_exists('pll_save_term_translations')) {
            // In bulk runs, the "source term id" we started from might not be the canonical term for the source language.
            // Always merge existing translation maps to avoid overwriting previously linked languages.
            $canonicalSourceId = $sourceTermId;
            if ($sourceLang !== '') {
                $resolved = self::resolveCanonicalSourceTermId($sourceTermId, $sourceLang, $taxonomy);
                if ($resolved > 0) {
                    $canonicalSourceId = $resolved;
                }
            }

            $map = self::mergeTermTranslationMaps([
                $sourceTermId,
                $canonicalSourceId,
                $targetTermId,
            ]);

            if ($sourceLang !== '') {
                $map[$sourceLang] = $canonicalSourceId;
            }
            if ($effectiveSourceLang !== '' && $effectiveSourceLang !== $sourceLang) {
                $map[$effectiveSourceLang] = $sourceTermId;
            }
            $map[$targetLang] = $targetTermId;

            pll_save_term_translations($map);
        }

        //translate/copy term meta
        self::copyTranslatedTermMeta($sourceTermId, $targetTermId, $effectiveSourceLang, $targetLang);

        //store a stable pointer back to the source term for future matching
        self::persistSourceTermPointerMeta($sourceTermId, $taxonomy, $effectiveSourceLang, $targetTermId);

        return $targetTermId;
    }

    private static function persistSourceTermPointerMeta(int $sourceTermId, string $taxonomy, string $sourceLang, int $targetTermId): void
    {
        if ($sourceTermId <= 0 || $targetTermId <= 0 || $taxonomy === '' || !function_exists('add_term_meta') || !function_exists('delete_term_meta')) {
            return;
        }

        //overwrite to keep it authoritative
        delete_term_meta($targetTermId, self::SOURCE_TERM_ID_META_KEY);
        delete_term_meta($targetTermId, self::SOURCE_TERM_TAX_META_KEY);
        delete_term_meta($targetTermId, self::SOURCE_TERM_LANG_META_KEY);

        add_term_meta($targetTermId, self::SOURCE_TERM_ID_META_KEY, (string) $sourceTermId);
        add_term_meta($targetTermId, self::SOURCE_TERM_TAX_META_KEY, (string) $taxonomy);
        add_term_meta($targetTermId, self::SOURCE_TERM_LANG_META_KEY, (string) $sourceLang);
    }

    private static function findExistingTermBySourceMeta(int $sourceTermId, string $taxonomy, string $sourceLang, string $targetLang): int
    {
        if (
            $sourceTermId <= 0
            || $taxonomy === ''
            || $targetLang === ''
            || !function_exists('get_terms')
            || !function_exists('get_term_meta')
        ) {
            return 0;
        }

        //scan target-language terms for this taxonomy and locate a term with our source pointer meta
        try {
            $candidates = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids',
            ]);
        } catch (\Throwable) {
            return 0;
        }

        if (!is_array($candidates)) {
            return 0;
        }

        foreach ($candidates as $candidateId) {
            $candidateId = (int) $candidateId;
            if ($candidateId <= 0) {
                continue;
            }

            //only consider terms in the target language (if polylang term-language API is available)
            if (function_exists('pll_get_term_language')) {
                $lang = (string) pll_get_term_language($candidateId, 'slug');
                if ($lang !== '' && $lang !== $targetLang) {
                    continue;
                }
            }

            $metaSourceId = (string) get_term_meta($candidateId, self::SOURCE_TERM_ID_META_KEY, true);
            $metaTax = (string) get_term_meta($candidateId, self::SOURCE_TERM_TAX_META_KEY, true);
            $metaLang = (string) get_term_meta($candidateId, self::SOURCE_TERM_LANG_META_KEY, true);

            if ((int) $metaSourceId === $sourceTermId && $metaTax === $taxonomy && ($metaLang === '' || $sourceLang === '' || $metaLang === $sourceLang)) {
                return $candidateId;
            }
        }

        return 0;
    }

    /**
     * Merge translation maps for multiple terms into one map.
     * @param array<int, int> $termIds
     * @return array<string, int>
     */
    private static function mergeTermTranslationMaps(array $termIds): array
    {
        $out = [];
        foreach ($termIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $map = pll_get_term_translations($id);
            if (!is_array($map)) {
                continue;
            }
            foreach ($map as $lang => $termId) {
                $lang = (string) $lang;
                $termId = (int) $termId;
                if ($lang === '' || $termId <= 0) {
                    continue;
                }
                $out[$lang] = $termId;
            }
        }
        return $out;
    }

    /**
     * @return array{name:string,slug:string,description:string,parent:int}
     */
    private static function translateTermFields(array $sourceTerm, string $taxonomy, string $sourceLang, string $targetLang): array
    {
        $name = (string) ($sourceTerm['name'] ?? '');
        $slug = (string) ($sourceTerm['slug'] ?? '');
        $description = (string) ($sourceTerm['description'] ?? '');
        $parent = (int) ($sourceTerm['parent'] ?? 0);

        $translatedName = self::translatePlainCached($name, $sourceLang, $targetLang);
        $translatedDescription = $description !== '' ? self::translatePlainCached($description, $sourceLang, $targetLang) : '';
        $translatedSlug = self::translateSlugCached($slug, $sourceLang, $targetLang);

        $translatedParent = 0;
        if ($parent > 0 && function_exists('is_taxonomy_hierarchical') && is_taxonomy_hierarchical($taxonomy)) {
            $translatedParent = function_exists('pll_get_term') ? (int) pll_get_term($parent, $targetLang) : 0;
            if ($translatedParent <= 0 && function_exists('get_term')) {
                $parentObj = get_term($parent, $taxonomy);
                if (is_object($parentObj) && isset($parentObj->term_id, $parentObj->slug)) {
                    $translatedParent = self::ensureTranslatedTerm(
                        [
                            'termId' => (int) $parentObj->term_id,
                            'slug' => (string) ($parentObj->slug ?? ''),
                            'name' => (string) ($parentObj->name ?? ''),
                            'description' => (string) ($parentObj->description ?? ''),
                            'parent' => (int) ($parentObj->parent ?? 0),
                        ],
                        $taxonomy,
                        $sourceLang,
                        $targetLang
                    );
                }
            }
        }

        return [
            'name' => $translatedName !== '' ? $translatedName : $name,
            'slug' => $translatedSlug !== '' ? $translatedSlug : $slug,
            'description' => $translatedDescription,
            'parent' => $translatedParent > 0 ? $translatedParent : 0,
        ];
    }

    private static function translatePlainCached(string $text, string $sourceLang, string $targetLang): string
    {
        $text = trim($text);
        if ($text === '' || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return $text;
        }

        $key = strtolower($sourceLang) . '|' . strtolower($targetLang) . '|' . $text;
        if (isset(self::$translatedTextCache[$key])) {
            return self::$translatedTextCache[$key];
        }

        $out = $text;
        if (class_exists('\NoviOnline\ContentTranslator\Core\DeepLTranslator')) {
            $out = (string) DeepLTranslator::translateText($text, $sourceLang, $targetLang, ['context' => 'plain']);
        }
        self::$translatedTextCache[$key] = $out;
        return $out;
    }

    private static function translateSlugCached(string $slug, string $sourceLang, string $targetLang): string
    {
        $slug = trim($slug);
        if ($slug === '' || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return $slug;
        }

        $key = strtolower($sourceLang) . '|' . strtolower($targetLang) . '|' . $slug;
        if (isset(self::$translatedSlugCache[$key])) {
            return self::$translatedSlugCache[$key];
        }

        $human = str_replace(['-', '_'], ' ', $slug);
        $translated = self::translatePlainCached($human, $sourceLang, $targetLang);
        $out = function_exists('sanitize_title') ? (string) sanitize_title($translated) : self::slugifyFallback($translated);
        if ($out === '') {
            $out = $slug;
        }

        self::$translatedSlugCache[$key] = $out;
        return $out;
    }

    private static function slugifyFallback(string $text): string
    {
        $lower = function_exists('mb_strtolower') ? (string) mb_strtolower($text) : strtolower($text);
        $text = trim($lower);
        if ($text === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $text = (string) remove_accents($text);
        } elseif (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        $text = preg_replace('/[^a-z0-9\\s\\-]/', '', $text);
        $text = preg_replace('/[\\s\\-]+/', '-', (string) $text);
        $text = trim((string) $text, '-');
        return (string) $text;
    }

    public static function getTranslatableTermMetaKeys(): array
    {
        $metaKeys = apply_filters('nct_translatable_term_meta_keys', self::$translatableTermMetaKeys);
        if (!is_array($metaKeys)) {
            return self::$translatableTermMetaKeys;
        }

        $normalized = array_filter(
            array_map('strval', $metaKeys),
            static function ($v) {
                return $v !== '';
            }
        );

        return array_values($normalized);
    }

    private static function copyTranslatedTermMeta(int $sourceTermId, int $targetTermId, string $sourceLang, string $targetLang): void
    {
        if ($sourceTermId <= 0 || $targetTermId <= 0 || !function_exists('get_term_meta') || !function_exists('add_term_meta') || !function_exists('delete_term_meta')) {
            return;
        }

        $metaKeys = self::getTranslatableTermMetaKeys();
        if ($metaKeys === []) {
            return;
        }

        foreach ($metaKeys as $metaKey) {
            $values = get_term_meta($sourceTermId, $metaKey, false);
            if (!is_array($values) || $values === []) {
                continue;
            }

            delete_term_meta($targetTermId, $metaKey);

            foreach ($values as $value) {
                if (is_string($value)) {
                    $value = self::translatePlainCached($value, $sourceLang, $targetLang);
                }
                add_term_meta($targetTermId, $metaKey, $value);
            }
        }
    }
}

