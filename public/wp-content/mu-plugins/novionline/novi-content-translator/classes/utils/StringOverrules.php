<?php

namespace NoviOnline\ContentTranslator\Core;

/**
 * Don't-translate / terminology words for DeepL (brand names, forced glossary).
 *
 * Protects matching phrases with translate="no" spans before DeepL, then unwraps
 * and restores either the source (keep-as-is) or an optional per-language target
 * (glossary), adapting casing to the matched occurrence.
 */
final class StringOverrules
{
    private const SETTINGS_OPTION_KEY = 'novi_content_translator_settings';
    private const OPTION_KEY = 'dont_translate_words';
    private const LEGACY_OPTION_KEY = 'string_overrules';
    private const PROTECT_ATTR = 'data-nct-ov';
    // DeepL often wraps translate="no" spans in these quotation marks
    private const QUOTE_CHARS = '"\'“”„«»‘’';
    private const AUTO_TITLES_TRANSIENT_PREFIX = 'nct_auto_dt_titles_';
    private const AUTO_TITLES_TRANSIENT_TTL = 300;
    //single-token auto titles shorter than this are skipped (Plus etc.)
    private const AUTO_MIN_SINGLE_TOKEN_LENGTH = 4;

    /** @var array<string, array<int, array<string, mixed>>>|null */
    private static $rulesOverride = null;

    /** @var array<string, array<int, string>>|null null = live query; array = test override */
    private static $autoTitlesOverride = null;

    /** @var array<string, array<int, string>> request-level cache of auto titles by source lang */
    private static $autoTitlesRequestCache = [];

    /**
     * Allow unit tests to inject rules without WordPress options.
     *
     * @param array<string, array<int, array<string, mixed>>>|null $rules
     */
    public static function setRulesOverride(?array $rules): void
    {
        self::$rulesOverride = $rules;
    }

    /**
     * Allow unit tests to inject auto titles (keyed by source language slug).
     * Pass null to use live collection again.
     *
     * @param array<string, array<int, string>>|null $titlesByLang
     */
    public static function setAutoTitlesOverride(?array $titlesByLang): void
    {
        self::$autoTitlesOverride = $titlesByLang;
        self::$autoTitlesRequestCache = [];
    }

    /**
     * Clear request + transient caches for auto don’t-translate titles.
     */
    public static function clearAutoTitlesCache(?string $sourceLang = null): void
    {
        if ($sourceLang === null || $sourceLang === '') {
            self::$autoTitlesRequestCache = [];
            return;
        }
        $sourceLang = strtolower(trim($sourceLang));
        unset(self::$autoTitlesRequestCache[$sourceLang]);
        if (function_exists('delete_transient')) {
            \delete_transient(self::AUTO_TITLES_TRANSIENT_PREFIX . $sourceLang);
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function getAllRules(): array
    {
        if (self::$rulesOverride !== null) {
            return self::$rulesOverride;
        }

        if (!function_exists('get_option')) {
            return [];
        }

        $raw = (array) \get_option(self::SETTINGS_OPTION_KEY, []);
        if (isset($raw[self::OPTION_KEY]) && is_array($raw[self::OPTION_KEY])) {
            return self::normalizeStoredRules($raw[self::OPTION_KEY]);
        }
        //migrate legacy overrules (drop per-language targets)
        if (isset($raw[self::LEGACY_OPTION_KEY]) && is_array($raw[self::LEGACY_OPTION_KEY])) {
            return self::normalizeStoredRules($raw[self::LEGACY_OPTION_KEY]);
        }
        return [];
    }

    /**
     * Collect unique source needles for cache purge after save.
     *
     * @param array<string, array<int, array<string, mixed>>> $rules
     * @return array<int, string>
     */
    public static function collectNeedlesFromRules(array $rules): array
    {
        $needles = [];
        foreach ($rules as $ruleList) {
            if (!is_array($ruleList)) {
                continue;
            }
            foreach ($ruleList as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $source = trim((string) ($rule['source'] ?? ''));
                if ($source !== '') {
                    $needles[$source] = $source;
                }
                if (isset($rule['targets']) && is_array($rule['targets'])) {
                    foreach ($rule['targets'] as $target) {
                        $target = trim((string) $target);
                        if ($target !== '') {
                            $needles[$target] = $target;
                        }
                    }
                }
            }
        }
        return array_values($needles);
    }

    /**
     * Validate and normalize incoming UI/API payload.
     *
     * @param mixed $raw
     * @param array<int, string> $allLanguageSlugs Polylang language slugs
     * @return array{ok:bool, rules:array<string, array<int, array<string, mixed>>>, message:?string}
     */
    public static function validateAndNormalize($raw, array $allLanguageSlugs): array
    {
        if ($raw === null || $raw === []) {
            return ['ok' => true, 'rules' => [], 'message' => null];
        }
        if (!is_array($raw)) {
            return [
                'ok' => false,
                'rules' => [],
                'message' => __('Invalid don’t-translate words payload.', 'novi-content-translator'),
            ];
        }

        $allLanguageSlugs = array_values(array_filter(array_map(static function ($slug): string {
            return strtolower(trim((string) $slug));
        }, $allLanguageSlugs), static function (string $slug): bool {
            return $slug !== '';
        }));

        if ($allLanguageSlugs === []) {
            return [
                'ok' => false,
                'rules' => [],
                'message' => __('No Polylang languages available for don’t-translate words.', 'novi-content-translator'),
            ];
        }

        $normalized = [];

        foreach ($raw as $sourceLang => $ruleList) {
            $sourceLang = strtolower(trim((string) $sourceLang));
            if ($sourceLang === '' || !in_array($sourceLang, $allLanguageSlugs, true)) {
                return [
                    'ok' => false,
                    'rules' => [],
                    'message' => __('Don’t-translate words contain an unknown source language.', 'novi-content-translator'),
                ];
            }
            if (!is_array($ruleList)) {
                return [
                    'ok' => false,
                    'rules' => [],
                    'message' => __('Invalid don’t-translate words structure.', 'novi-content-translator'),
                ];
            }

            $seenSources = [];
            $normalizedRules = [];

            foreach ($ruleList as $index => $rule) {
                if (!is_array($rule)) {
                    return [
                        'ok' => false,
                        'rules' => [],
                        'message' => sprintf(
                            __('Invalid don’t-translate entry at position %d for language %s.', 'novi-content-translator'),
                            ((int) $index) + 1,
                            strtoupper($sourceLang)
                        ),
                    ];
                }

                $source = trim((string) ($rule['source'] ?? ''));
                if ($source === '') {
                    return [
                        'ok' => false,
                        'rules' => [],
                        'message' => sprintf(
                            __('Word or phrase is required for every entry (language %s).', 'novi-content-translator'),
                            strtoupper($sourceLang)
                        ),
                    ];
                }

                $sourceKey = mb_strtolower($source);
                if (isset($seenSources[$sourceKey])) {
                    return [
                        'ok' => false,
                        'rules' => [],
                        'message' => sprintf(
                            __('Duplicate word "%s" for language %s.', 'novi-content-translator'),
                            $source,
                            strtoupper($sourceLang)
                        ),
                    ];
                }
                $seenSources[$sourceKey] = true;

                $targetsResult = self::normalizeTargetsMap(
                    $rule['targets'] ?? [],
                    $allLanguageSlugs,
                    $sourceLang,
                    true
                );
                if (!$targetsResult['ok']) {
                    return [
                        'ok' => false,
                        'rules' => [],
                        'message' => $targetsResult['message'],
                    ];
                }

                $entry = [
                    'source' => $source,
                    'also_in_sentence' => array_key_exists('also_in_sentence', $rule)
                        ? (bool) $rule['also_in_sentence']
                        : true,
                    'ignore_casing' => array_key_exists('ignore_casing', $rule)
                        ? (bool) $rule['ignore_casing']
                        : true,
                ];
                if ($targetsResult['targets'] !== []) {
                    $entry['targets'] = $targetsResult['targets'];
                }
                $normalizedRules[] = $entry;
            }

            if ($normalizedRules !== []) {
                $normalized[$sourceLang] = $normalizedRules;
            }
        }

        return ['ok' => true, 'rules' => $normalized, 'message' => null];
    }

    /**
     * Normalize optional per-target-language glossary map.
     *
     * Empty targets = keep source as-is. Unknown language slugs fail when $strict.
     *
     * @param mixed $raw
     * @param array<int, string> $allLanguageSlugs
     * @return array{ok:bool, targets:array<string, string>, message:?string}
     */
    public static function normalizeTargetsMap($raw, array $allLanguageSlugs, string $sourceLang, bool $strict = false): array
    {
        if ($raw === null || $raw === [] || $raw === '') {
            return ['ok' => true, 'targets' => [], 'message' => null];
        }
        if (!is_array($raw)) {
            return [
                'ok' => false,
                'targets' => [],
                'message' => __('Invalid terminology targets payload.', 'novi-content-translator'),
            ];
        }

        $allowed = [];
        foreach ($allLanguageSlugs as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug !== '' && $slug !== $sourceLang) {
                $allowed[$slug] = true;
            }
        }

        $targets = [];
        foreach ($raw as $lang => $value) {
            $lang = strtolower(trim((string) $lang));
            if ($lang === '' || $lang === $sourceLang) {
                continue;
            }
            if (!isset($allowed[$lang])) {
                if ($strict) {
                    return [
                        'ok' => false,
                        'targets' => [],
                        'message' => sprintf(
                            __('Unknown target language "%s" in terminology overrides.', 'novi-content-translator'),
                            $lang
                        ),
                    ];
                }
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $targets[$lang] = $value;
        }

        return ['ok' => true, 'targets' => $targets, 'message' => null];
    }

    /**
     * Resolve restore string for a rule toward a target language.
     *
     * @param array<string, mixed> $rule
     */
    public static function resolveRuleTarget(array $rule, string $targetLang): string
    {
        $source = trim((string) ($rule['source'] ?? ''));
        $targetLang = strtolower(trim($targetLang));
        if ($targetLang === '' || $source === '') {
            return $source;
        }
        $targets = $rule['targets'] ?? null;
        if (!is_array($targets)) {
            return $source;
        }
        $mapped = trim((string) ($targets[$targetLang] ?? ''));
        return $mapped !== '' ? $mapped : $source;
    }

    /**
     * Look up a manual glossary target for an exact source phrase (authored casing).
     * Empty string when no matching rule or no non-empty target for $targetLang.
     * Matching is case-insensitive when the rule has ignore_casing (default true).
     */
    public static function getGlossaryTargetForSource(string $sourceText, string $sourceLang, string $targetLang): string
    {
        $sourceText = trim($sourceText);
        $sourceLang = strtolower(trim($sourceLang));
        $targetLang = strtolower(trim($targetLang));
        if ($sourceText === '' || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return '';
        }

        $all = self::getAllRules();
        $list = $all[$sourceLang] ?? [];
        if (!is_array($list)) {
            return '';
        }

        $sourceKey = function_exists('mb_strtolower')
            ? (string) mb_strtolower($sourceText)
            : strtolower($sourceText);

        foreach ($list as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $ruleSource = trim((string) ($rule['source'] ?? ''));
            if ($ruleSource === '') {
                continue;
            }
            $ignoreCasing = array_key_exists('ignore_casing', $rule)
                ? (bool) $rule['ignore_casing']
                : true;
            if ($ignoreCasing) {
                $ruleKey = function_exists('mb_strtolower')
                    ? (string) mb_strtolower($ruleSource)
                    : strtolower($ruleSource);
                if ($ruleKey !== $sourceKey) {
                    continue;
                }
            } elseif ($ruleSource !== $sourceText) {
                continue;
            }

            $targets = $rule['targets'] ?? null;
            if (!is_array($targets)) {
                return '';
            }
            return trim((string) ($targets[$targetLang] ?? ''));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function normalizeStoredRules(array $raw): array
    {
        $out = [];
        foreach ($raw as $sourceLang => $ruleList) {
            $sourceLang = strtolower(trim((string) $sourceLang));
            if ($sourceLang === '' || !is_array($ruleList)) {
                continue;
            }
            $rules = [];
            $seen = [];
            foreach ($ruleList as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $source = trim((string) ($rule['source'] ?? ''));
                if ($source === '') {
                    continue;
                }
                $sourceKey = mb_strtolower($source);
                if (isset($seen[$sourceKey])) {
                    continue;
                }
                $seen[$sourceKey] = true;
                $entry = [
                    'source' => $source,
                    'also_in_sentence' => array_key_exists('also_in_sentence', $rule)
                        ? (bool) $rule['also_in_sentence']
                        : true,
                    'ignore_casing' => array_key_exists('ignore_casing', $rule)
                        ? (bool) $rule['ignore_casing']
                        : true,
                ];
                //keep optional glossary targets (empty map omitted)
                if (isset($rule['targets']) && is_array($rule['targets'])) {
                    $targets = [];
                    foreach ($rule['targets'] as $lang => $value) {
                        $lang = strtolower(trim((string) $lang));
                        $value = trim((string) $value);
                        if ($lang === '' || $lang === $sourceLang || $value === '') {
                            continue;
                        }
                        $targets[$lang] = $value;
                    }
                    if ($targets !== []) {
                        $entry['targets'] = $targets;
                    }
                }
                $rules[] = $entry;
            }
            if ($rules !== []) {
                $out[$sourceLang] = $rules;
            }
        }
        return $out;
    }

    /**
     * Rules applicable for a source→target pair, longest source first.
     *
     * Merges manual settings with auto titles from keep-as-is post types
     * (team / project). Manual entries win on duplicate (same source, case-insensitive).
     *
     * @return array<int, array{source:string, also_in_sentence:bool, ignore_casing:bool, target:string}>
     */
    public static function getApplicableRules(string $sourceLang, string $targetLang): array
    {
        $sourceLang = strtolower(trim($sourceLang));
        $targetLang = strtolower(trim($targetLang));
        if ($sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
            return [];
        }

        $all = self::getAllRules();
        $list = $all[$sourceLang] ?? [];
        if (!is_array($list)) {
            $list = [];
        }

        $applicable = [];
        $seen = [];

        foreach ($list as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $source = trim((string) ($rule['source'] ?? ''));
            if ($source === '') {
                continue;
            }
            $sourceKey = mb_strtolower($source);
            if (isset($seen[$sourceKey])) {
                continue;
            }
            $seen[$sourceKey] = true;
            $applicable[] = [
                'source' => $source,
                'also_in_sentence' => array_key_exists('also_in_sentence', $rule) ? (bool) $rule['also_in_sentence'] : true,
                'ignore_casing' => array_key_exists('ignore_casing', $rule) ? (bool) $rule['ignore_casing'] : true,
                //glossary target when set; otherwise keep source (don’t-translate)
                'target' => self::resolveRuleTarget($rule, $targetLang),
            ];
        }

        foreach (self::getAutoDontTranslateTitles($sourceLang) as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $sourceKey = mb_strtolower($title);
            if (isset($seen[$sourceKey])) {
                continue;
            }
            $seen[$sourceKey] = true;
            $applicable[] = [
                'source' => $title,
                'also_in_sentence' => true,
                'ignore_casing' => true,
                'target' => $title,
            ];
        }

        if ($applicable === []) {
            return [];
        }

        usort($applicable, static function (array $a, array $b): int {
            return mb_strlen($b['source']) <=> mb_strlen($a['source']);
        });

        return $applicable;
    }

    /**
     * Post types whose published titles are auto-added to don’t-translate.
     * Defaults to keep-as-is types (team, project). Filter: nct_auto_dont_translate_post_types
     *
     * @return array<int, string>
     */
    public static function getAutoDontTranslatePostTypes(): array
    {
        $defaults = class_exists(PostDuplicator::class)
            ? PostDuplicator::getTitleKeepAsIsPostTypes()
            : ['team', 'project'];

        $postTypes = apply_filters('nct_auto_dont_translate_post_types', $defaults);
        if (!is_array($postTypes)) {
            $postTypes = $defaults;
        }

        return array_values(array_filter(array_map('strval', $postTypes), static function ($v) {
            return $v !== '';
        }));
    }

    /**
     * Collect auto don’t-translate titles for a source language (runtime only; not stored in settings).
     *
     * @return array<int, string>
     */
    public static function getAutoDontTranslateTitles(string $sourceLang): array
    {
        $sourceLang = strtolower(trim($sourceLang));
        if ($sourceLang === '') {
            return [];
        }

        if (self::$autoTitlesOverride !== null) {
            $override = self::$autoTitlesOverride[$sourceLang] ?? [];
            return is_array($override) ? array_values(array_filter(array_map('strval', $override))) : [];
        }

        if (isset(self::$autoTitlesRequestCache[$sourceLang])) {
            return self::$autoTitlesRequestCache[$sourceLang];
        }

        $transientKey = self::AUTO_TITLES_TRANSIENT_PREFIX . $sourceLang;
        if (function_exists('get_transient')) {
            $cached = \get_transient($transientKey);
            if (is_array($cached)) {
                $titles = array_values(array_filter(array_map('strval', $cached)));
                self::$autoTitlesRequestCache[$sourceLang] = $titles;
                return $titles;
            }
        }

        $titles = self::collectAutoDontTranslateTitles($sourceLang);
        $titles = apply_filters('nct_auto_dont_translate_titles', $titles, $sourceLang);
        if (!is_array($titles)) {
            $titles = [];
        }
        $titles = array_values(array_unique(array_filter(array_map(static function ($title): string {
            return trim((string) $title);
        }, $titles), static function (string $title): bool {
            return $title !== '';
        })));

        self::$autoTitlesRequestCache[$sourceLang] = $titles;
        if (function_exists('set_transient')) {
            \set_transient($transientKey, $titles, self::AUTO_TITLES_TRANSIENT_TTL);
        }

        return $titles;
    }

    /**
     * Query keep-as-is post titles in the source language and filter risky short names.
     *
     * @return array<int, string>
     */
    private static function collectAutoDontTranslateTitles(string $sourceLang): array
    {
        $postTypes = self::getAutoDontTranslatePostTypes();
        if ($postTypes === [] || !function_exists('get_posts')) {
            return [];
        }

        $queryArgs = [
            'post_type' => $postTypes,
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        //Polylang: limit to posts in the source language being translated from
        if (function_exists('pll_get_post_language')) {
            $queryArgs['lang'] = $sourceLang;
        }

        $posts = \get_posts($queryArgs);
        if (!is_array($posts) || $posts === []) {
            return [];
        }

        $titles = [];
        $seen = [];
        foreach ($posts as $post) {
            $title = '';
            if (is_object($post)) {
                $title = trim((string) ($post->post_title ?? ''));
            } elseif (is_array($post)) {
                $title = trim((string) ($post['post_title'] ?? ''));
            } elseif (is_numeric($post) && function_exists('get_the_title')) {
                $title = trim((string) \get_the_title((int) $post));
            }
            if ($title === '' || !self::shouldAutoIncludeTitle($title)) {
                continue;
            }
            $key = mb_strtolower($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $titles[] = $title;
        }

        return $titles;
    }

    /**
     * Decide whether a post title is safe to auto-protect.
     *
     * Multi-word titles are always included. Single-token titles need length >= 4
     * and must not be on the denylist (e.g. "Plus") so common words are not locked.
     */
    public static function shouldAutoIncludeTitle(string $title): bool
    {
        $title = trim($title);
        if ($title === '') {
            return false;
        }

        //skip language-prefixed titles (e.g. "NL - Something") if any slipped in
        if (preg_match('/^[A-Za-z]{2}\s*-\s+/u', $title)) {
            return false;
        }

        $parts = preg_split('/\s+/u', $title);
        $tokens = is_array($parts) ? array_values(array_filter($parts, static function (string $part): bool {
            return $part !== '';
        })) : [];
        $isMultiWord = count($tokens) > 1;
        if ($isMultiWord) {
            return (bool) apply_filters('nct_auto_dont_translate_should_include_title', true, $title, true);
        }

        $minLength = (int) apply_filters(
            'nct_auto_dont_translate_min_single_token_length',
            self::AUTO_MIN_SINGLE_TOKEN_LENGTH
        );
        if ($minLength < 1) {
            $minLength = self::AUTO_MIN_SINGLE_TOKEN_LENGTH;
        }
        if (mb_strlen($title) < $minLength) {
            return (bool) apply_filters('nct_auto_dont_translate_should_include_title', false, $title, false);
        }

        $denylist = apply_filters('nct_auto_dont_translate_title_denylist', [
            'plus',
            'and',
            'the',
            'for',
            'with',
            'from',
            'over',
            'into',
            'van',
            'het',
            'een',
            'de',
            'en',
            'of',
            'or',
            'to',
            'in',
            'on',
            'at',
            'by',
            'met',
            'voor',
            'naar',
        ]);
        if (!is_array($denylist)) {
            $denylist = [];
        }
        $denied = [];
        foreach ($denylist as $word) {
            $word = mb_strtolower(trim((string) $word));
            if ($word !== '') {
                $denied[$word] = true;
            }
        }
        if (isset($denied[mb_strtolower($title)])) {
            return (bool) apply_filters('nct_auto_dont_translate_should_include_title', false, $title, false);
        }

        return (bool) apply_filters('nct_auto_dont_translate_should_include_title', true, $title, false);
    }

    /**
     * Protect matching phrases in one text. Returns protected text + restore map.
     *
     * @return array{0: string, 1: array<int, array{matched:string, target:string, keep_quote_before:bool, keep_quote_after:bool, slugify:bool}>, 2: bool}
     */
    public static function protect(string $text, string $sourceLang, string $targetLang): array
    {
        $rules = self::getApplicableRules($sourceLang, $targetLang);
        if ($rules === [] || $text === '') {
            return [$text, [], false];
        }

        //whole string looks like a WP slug → restore glossary targets as slugified
        $slugContext = self::isSlugContextText($text);

        $map = [];
        $nextId = 0;
        $protected = $text;
        $didProtect = false;

        foreach ($rules as $rule) {
            $matches = self::findMatches($protected, $rule['source'], (bool) $rule['also_in_sentence'], (bool) $rule['ignore_casing']);
            if ($matches === []) {
                continue;
            }

            // replace from the end so offsets stay valid
            for ($i = count($matches) - 1; $i >= 0; $i--) {
                $match = $matches[$i];
                $id = $nextId++;
                $charBefore = $match['start'] > 0 ? mb_substr($protected, $match['start'] - 1, 1) : '';
                $charAfter = mb_substr($protected, $match['start'] + $match['length'], 1);
                $map[$id] = [
                    'matched' => $match['text'],
                    'target' => $rule['target'],
                    // remember intentional source quotes so DeepL-added wraps can be stripped later
                    'keep_quote_before' => self::isQuoteChar($charBefore),
                    'keep_quote_after' => self::isQuoteChar($charAfter),
                    'slugify' => $slugContext,
                ];
                $wrap = self::buildProtectMarkup($id, $match['text']);
                $protected = self::mbSubstrReplace($protected, $wrap, $match['start'], $match['length']);
                $didProtect = true;
            }
        }

        return [$protected, $map, $didProtect];
    }

    /**
     * @param array<int, string> $texts
     * @return array{0: array<int, string>, 1: array<int, array<int, array{matched:string, target:string, keep_quote_before:bool, keep_quote_after:bool, slugify:bool}>>, 2: bool}
     */
    public static function protectTexts(array $texts, string $sourceLang, string $targetLang): array
    {
        $maps = [];
        $didProtect = false;
        $out = [];
        foreach ($texts as $index => $text) {
            if (!is_string($text) || trim($text) === '') {
                $out[$index] = $text;
                continue;
            }
            [$protected, $map, $protectedFlag] = self::protect($text, $sourceLang, $targetLang);
            $out[$index] = $protected;
            if ($map !== []) {
                $maps[(int) $index] = $map;
            }
            if ($protectedFlag) {
                $didProtect = true;
            }
        }
        return [$out, $maps, $didProtect];
    }

    /**
     * Restore protect markup using the map from protect().
     *
     * Also strips quotation marks DeepL often wraps around translate="no" spans,
     * unless the source already had quotes immediately adjacent to the match.
     *
     * @param array<int, array{matched:string, target:string, keep_quote_before?:bool, keep_quote_after?:bool, slugify?:bool}> $map
     */
    public static function restore(string $text, array $map): string
    {
        if ($text === '' || ($map === [] && !str_contains($text, self::PROTECT_ATTR))) {
            return $text;
        }

        $quoteClass = preg_quote(self::QUOTE_CHARS, '/');
        // optional DeepL wrapping quotes (with optional inner spaces) around the protect span
        // /u is required so multibyte curly quotes match as single characters in the class
        $pattern = '/([' . $quoteClass . ']\s*)?<span\b([^>]*)\b' . preg_quote(self::PROTECT_ATTR, '/')
            . '\s*=\s*(["\']?)(\d+)\3([^>]*)>(.*?)<\/span>(\s*[' . $quoteClass . '])?/isu';
        $restored = preg_replace_callback(
            $pattern,
            static function (array $m) use ($map): string {
                $id = (int) $m[4];
                $inner = (string) $m[6];
                $entry = $map[$id] ?? null;
                $matched = is_array($entry) ? (string) ($entry['matched'] ?? $inner) : $inner;
                $target = is_array($entry) ? (string) ($entry['target'] ?? $inner) : $inner;
                $forceSlugify = is_array($entry) && !empty($entry['slugify']);
                $replacement = self::applyCasing(
                    $matched !== '' ? $matched : $inner,
                    $target,
                    $forceSlugify
                );

                $quoteBefore = (string) ($m[1] ?? '');
                $quoteAfter = (string) ($m[7] ?? '');
                $keepBefore = is_array($entry) && !empty($entry['keep_quote_before']);
                $keepAfter = is_array($entry) && !empty($entry['keep_quote_after']);

                if ($keepBefore) {
                    $replacement = $quoteBefore . $replacement;
                }
                if ($keepAfter) {
                    $replacement .= $quoteAfter;
                }

                return $replacement;
            },
            $text
        );

        return is_string($restored) ? $restored : $text;
    }

    /**
     * @param array<int, string> $translations keyed like original texts array
     * @param array<int, array<int, array{matched:string, target:string, keep_quote_before?:bool, keep_quote_after?:bool, slugify?:bool}>> $mapsByIndex
     * @return array<int, string>
     */
    public static function restoreTranslations(array $translations, array $mapsByIndex): array
    {
        if ($mapsByIndex === []) {
            foreach ($translations as $index => $text) {
                if (is_string($text) && str_contains($text, self::PROTECT_ATTR)) {
                    $translations[$index] = self::restore($text, []);
                }
            }
            return $translations;
        }

        foreach ($mapsByIndex as $index => $map) {
            if (!isset($translations[$index]) || !is_string($translations[$index])) {
                continue;
            }
            $translations[$index] = self::restore($translations[$index], is_array($map) ? $map : []);
        }

        return $translations;
    }

    private static function isQuoteChar(string $char): bool
    {
        return $char !== '' && mb_strpos(self::QUOTE_CHARS, $char) !== false;
    }

    /**
     * Whether the full string being translated looks like a WP slug.
     * Lowercase, no spaces, letters/digits/hyphens/underscores only — so a
     * title-case page title like "Ketenverantwoordelijkheid" is not slugified.
     */
    public static function isSlugContextText(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || preg_match('/\s/u', $text)) {
            return false;
        }
        //WP post_name style is lowercase
        if ($text !== mb_strtolower($text)) {
            return false;
        }
        return (bool) preg_match('/^[\p{L}\p{N}_\-]+$/u', $text);
    }

    /**
     * Matched token itself looks hyphenated/underscored (slug fragment).
     */
    public static function matchedLooksSlugLike(string $matched): bool
    {
        $matched = trim($matched);
        if ($matched === '' || preg_match('/\s/u', $matched)) {
            return false;
        }
        return str_contains($matched, '-') || str_contains($matched, '_');
    }

    /**
     * Lowercase + hyphens for slug-style glossary restores.
     */
    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/[\s_]+/u', '-', $text);
        $text = preg_replace('/-+/', '-', (string) $text);
        return trim((string) $text, '-');
    }

    /**
     * Apply target casing based on the matched occurrence style.
     *
     * Don’t-translate (target ≈ source): adapt brand casing (KIEM / kiem / Kiem).
     * Glossary (different phrase): keep authored casing for title-case prose
     * ("Due diligence"), lowercase for mid-sentence, ALL CAPS → ALL CAPS,
     * and slugify when the whole string or matched token is slug-like.
     *
     * @param bool $forceSlugify set by protect() when the full text is slug context
     */
    public static function applyCasing(string $matched, string $target, bool $forceSlugify = false): string
    {
        if ($target === '' || $matched === '') {
            return $forceSlugify && $target !== '' ? self::slugify($target) : $target;
        }

        if (!preg_match('/\p{L}/u', $matched)) {
            return $forceSlugify ? self::slugify($target) : $target;
        }

        $upper = mb_strtoupper($matched);
        $lower = mb_strtolower($matched);
        $isGlossary = mb_strtolower($matched) !== mb_strtolower($target);
        $slugMode = $forceSlugify || self::matchedLooksSlugLike($matched);

        if ($matched === $upper) {
            return $slugMode
                ? mb_strtoupper(self::slugify($target))
                : mb_strtoupper($target);
        }
        if ($matched === $lower) {
            return $slugMode ? self::slugify($target) : mb_strtolower($target);
        }
        if ($slugMode) {
            return self::slugify($target);
        }
        if (self::isTitleCase($matched)) {
            //glossary: keep intended word casing; brands: title-case the stored source
            return $isGlossary ? $target : self::toTitleCase($target);
        }

        return $target;
    }

    /**
     * @return array<int, array{start:int, length:int, text:string}>
     */
    public static function findMatches(string $haystack, string $needle, bool $alsoInSentence, bool $ignoreCasing): array
    {
        $needle = (string) $needle;
        if ($haystack === '' || $needle === '') {
            return [];
        }

        $segments = self::splitAroundProtectSpans($haystack);
        $matches = [];

        foreach ($segments as $segment) {
            if ($segment['protected']) {
                continue;
            }
            $chunk = $segment['text'];
            $baseOffset = $segment['start'];
            if ($chunk === '') {
                continue;
            }

            if (!$alsoInSentence) {
                $compareHay = $ignoreCasing ? mb_strtolower($chunk) : $chunk;
                $compareNeedle = $ignoreCasing ? mb_strtolower($needle) : $needle;
                if ($compareHay === $compareNeedle) {
                    $matches[] = [
                        'start' => $baseOffset,
                        'length' => mb_strlen($chunk),
                        'text' => $chunk,
                    ];
                }
                continue;
            }

            $pattern = self::buildPhrasePattern($needle, $ignoreCasing);
            if ($pattern === '') {
                continue;
            }
            if (!preg_match_all($pattern, $chunk, $pregMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($pregMatches[0] as $pregMatch) {
                $byteOffset = (int) $pregMatch[1];
                $matchText = (string) $pregMatch[0];
                $charOffset = self::byteOffsetToCharOffset($chunk, $byteOffset);
                $absoluteStart = $baseOffset + $charOffset;
                //never wrap needles inside HTML tags/attrs (href with "2bhonest", path "environment")
                if (self::isInsideHtmlTag($haystack, $absoluteStart, mb_strlen($matchText))) {
                    continue;
                }
                $matches[] = [
                    'start' => $absoluteStart,
                    'length' => mb_strlen($matchText),
                    'text' => $matchText,
                ];
            }
        }

        return $matches;
    }

    private static function buildProtectMarkup(int $id, string $matchedText): string
    {
        return '<span translate="no" ' . self::PROTECT_ATTR . '="' . $id . '">' . $matchedText . '</span>';
    }

    /**
     * True when [$start, $start+$length) sits inside an unclosed HTML tag (between < and >).
     * Prevents wrapping brand needles that appear in href/src attribute values.
     *
     * Also handles Gutenberg JSON unicode escapes (\u003c / \u003e / \u0022) stored as
     * literal backslash-u sequences in post_content — otherwise "2bhonest" inside
     * href=\u0022https://2bhonest.test/...\u0022 gets wrapped and DeepL emits broken URLs.
     */
    private static function isInsideHtmlTag(string $haystack, int $start, int $length): bool
    {
        unset($length);
        if ($haystack === '' || $start <= 0) {
            return false;
        }

        $before = mb_substr($haystack, 0, $start);
        return self::isInsideLiteralHtmlTag($before) || self::isInsideUnicodeEscapedHtmlTag($before);
    }

    /**
     * @param string $before Text before the match (character offsets).
     */
    private static function isInsideLiteralHtmlTag(string $before): bool
    {
        $lastOpen = mb_strrpos($before, '<');
        if ($lastOpen === false) {
            return false;
        }
        $lastClose = mb_strrpos($before, '>');
        if ($lastClose !== false && $lastClose > $lastOpen) {
            return false;
        }

        return true;
    }

    /**
     * Same as isInsideLiteralHtmlTag but for literal "\u003c" / "\u003e" sequences in the string.
     */
    private static function isInsideUnicodeEscapedHtmlTag(string $before): bool
    {
        //Gutenberg stores tag delimiters as the 6-char sequences \u003c and \u003e
        $openToken = '\\u003c';
        $closeToken = '\\u003e';
        $lastOpen = mb_strrpos($before, $openToken);
        if ($lastOpen === false) {
            //also accept uppercase hex as produced by some serializers
            $lastOpen = mb_strrpos($before, '\\u003C');
            if ($lastOpen === false) {
                return false;
            }
        }
        $lastClose = mb_strrpos($before, $closeToken);
        if ($lastClose === false) {
            $lastClose = mb_strrpos($before, '\\u003E');
        }
        if ($lastClose !== false && $lastClose > $lastOpen) {
            return false;
        }

        return true;
    }

    private static function buildPhrasePattern(string $needle, bool $ignoreCasing): string
    {
        $parts = preg_split('/\s+/u', trim($needle));
        if (!is_array($parts) || $parts === []) {
            return '';
        }
        $escaped = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $escaped[] = preg_quote($part, '/');
        }
        if ($escaped === []) {
            return '';
        }
        $body = implode('\s+', $escaped);
        $flags = $ignoreCasing ? 'iu' : 'u';
        return '/(?<![\p{L}\p{N}_])' . $body . '(?![\p{L}\p{N}_])/' . $flags;
    }

    /**
     * @return array<int, array{start:int, text:string, protected:bool}>
     */
    private static function splitAroundProtectSpans(string $text): array
    {
        $pattern = '/<span\b[^>]*\b' . preg_quote(self::PROTECT_ATTR, '/') . '\s*=\s*(["\']?)\d+\1[^>]*>.*?<\/span>/is';
        $segments = [];
        $charPos = 0;

        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [['start' => 0, 'text' => $text, 'protected' => false]];
        }

        foreach ($matches[0] as $match) {
            $byteStart = (int) $match[1];
            $matchText = (string) $match[0];
            $charStart = self::byteOffsetToCharOffset($text, $byteStart);
            if ($charStart > $charPos) {
                $segments[] = [
                    'start' => $charPos,
                    'text' => mb_substr($text, $charPos, $charStart - $charPos),
                    'protected' => false,
                ];
            }
            $segments[] = [
                'start' => $charStart,
                'text' => $matchText,
                'protected' => true,
            ];
            $charPos = $charStart + mb_strlen($matchText);
        }

        $remaining = mb_substr($text, $charPos);
        if ($remaining !== '') {
            $segments[] = [
                'start' => $charPos,
                'text' => $remaining,
                'protected' => false,
            ];
        }

        return $segments !== [] ? $segments : [['start' => 0, 'text' => $text, 'protected' => false]];
    }

    private static function byteOffsetToCharOffset(string $text, int $byteOffset): int
    {
        if ($byteOffset <= 0) {
            return 0;
        }
        if ($byteOffset >= strlen($text)) {
            return mb_strlen($text);
        }
        return mb_strlen(substr($text, 0, $byteOffset));
    }

    private static function mbSubstrReplace(string $string, string $replacement, int $start, int $length): string
    {
        return mb_substr($string, 0, $start) . $replacement . mb_substr($string, $start + $length);
    }

    private static function isTitleCase(string $text): bool
    {
        $words = preg_split('/\s+/u', trim($text));
        if (!is_array($words) || $words === []) {
            return false;
        }
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $first = mb_substr($word, 0, 1);
            $rest = mb_substr($word, 1);
            if ($first !== mb_strtoupper($first)) {
                return false;
            }
            if ($rest !== '' && $rest !== mb_strtolower($rest)) {
                return false;
            }
        }
        return true;
    }

    private static function toTitleCase(string $text): string
    {
        $words = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($words)) {
            return $text;
        }
        $out = '';
        foreach ($words as $word) {
            if ($word === '' || preg_match('/^\s+$/u', $word)) {
                $out .= $word;
                continue;
            }
            $out .= mb_strtoupper(mb_substr($word, 0, 1)) . mb_strtolower(mb_substr($word, 1));
        }
        return $out;
    }
}
