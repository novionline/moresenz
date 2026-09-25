<?php

namespace NoviOnline\ContentTranslator\Core;

/**
     * Class DeepLTranslator
     * @package NoviOnline\ContentTranslator\Core
 */
class DeepLTranslator
{
    private const FORMALITY_UNSUPPORTED_TRANSIENT_PREFIX = 'nct_deepl_formality_unsupported_';

    //marker attribute used while DeepL HTML mode runs; restored afterwards
    private const ANCHOR_PROTECT_ATTR = 'data-nct-a';

    //guard so emptied-anchor healing (plain re-translate) does not re-enter HTML anchor protect
    private static bool $skipHtmlAnchorProtect = false;

    public static function enableTransientCache(int $ttlSeconds): void
    {
        DeepLTranslationCache::enable($ttlSeconds);
    }

    public static function disableTransientCache(): void
    {
        DeepLTranslationCache::disable();
    }

    private static function buildFormalityUnsupportedCacheKey(string $targetDeepL): string
    {
        $key = strtolower(trim($targetDeepL));
        $key = preg_replace('/[^a-z0-9\\-]/', '', $key);
        return self::FORMALITY_UNSUPPORTED_TRANSIENT_PREFIX . ($key ?: 'unknown');
    }
    /**
     * WordPress option key where Novi stores translator settings.
     */
    private const SETTINGS_OPTION_KEY = 'novi_content_translator_settings';

    /**
     * @var object|null
     */
    private static $translator = null;

    /**
     * Test override for translation calls.
     * @var callable|null
     */
    private static $testTranslator = null;

    /**
     * Allow unit tests to override translation behavior.
     * @param callable|null $translatorFn function(array $texts, string $sourceLang, string $targetLang, array $options): array
     * @return void
     */
    public static function setTestTranslator(?callable $translatorFn): void
    {
        self::$testTranslator = $translatorFn;
    }

    /**
     * Get DeepL Translator instance
     * @return object|null
     */
    private static function getTranslator(): ?object
    {
        if (self::$translator !== null) {
            return self::$translator;
        }

        $apiKey = self::getSavedApiKey();
        if (empty($apiKey)) {
            // backward compatibility: fallback to legacy constant
            $apiKey = defined('NCT_DEEPL_API_KEY') ? NCT_DEEPL_API_KEY : '';
        }

        if (empty($apiKey)) {
            error_log('Novi content translator: DeepL API key is not configured.');
            return null;
        }

        if (!class_exists('\DeepL\Translator')) {
            error_log('Novi content translator: DeepL SDK class DeepL\\Translator is unavailable.');
            return null;
        }

        try {
            self::$translator = new \DeepL\Translator($apiKey);
            return self::$translator;
        } catch (\Throwable $e) {
            error_log('Novi content translator: Failed to initialize DeepL Translator: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Read the saved DeepL API key from WordPress settings.
     */
    private static function getSavedApiKey(): string
    {
        if (!function_exists('get_option')) {
            return '';
        }

        $raw = (array) \get_option(self::SETTINGS_OPTION_KEY, []);
        $encryptedKey = isset($raw['deepl_api_key']) ? (string) $raw['deepl_api_key'] : '';

        if ($encryptedKey !== '') {
            $decrypted = self::decryptApiKeyFromStorage($encryptedKey);
            if ($decrypted !== '') {
                return trim($decrypted);
            }
        }

        // backward compatibility for any old plain-text value in DB
        $legacyKey = isset($raw['deepl_api_key_plain']) ? (string) $raw['deepl_api_key_plain'] : '';
        return trim($legacyKey);
    }

    /**
     * Encrypt API key for DB storage (installation-specific).
     */
    public static function encryptApiKeyForStorage(string $apiKey): string
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return '';
        }

        if (!function_exists('wp_salt')) {
            return base64_encode($apiKey);
        }

        $salt = (string) wp_salt('auth');
        $key = hash('sha256', $salt, true);
        $iv = substr(hash('sha256', $salt . '|nct-deepl-iv', true), 0, 16);

        $encrypted = openssl_encrypt($apiKey, 'AES-256-CBC', $key, 0, $iv);
        return is_string($encrypted) ? $encrypted : '';
    }

    /**
     * Decrypt API key from DB storage.
     */
    public static function decryptApiKeyFromStorage(string $encryptedApiKey): string
    {
        $encryptedApiKey = trim($encryptedApiKey);
        if ($encryptedApiKey === '') {
            return '';
        }

        if (!function_exists('wp_salt')) {
            $decoded = base64_decode($encryptedApiKey, true);
            return is_string($decoded) ? $decoded : '';
        }

        $salt = (string) wp_salt('auth');
        $key = hash('sha256', $salt, true);
        $iv = substr(hash('sha256', $salt . '|nct-deepl-iv', true), 0, 16);

        $decrypted = openssl_decrypt($encryptedApiKey, 'AES-256-CBC', $key, 0, $iv);
        return is_string($decrypted) ? $decrypted : '';
    }

    /**
     * Build a cache key for throttling key validation.
     */
    public static function buildUsageValidationCacheKey(string $apiKey): string
    {
        $hash = hash('sha256', $apiKey);
        return 'nct_deepl_usage_valid_' . $hash;
    }

    /**
     * Create a masked DeepL key identifier for UI.
     *
     * Example: DE****7A2C
     */
    public static function maskApiKeyIdentifier(string $apiKey): string
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return '';
        }

        $upper = strtoupper($apiKey);
        $prefix = substr($upper, 0, 2);
        $last4 = strlen($upper) > 4 ? substr($upper, -4) : $upper;

        return $prefix . '****' . $last4;
    }

    /**
     * Try to normalize a timestamp-like value into unix seconds.
     */
    private static function normalizeTimestamp(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 0) {
                return $timestamp;
            }
            return null;
        }

        if (is_string($value) && trim($value) !== '') {
            $trimmed = trim($value);
            if (is_numeric($trimmed)) {
                $timestamp = (int) $trimmed;
                return $timestamp > 0 ? $timestamp : null;
            }

            $parsed = strtotime($trimmed);
            return $parsed !== false ? (int) $parsed : null;
        }

        return null;
    }

    /**
     * Determine if quota is exhausted based on usage data.
     */
    public static function isQuotaExhausted(array $status): bool
    {
        $usage = isset($status['usage']) && is_array($status['usage']) ? $status['usage'] : [];
        $count = isset($usage['character_count']) ? (int) $usage['character_count'] : 0;
        $limit = isset($usage['character_limit']) ? (int) $usage['character_limit'] : 0;

        return $limit > 0 && $count >= $limit;
    }

    /**
     * Validate a DeepL key by calling /v2/usage (cached/throttled).
     *
     * @return array{
     *   valid: bool,
     *   identifier: string,
     *   usage: array<string, int|string|null>,
     *   quota_exhausted: bool,
     *   refresh_at: int|null,
     *   period_start_at: int|null,
     *   period_end_at: int|null,
     *   last_checked: int
     * }
     */
    public static function validateDeepLKeyAndGetUsage(string $apiKey, bool $forceRefresh = false): array
    {
        $apiKey = trim($apiKey);
        $identifier = self::maskApiKeyIdentifier($apiKey);
        $now = time();

        if ($apiKey === '') {
            return [
                'valid' => false,
                'identifier' => '',
                'usage' => [
                    'character_count' => 0,
                    'character_limit' => 0,
                    'api_key_character_count' => null,
                    'api_key_character_limit' => null,
                ],
                'quota_exhausted' => false,
                'refresh_at' => null,
                'period_start_at' => null,
                'period_end_at' => null,
                'last_checked' => $now,
            ];
        }

        $cacheKey = self::buildUsageValidationCacheKey($apiKey);
        if (!$forceRefresh && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && isset($cached['valid'], $cached['usage'], $cached['last_checked'])) {
                $cached['identifier'] = $cached['identifier'] ?? $identifier;
                $cached['quota_exhausted'] = self::isQuotaExhausted($cached);
                $cached['refresh_at'] = isset($cached['refresh_at']) ? self::normalizeTimestamp($cached['refresh_at']) : null;
                $cached['period_start_at'] = isset($cached['period_start_at']) ? self::normalizeTimestamp($cached['period_start_at']) : null;
                $cached['period_end_at'] = isset($cached['period_end_at']) ? self::normalizeTimestamp($cached['period_end_at']) : null;
                return $cached;
            }
        }

        $result = [
            'valid' => false,
            'identifier' => $identifier,
            'usage' => [
                'character_count' => 0,
                'character_limit' => 0,
                'api_key_character_count' => null,
                'api_key_character_limit' => null,
            ],
            'quota_exhausted' => false,
            'refresh_at' => null,
            'period_start_at' => null,
            'period_end_at' => null,
            'last_checked' => $now,
        ];

        // DeepL API Pro uses api.deepl.com, Free uses api-free.deepl.com.
        $hosts = ['https://api.deepl.com', 'https://api-free.deepl.com'];
        $usageJson = null;
        $httpCode = 0;

        foreach ($hosts as $host) {
            $response = null;
            $url = $host . '/v2/usage';

            if (function_exists('wp_remote_get')) {
                $response = wp_remote_get($url, [
                    'timeout' => 15,
                    'headers' => [
                        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
                    ],
                ]);
            } else {
                // Non-WP runtime fallback (unit tests). Keep it simple.
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => 'Authorization: DeepL-Auth-Key ' . $apiKey,
                        'timeout' => 15,
                    ],
                ]);
                $body = @file_get_contents($url, false, $context);
                $usageJson = $body ? json_decode($body, true) : null;
                $httpCode = 200;
                if (is_array($usageJson)) {
                    break;
                }
                continue;
            }

            if (is_wp_error($response)) {
                continue;
            }

            $httpCode = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $usageJson = $body ? json_decode($body, true) : null;

            if ($httpCode >= 200 && $httpCode < 300 && is_array($usageJson)) {
                break;
            }
        }

        $hasUsageFields = is_array($usageJson)
            && array_key_exists('character_count', $usageJson)
            && array_key_exists('character_limit', $usageJson);

        if ($httpCode >= 200 && $httpCode < 300 && $hasUsageFields) {
            $result['valid'] = true;
            $result['usage']['character_count'] = isset($usageJson['character_count']) ? (int) $usageJson['character_count'] : 0;
            $result['usage']['character_limit'] = isset($usageJson['character_limit']) ? (int) $usageJson['character_limit'] : 0;
            $result['usage']['api_key_character_count'] = isset($usageJson['api_key_character_count']) ? (int) $usageJson['api_key_character_count'] : null;
            $result['usage']['api_key_character_limit'] = isset($usageJson['api_key_character_limit']) ? (int) $usageJson['api_key_character_limit'] : null;

            // DeepL may expose billing period timing keys depending on account/API version.
            $periodStartCandidates = [
                $usageJson['billing_period_start'] ?? null,
                $usageJson['start_time'] ?? null,
                $usageJson['subscription_period_start'] ?? null,
            ];
            foreach ($periodStartCandidates as $candidate) {
                $normalized = self::normalizeTimestamp($candidate);
                if ($normalized !== null) {
                    $result['period_start_at'] = $normalized;
                    break;
                }
            }

            $periodEndCandidates = [
                $usageJson['billing_period_end'] ?? null,
                $usageJson['end_time'] ?? null,
                $usageJson['subscription_period_end'] ?? null,
            ];
            foreach ($periodEndCandidates as $candidate) {
                $normalized = self::normalizeTimestamp($candidate);
                if ($normalized !== null) {
                    $result['period_end_at'] = $normalized;
                    $result['refresh_at'] = $normalized;
                    break;
                }
            }
        } else {
            // keep valid=false with existing defaults
        }

        $result['quota_exhausted'] = self::isQuotaExhausted($result);

        if (function_exists('set_transient')) {
            // validate once per minute to preserve quota
            set_transient($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Convenience: validate the currently saved key.
     */
    public static function getDeepLKeyStatus(bool $forceRefresh = false): array
    {
        $apiKey = self::getSavedApiKey();
        return self::validateDeepLKeyAndGetUsage($apiKey, $forceRefresh);
    }

    /**
     * Map WordPress language code to DeepL language code
     * Handles source and target languages correctly (English and Portuguese require regional variants for targets)
     * @param string $wpLang WordPress locale (e.g., 'nl_NL', 'en_US', 'en_GB', 'pt_BR', 'pt_PT')
     * @param bool $isTargetLanguage Whether this is a target language (default: true)
     * @return string DeepL language code (e.g., 'nl', 'en-US', 'en-GB', 'pt-BR', 'pt-PT')
     */
    public static function mapWordPressToDeepL(string $wpLang, bool $isTargetLanguage = true): string
    {
        // Normalize WordPress locale format (handle both underscore and hyphen)
        $wpLang = str_replace('_', '-', strtolower($wpLang));
        
        // Split into language and region parts
        $parts = explode('-', $wpLang, 2);
        $baseLang = $parts[0];
        $region = isset($parts[1]) ? strtoupper($parts[1]) : null;

        // Handle English - requires regional variant for target languages
        if ($baseLang === 'en') {
            if ($isTargetLanguage) {
                // For target languages, use regional variant
                if ($region === 'GB' || $region === 'UK') {
                    return 'en-GB';
                } elseif ($region === 'US' || $region === 'CA' || $region === 'AU' || $region === 'NZ') {
                    return 'en-US';
                } else {
                    // Default to en-US if no specific region or unknown region
                    return 'en-US';
                }
            } else {
                // For source languages, use 'en'
                return 'en';
            }
        }

        // Handle Portuguese - requires regional variant for target languages
        if ($baseLang === 'pt') {
            if ($isTargetLanguage) {
                // For target languages, use regional variant
                if ($region === 'BR') {
                    return 'pt-BR';
                } elseif ($region === 'PT') {
                    return 'pt-PT';
                } else {
                    // Default to pt-BR if no specific region
                    return 'pt-BR';
                }
            } else {
                // For source languages, use 'pt'
                return 'pt';
            }
        }

        // Map other languages (all use lowercase codes per DeepL API)
        $mapping = [
            'nl' => 'nl', // Dutch
            'de' => 'de', // German
            'fr' => 'fr', // French
            'es' => 'es', // Spanish
            'it' => 'it', // Italian
            'pl' => 'pl', // Polish
            'ru' => 'ru', // Russian
            'ja' => 'ja', // Japanese
            'zh' => 'zh', // Chinese
            'ar' => 'ar', // Arabic
            'bg' => 'bg', // Bulgarian
            'cs' => 'cs', // Czech
            'da' => 'da', // Danish
            'el' => 'el', // Greek
            'et' => 'et', // Estonian
            'fi' => 'fi', // Finnish
            'hu' => 'hu', // Hungarian
            'id' => 'id', // Indonesian
            'ko' => 'ko', // Korean
            'lt' => 'lt', // Lithuanian
            'lv' => 'lv', // Latvian
            'nb' => 'nb', // Norwegian (bokmål)
            'ro' => 'ro', // Romanian
            'sk' => 'sk', // Slovak
            'sl' => 'sl', // Slovenian
            'sv' => 'sv', // Swedish
            'tr' => 'tr', // Turkish
            'uk' => 'uk', // Ukrainian
            'th' => 'th', // Thai (supported by DeepL)
            'vi' => 'vi', // Vietnamese
            'he' => 'he', // Hebrew
            'hi' => 'hi', // Hindi
        ];

        // Return mapped DeepL code or empty string when not supported (e.g. Thai).
        // Callers must handle empty string as "unsupported language".
        return $mapping[$baseLang] ?? '';
    }

    //getSourceLanguage removed: DeepLTranslator now relies on Polylang/locale values passed in from callers

    /**
     * Normalize Unicode characters (e.g., mathematical bold) to regular characters
     * This helps DeepL translate text that contains special Unicode characters
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private static function normalizeUnicode(string $text): string
    {
        // Map mathematical bold Unicode characters (U+1D400–U+1D7FF) to regular characters
        // Mathematical Bold A-Z: U+1D400–U+1D419
        // Mathematical Bold a-z: U+1D41A–U+1D433
        // Mathematical Bold 0-9: U+1D7CE–U+1D7D7
        
        $normalized = $text;
        
        // Mathematical Bold uppercase A-Z (U+1D400–U+1D419)
        for ($i = 0; $i < 26; $i++) {
            $boldChar = mb_chr(0x1D400 + $i, 'UTF-8');
            $normalChar = chr(65 + $i); // A-Z
            $normalized = str_replace($boldChar, $normalChar, $normalized);
        }
        
        // Mathematical Bold lowercase a-z (U+1D41A–U+1D433)
        for ($i = 0; $i < 26; $i++) {
            $boldChar = mb_chr(0x1D41A + $i, 'UTF-8');
            $normalChar = chr(97 + $i); // a-z
            $normalized = str_replace($boldChar, $normalChar, $normalized);
        }
        
        // Mathematical Bold digits 0-9 (U+1D7CE–U+1D7D7)
        for ($i = 0; $i < 10; $i++) {
            $boldChar = mb_chr(0x1D7CE + $i, 'UTF-8');
            $normalChar = chr(48 + $i); // 0-9
            $normalized = str_replace($boldChar, $normalChar, $normalized);
        }
        
        return $normalized;
    }

    /**
     * Translate multiple texts in batch
     * DeepL supports up to 50 texts per request
     * @param array $texts Array of strings to translate
     * @param string $sourceLang Source language code (WordPress locale)
     * @param string $targetLang Target language code (WordPress locale)
     * @param array $options Optional translation options (e.g., ['context' => 'html', 'tag_handling' => 'html'])
     * @return array Array with 'success' (bool), 'translations' (array), and 'error' (string|null)
     */
    public static function translateTexts(array $texts, string $sourceLang, string $targetLang, array $options = []): array
    {
        //allow unit tests to override translation behavior, but keep retry logic consistent
        $testTranslator = is_callable(self::$testTranslator) ? self::$testTranslator : null;

        // Determine translation context (plain text vs HTML, etc.)
        $context = isset($options['context']) && is_string($options['context'])
            ? $options['context']
            : 'plain';
        unset($options['context']);

        $translator = null;
        if (!is_callable($testTranslator)) {
            $translator = self::getTranslator();
            if (!$translator) {
                //no API key or DeepL client available: skip translation silently and return original texts
                return [
                    'success' => true,
                    'translations' => $texts,
                    'error' => null,
                ];
            }
        }

        // Filter out empty texts, normalize Unicode characters, and keep track of indices
        $nonEmptyTexts = [];
        $indices = [];
        foreach ($texts as $index => $text) {
            if (!empty(trim($text))) {
                // Normalize Unicode characters (e.g., mathematical bold) to regular characters
                $normalizedText = self::normalizeUnicode($text);
                $nonEmptyTexts[] = $normalizedText;
                $indices[] = $index;
            }
        }

        if (empty($nonEmptyTexts)) {
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        }

        // Map WordPress locales to DeepL codes
        // Source language can use generic codes (e.g., 'en', 'pt')
        // Target language requires regional variants for English and Portuguese (e.g., 'en-US', 'pt-BR')
        $sourceDeepL = self::mapWordPressToDeepL($sourceLang, false);
        $targetDeepL = self::mapWordPressToDeepL($targetLang, true);

        // If DeepL doesn't support either source or target language (e.g. Thai),
        // skip translation but report a soft warning so callers can surface it.
        if ($sourceDeepL === '' || $targetDeepL === '') {
            $unsupported = $targetDeepL === '' ? $targetLang : $sourceLang;
            $errorMessage = sprintf(
                __('DeepL does not support language %s. Content was duplicated without automatic translation.', 'novi-content-translator'),
                $unsupported
            );
            error_log('Novi content translator: ' . $errorMessage);
            return [
                'success' => true,
                'translations' => $texts,
                'error' => $errorMessage,
            ];
        }

        // If source and target are the same, return original texts
        if ($sourceDeepL === $targetDeepL) {
            return [
                'success' => true,
                'translations' => $texts,
                'error' => null,
            ];
        }

        // protect <a> tags BEFORE string overrules. overrules wrap brand/title needles
        // (e.g. "2bhonest", "environment") in <span translate="no">; doing that inside
        // href="..." splits the opening tag so protectHtmlAnchors / DeepL emit empty
        // labels and attribute bleed (see homepage Spielfeld + /de/strategie/).
        $anchorMapsByOriginalIndex = [];
        $didProtectAnchors = false;
        if (!self::$skipHtmlAnchorProtect && self::textsContainHtmlAnchors($nonEmptyTexts)) {
            foreach ($nonEmptyTexts as $i => $text) {
                $originalIndex = (int) ($indices[$i] ?? -1);
                [$protected, $map] = self::protectHtmlAnchors((string) $text);
                $nonEmptyTexts[$i] = $protected;
                if ($map !== []) {
                    $didProtectAnchors = true;
                    if ($originalIndex >= 0) {
                        $anchorMapsByOriginalIndex[$originalIndex] = $map;
                    }
                }
            }
        }

        // apply string overrules before cache/DeepL so brand phrases are never translated
        $overruleMapsByIndex = [];
        $didProtectOverrules = false;
        [$nonEmptyTexts, $overruleMapsByOriginalPos, $didProtectOverrules] = StringOverrules::protectTexts(
            $nonEmptyTexts,
            $sourceLang,
            $targetLang
        );
        // remap protect maps from non-empty array positions to original $texts indices
        foreach ($overruleMapsByOriginalPos as $nonEmptyPos => $map) {
            $originalIndex = (int) ($indices[$nonEmptyPos] ?? -1);
            if ($originalIndex >= 0) {
                $overruleMapsByIndex[$originalIndex] = $map;
            }
        }

        $finalizeWithOverrules = function (array $result) use (&$anchorMapsByOriginalIndex, $overruleMapsByIndex, $sourceLang, $targetLang, $texts): array {
            if (!isset($result['translations']) || !is_array($result['translations'])) {
                return $result;
            }
            if ($anchorMapsByOriginalIndex !== []) {
                $result['translations'] = self::restoreHtmlAnchorsInTranslations(
                    $result['translations'],
                    $anchorMapsByOriginalIndex,
                    $sourceLang,
                    $targetLang
                );
            }
            $result['translations'] = StringOverrules::restoreTranslations(
                $result['translations'],
                $overruleMapsByIndex
            );
            //DeepL often Title-Cases short English slogans; undo when source was not Title Case
            foreach ($result['translations'] as $index => $translated) {
                if (!is_string($translated)) {
                    continue;
                }
                $source = isset($texts[$index]) && is_string($texts[$index]) ? $texts[$index] : '';
                $translated = self::normalizeUnexpectedTitleCase($source, $translated);
                $translated = self::normalizeSpacedDashes($source, $translated);
                //DeepL often inserts English address commas after <br> ("Street<br>, City")
                $translated = self::stripSpuriousBreakCommas($source, $translated);
                //post-restore HTML integrity (NL→EN and EN→DE)
                if (stripos($translated, '<a') !== false || stripos($translated, '</') !== false) {
                    $translated = self::stripTextFragmentsFromHtml($translated);
                    $translated = self::collapseConsecutiveSameHrefAnchors($translated);
                    $translated = self::ensureInlineTagWhitespace($translated);
                }
                $result['translations'][$index] = $translated;
            }
            return $result;
        };

        $doTranslate = function (array $translationOptions, array $textsToTranslate, array $nonEmptyTextsToTranslate, array $indicesToTranslate) use ($testTranslator, $texts, $sourceLang, $targetLang, $sourceDeepL, $targetDeepL, $translator, $context): array {
            if (is_callable($testTranslator)) {
                // tests can inspect the merged options via $options; provide a similar signature
                $raw = call_user_func($testTranslator, $textsToTranslate, $sourceLang, $targetLang, array_merge(['context' => $context], $translationOptions));
                if (!is_array($raw)) {
                    return [
                        'success' => false,
                        'translations' => $texts,
                        'error' => 'DeepL test translator returned invalid result',
                    ];
                }
                $rawTranslations = isset($raw['translations']) && is_array($raw['translations']) ? $raw['translations'] : $textsToTranslate;
                $translations = $texts;
                foreach ($indicesToTranslate as $i => $originalIndex) {
                    if (isset($rawTranslations[$i])) {
                        $translations[$originalIndex] = $rawTranslations[$i];
                    }
                }
                return [
                    'success' => isset($raw['success']) ? (bool) $raw['success'] : true,
                    'translations' => $translations,
                    'error' => isset($raw['error']) && is_string($raw['error']) ? $raw['error'] : null,
                ];
            }

            $results = $translator->translateText($nonEmptyTextsToTranslate, $sourceDeepL, $targetDeepL, $translationOptions);

            // Map results back to original array structure
            $translations = $texts;
            foreach ($indicesToTranslate as $i => $originalIndex) {
                if (isset($results[$i])) {
                    $translations[$originalIndex] = $results[$i]->text;
                }
            }

            return [
                'success' => true,
                'translations' => $translations,
                'error' => null,
            ];
        };

        $translationOptions = [];
        // default: no caching, translate everything
        $missNonEmptyTexts = $nonEmptyTexts;
        $missIndices = $indices;
        try {
            // Build translation options from Novi defaults + per-call overrides
            $defaultOptions = self::getDefaultOptions($context, $targetLang);
            $mergedOptions = array_merge($defaultOptions, $options);

            // overrules / early anchor protect wrap content in HTML; force tag handling
            if (($didProtectOverrules || $didProtectAnchors) && empty($mergedOptions['tag_handling'])) {
                $mergedOptions['tag_handling'] = 'html';
            }

            // DeepL HTML mode drops NBSP/unicode spaces next to tags (Gutenberg often uses NBSP
            // before <strong>/<em>/<a>). Convert those to ASCII spaces so DeepL keeps them.
            if (!empty($mergedOptions['tag_handling']) && (string) $mergedOptions['tag_handling'] === 'html') {
                foreach ($nonEmptyTexts as $i => $text) {
                    $nonEmptyTexts[$i] = self::normalizeHtmlWhitespace((string) $text);
                }
            }

            $hasOptionConstants = class_exists('\DeepL\TranslateTextOptions');
            if (!empty($mergedOptions['tag_handling'])) {
                $optionKey = $hasOptionConstants ? \DeepL\TranslateTextOptions::TAG_HANDLING : 'tag_handling';
                $translationOptions[$optionKey] = $mergedOptions['tag_handling'];
            }
            if (!empty($mergedOptions['formality'])) {
                //avoid sending formality for targets we've learned don't support it (cached 1 day)
                $cacheKey = self::buildFormalityUnsupportedCacheKey($targetDeepL);
                $formalityUnsupported = function_exists('get_transient') ? (bool) get_transient($cacheKey) : false;
                if (!$formalityUnsupported) {
                    $optionKey = $hasOptionConstants ? \DeepL\TranslateTextOptions::FORMALITY : 'formality';
                    $translationOptions[$optionKey] = $mergedOptions['formality'];
                }
            }
            if (!empty($mergedOptions['split_sentences'])) {
                $optionKey = $hasOptionConstants ? \DeepL\TranslateTextOptions::SPLIT_SENTENCES : 'split_sentences';
                $translationOptions[$optionKey] = $mergedOptions['split_sentences'];
            }
            if (array_key_exists('preserve_formatting', $mergedOptions)) {
                $optionKey = $hasOptionConstants ? \DeepL\TranslateTextOptions::PRESERVE_FORMATTING : 'preserve_formatting';
                $translationOptions[$optionKey] = (bool) $mergedOptions['preserve_formatting'];
            }
            // Glossary and tag-related options are reserved for future use when
            // Novi exposes them in the settings UI. The DeepL PHP SDK versions
            // in use might not expose dedicated constants for these, so we only
            // wire the core, widely supported options above for now.
            
            // Optional transient cache (enabled only by bulk CLI runs).
            $useCache = DeepLTranslationCache::isEnabled();

            $cachedTranslationsByOriginalIndex = [];
            $missNonEmptyTexts = [];
            $missIndices = [];

            if ($useCache) {
                foreach ($nonEmptyTexts as $i => $normalizedText) {
                    $originalIndex = (int) ($indices[$i] ?? -1);
                    if ($originalIndex < 0) {
                        continue;
                    }
                    $key = DeepLTranslationCache::buildKey($sourceLang, $targetLang, $context, $mergedOptions, (string) $normalizedText);
                    $cached = DeepLTranslationCache::get($key);
                    if ($cached !== '') {
                        $cachedTranslationsByOriginalIndex[$originalIndex] = $cached;
                        continue;
                    }
                    $missNonEmptyTexts[] = $normalizedText;
                    $missIndices[] = $originalIndex;
                }
            } else {
                // No caching: treat everything as a miss.
                $missNonEmptyTexts = $nonEmptyTexts;
                $missIndices = $indices;
            }

            if ($missNonEmptyTexts === []) {
                $translations = $texts;
                foreach ($cachedTranslationsByOriginalIndex as $originalIndex => $translated) {
                    $translations[(int) $originalIndex] = $translated;
                }
                return $finalizeWithOverrules([
                    'success' => true,
                    'translations' => $translations,
                    'error' => null,
                ]);
            }

            // Translate only cache misses.
            $result = $doTranslate($translationOptions, $missNonEmptyTexts, $missNonEmptyTexts, $missIndices);

            // Persist miss results to cache, and merge cached translations.
            if ($useCache && is_array($result) && isset($result['translations']) && is_array($result['translations'])) {
                foreach ($missNonEmptyTexts as $i => $normalizedText) {
                    $originalIndex = (int) ($missIndices[$i] ?? -1);
                    if ($originalIndex < 0) {
                        continue;
                    }
                    $translated = $result['translations'][$originalIndex] ?? null;
                    if (!is_string($translated) || $translated === '') {
                        continue;
                    }
                    $key = DeepLTranslationCache::buildKey($sourceLang, $targetLang, $context, $mergedOptions, (string) $normalizedText);
                    DeepLTranslationCache::set($key, $translated);
                }
                foreach ($cachedTranslationsByOriginalIndex as $originalIndex => $translated) {
                    $result['translations'][(int) $originalIndex] = $translated;
                }
            }

            // If the translator returns a soft failure (e.g. test override),
            // retry once without formality when DeepL rejects it for the target language.
            $formalityKey = $hasOptionConstants ? \DeepL\TranslateTextOptions::FORMALITY : 'formality';
            if (
                is_array($result)
                && isset($result['success'])
                && $result['success'] === false
                && isset($translationOptions[$formalityKey])
                && isset($result['error'])
                && is_string($result['error'])
                && stripos($result['error'], 'formality') !== false
                && stripos($result['error'], 'not supported') !== false
            ) {
                $retryOptions = $translationOptions;
                unset($retryOptions[$formalityKey]);
                error_log('Novi content translator: DeepL rejected formality for target ' . $targetLang . ' – retrying without formality.');
                if (function_exists('set_transient')) {
                    $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
                    set_transient(self::buildFormalityUnsupportedCacheKey($targetDeepL), 1, $ttl);
                }
                return $finalizeWithOverrules($doTranslate($retryOptions, $missNonEmptyTexts, $missNonEmptyTexts, $missIndices));
            }

            return $finalizeWithOverrules(is_array($result) ? $result : [
                'success' => false,
                'translations' => $texts,
                'error' => 'DeepL translation failed',
            ]);
        } catch (\Throwable $e) {
            // retry once without formality if DeepL rejects it for this target language (e.g. th)
            $message = (string) $e->getMessage();
            $hasFormality = false;
            foreach (['formality', '\DeepL\TranslateTextOptions::FORMALITY'] as $_) {
                // noop; keep phpstan happy
            }
            $formalityKey = class_exists('\DeepL\TranslateTextOptions') ? \DeepL\TranslateTextOptions::FORMALITY : 'formality';
            if (isset($translationOptions) && is_array($translationOptions) && array_key_exists($formalityKey, $translationOptions)) {
                $hasFormality = true;
            }
            if (
                $hasFormality
                && (stripos($message, 'formality') !== false)
                && (stripos($message, 'not supported') !== false)
            ) {
                try {
                    $retryOptions = $translationOptions;
                    unset($retryOptions[$formalityKey]);
                    error_log('Novi content translator: DeepL rejected formality for target ' . $targetLang . ' – retrying without formality.');
                    if (function_exists('set_transient')) {
                        $ttl = defined('DAY_IN_SECONDS') ? (int) DAY_IN_SECONDS : 86400;
                        set_transient(self::buildFormalityUnsupportedCacheKey($targetDeepL), 1, $ttl);
                    }
                    return $finalizeWithOverrules($doTranslate($retryOptions, $missNonEmptyTexts, $missNonEmptyTexts, $missIndices));
                } catch (\Throwable $retryException) {
                    // fall through to regular error handling below
                    $e = $retryException;
                }
            }

            if (is_a($e, '\DeepL\AuthorizationException')) {
                $errorMessage = __('DeepL API authentication failed. Please check your API key.', 'novi-content-translator');
                error_log('Novi content translator: DeepL authentication error: ' . $e->getMessage());
                return $finalizeWithOverrules([
                    'success' => false,
                    'translations' => $texts,
                    'error' => $errorMessage,
                ]);
            }
            if (is_a($e, '\DeepL\QuotaExceededException')) {
                $errorMessage = __('DeepL API quota exceeded. Please check your account limits.', 'novi-content-translator');
                error_log('Novi content translator: DeepL quota exceeded: ' . $e->getMessage());
                return $finalizeWithOverrules([
                    'success' => false,
                    'translations' => $texts,
                    'error' => $errorMessage,
                ]);
            }

            $errorMessage = sprintf(
                __('DeepL translation failed: %s', 'novi-content-translator'),
                $e->getMessage()
            );
            error_log('Novi content translator: DeepL translation error: ' . $e->getMessage());
            return $finalizeWithOverrules([
                'success' => false,
                'translations' => $texts,
                'error' => $errorMessage,
            ]);
        }
    }

    /**
     * Translate a single text
     * @param string $text Text to translate
     * @param string $sourceLang Source language code (WordPress locale)
     * @param string $targetLang Target language code (WordPress locale)
     * @param array $options Optional translation options (e.g., ['tag_handling' => 'html'])
     * @return string Translated text or original if translation fails
     */
    public static function translateText(string $text, string $sourceLang, string $targetLang, array $options = []): string
    {
        $result = self::translateTexts([$text], $sourceLang, $targetLang, $options);
        return $result['translations'][0] ?? $text;
    }

    /**
     * DeepL HTML tag_handling strips NBSP and other unicode spaces adjacent to tags
     * (e.g. "om\u00a0<strong>het hoe</strong>" → "about<strong>the how</strong>"),
     * while ASCII spaces are preserved. Gutenberg often inserts NBSP around inline marks.
     */
    private static function normalizeHtmlWhitespace(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        //nbsp, ogham space, en/em/thin/hair spaces, narrow nbsp, math space, ideographic space
        $normalized = preg_replace(
            '/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u',
            ' ',
            $html
        );

        return is_string($normalized) ? $normalized : $html;
    }

    /**
     * @param array<int, mixed> $texts
     */
    private static function textsContainHtmlAnchors(array $texts): bool
    {
        foreach ($texts as $text) {
            if (is_string($text) && stripos($text, '<a') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Strip Chrome #:~:text= fragments from href attributes (and bare occurrences).
     */
    public static function stripTextFragmentsFromHtml(string $html): string
    {
        if ($html === '' || !str_contains($html, '#:~:text=')) {
            return $html;
        }

        //href="…#:~:text=…" / href='…'
        $html = preg_replace(
            '/(href\s*=\s*(["\']))([^"\']*?)#:~:text=[^"\']*(\2)/i',
            '$1$3$4',
            $html
        ) ?? $html;
        //unicode-escaped Gutenberg JSON: href\u0022…#:~:text=…\u0022
        $html = preg_replace(
            '/(href\\\\u002([27]))(.*?)#:~:text=.*?(\\\\u002\2)/i',
            '$1$3$4',
            $html
        ) ?? $html;
        //leftover bare fragments (rare)
        $html = preg_replace('/#:~:text=[^"\'\s<]*/i', '', $html) ?? $html;

        return $html;
    }

    /**
     * Insert missing spaces when DeepL glues words to inline tags
     * (e.g. "</a></strong>ist" → "</a></strong> ist", "word<a" → "word <a").
     */
    public static function ensureInlineTagWhitespace(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        //letter/digit/punct before opening inline tag
        $html = preg_replace(
            '/([\p{L}\p{N}\.,;:!?])(<(?:a|strong|em|b|i)\b)/u',
            '$1 $2',
            $html
        ) ?? $html;
        //closing inline tag immediately followed by a word character
        $html = preg_replace(
            '/(<\/(?:a|strong|em|b|i)>)(\p{L}|\p{N})/u',
            '$1 $2',
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Collapse nearby <a> tags that share the same href into one anchor.
     * DeepL sometimes re-wraps mid-phrase words as extra anchors, including with
     * plain text between them ("…wird </a> als Formalität <a>betrachtet").
     * Only merges when the gap has no block-level / other-link markup.
     */
    public static function collapseConsecutiveSameHrefAnchors(string $html): string
    {
        if ($html === '' || stripos($html, '<a') === false) {
            return $html;
        }

        $previous = null;
        $guard = 0;
        while ($html !== $previous && $guard < 20) {
            $previous = $html;
            $guard++;
            $replaced = preg_replace_callback(
                '/<a\b([^>]*\bhref\s*=\s*(["\'])([^"\']*)\2[^>]*)>(.*?)<\/a>'
                . '((?:[^<]|<(?!\/?a\b)\/?(?:strong|em|b|i|br|span)(?:\s[^>]*)?>)*)'
                . '<a\b([^>]*\bhref\s*=\s*\2\3\2[^>]*)>(.*?)<\/a>/is',
                static function (array $m): string {
                    $attrs = trim((string) ($m[1] ?? ''));
                    $left = (string) ($m[4] ?? '');
                    $mid = (string) ($m[5] ?? '');
                    $right = (string) ($m[7] ?? '');
                    //refuse merge when gap looks like a new sentence/block boundary with long content
                    $midText = trim(function_exists('wp_strip_all_tags') ? \wp_strip_all_tags($mid) : strip_tags($mid));
                    if (mb_strlen($midText) > 80) {
                        return (string) ($m[0] ?? '');
                    }
                    if ($midText === ''
                        && $left !== ''
                        && $right !== ''
                        && !preg_match('/\s$/u', $left)
                        && !preg_match('/^\s/u', $right)
                        && !preg_match('/[\p{P}]$/u', $left)
                    ) {
                        $mid = ' ';
                    }
                    return '<a ' . $attrs . '>' . $left . $mid . $right . '</a>';
                },
                $html
            );
            if (is_string($replaced)) {
                $html = $replaced;
            }
        }

        return $html;
    }

    /**
     * Protect <a> tags for DeepL HTML mode: keep inner HTML, drop real attrs (especially href)
     * so DeepL cannot strip Maps/short-link URLs or empty the label.
     *
     * @return array{0: string, 1: array<int, array{attrs: string, inner: string}>}
     */
    private static function protectHtmlAnchors(string $html): array
    {
        if ($html === '' || stripos($html, '<a') === false) {
            return [$html, []];
        }

        $map = [];
        $nextId = 0;
        $protected = preg_replace_callback(
            '/<a\b([^>]*)>(.*?)<\/a>/is',
            static function (array $m) use (&$map, &$nextId): string {
                $id = $nextId++;
                $attrs = trim((string) ($m[1] ?? ''));
                if ($attrs !== '' && str_contains($attrs, '#:~:text=')) {
                    $attrs = preg_replace(
                        '/(href\s*=\s*(["\']))([^"\']*?)#:~:text=[^"\']*(\2)/i',
                        '$1$3$4',
                        $attrs
                    ) ?? $attrs;
                }
                $map[$id] = [
                    'attrs' => $attrs,
                    'inner' => (string) ($m[2] ?? ''),
                ];
                return '<a ' . self::ANCHOR_PROTECT_ATTR . '="' . $id . '">' . $map[$id]['inner'] . '</a>';
            },
            $html
        );

        return [is_string($protected) ? $protected : $html, $map];
    }

    /**
     * @param array<int, string> $translations
     * @param array<int, array<int, array{attrs: string, inner: string}>> $anchorMapsByOriginalIndex
     * @return array<int, string>
     */
    private static function restoreHtmlAnchorsInTranslations(
        array $translations,
        array $anchorMapsByOriginalIndex,
        string $sourceLang,
        string $targetLang
    ): array {
        $healByKey = [];

        foreach ($anchorMapsByOriginalIndex as $originalIndex => $map) {
            if (!isset($translations[$originalIndex]) || !is_string($translations[$originalIndex])) {
                continue;
            }
            [$restored, $needsHeal] = self::restoreHtmlAnchors(
                (string) $translations[$originalIndex],
                $map
            );
            $translations[$originalIndex] = $restored;
            foreach ($needsHeal as $anchorId => $originalInner) {
                $healByKey[$originalIndex . ':' . $anchorId] = $originalInner;
            }
        }

        if ($healByKey === []) {
            return $translations;
        }

        $healKeys = array_keys($healByKey);
        $healTexts = array_values($healByKey);

        $previousSkip = self::$skipHtmlAnchorProtect;
        self::$skipHtmlAnchorProtect = true;
        try {
            $healed = self::translateTexts($healTexts, $sourceLang, $targetLang, ['context' => 'plain']);
        } finally {
            self::$skipHtmlAnchorProtect = $previousSkip;
        }

        $healedTranslations = (isset($healed['translations']) && is_array($healed['translations']))
            ? $healed['translations']
            : $healTexts;

        foreach ($healKeys as $i => $key) {
            $parts = explode(':', (string) $key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $originalIndex = (int) $parts[0];
            $anchorId = (int) $parts[1];
            if (!isset($translations[$originalIndex]) || !is_string($translations[$originalIndex])) {
                continue;
            }
            $replacement = isset($healedTranslations[$i]) && is_string($healedTranslations[$i])
                ? $healedTranslations[$i]
                : (string) ($healByKey[$key] ?? '');
            $replacementText = function_exists('wp_strip_all_tags')
                ? \wp_strip_all_tags($replacement)
                : strip_tags($replacement);
            if (trim($replacementText) === '') {
                $replacement = (string) ($healByKey[$key] ?? '');
            }
            $token = '%%NCT_A_HEAL_' . $anchorId . '%%';
            $translations[$originalIndex] = str_replace($token, $replacement, (string) $translations[$originalIndex]);
        }

        return $translations;
    }

    /**
     * Restore protected anchors and collect emptied labels that need a plain-text re-translate.
     *
     * @param array<int, array{attrs: string, inner: string}> $map
     * @return array{0: string, 1: array<int, string>} html + anchorId => original inner
     */
    private static function restoreHtmlAnchors(string $html, array $map): array
    {
        if ($html === '' || $map === []) {
            return [$html, []];
        }

        $needsHeal = [];

        $rebuild = static function (string $attrs, string $newInner, string $originalInner, int $id) use (&$needsHeal): string {
            $attrs = trim($attrs);
            //drop our protect marker if DeepL left it on the tag
            $attrs = preg_replace(
                '/\s*' . preg_quote(self::ANCHOR_PROTECT_ATTR, '/') . '\s*=\s*(["\']?)\d+\1/i',
                '',
                $attrs
            ) ?? $attrs;
            $attrs = trim((string) $attrs);

            //DeepL sometimes entity-encodes the opening ">" into the label ("&gt;Strategie")
            $newInner = preg_replace('/^(?:&gt;|>)+/i', '', $newInner) ?? $newInner;

            $newText = trim(function_exists('wp_strip_all_tags') ? \wp_strip_all_tags($newInner) : strip_tags($newInner));
            $originalText = trim(function_exists('wp_strip_all_tags') ? \wp_strip_all_tags($originalInner) : strip_tags($originalInner));
            //empty or punctuation-only label after DeepL corruption → re-translate plain
            $newTextMeaningful = preg_replace('/[\s\p{P}\p{S}]+/u', '', $newText) ?? $newText;
            if ($newTextMeaningful === '' && $originalText !== '') {
                $needsHeal[$id] = $originalInner;
                $newInner = '%%NCT_A_HEAL_' . $id . '%%';
            }

            $open = $attrs !== '' ? '<a ' . $attrs . '>' : '<a>';
            return $open . $newInner . '</a>';
        };

        //prefer marker-based restore when DeepL kept data-nct-a
        if (str_contains($html, self::ANCHOR_PROTECT_ATTR)) {
            $restored = preg_replace_callback(
                '/<a\b([^>]*)\b' . preg_quote(self::ANCHOR_PROTECT_ATTR, '/') . '\s*=\s*(["\']?)(\d+)\2([^>]*)>(.*?)<\/a>/is',
                static function (array $m) use ($map, $rebuild): string {
                    $id = (int) $m[3];
                    $entry = $map[$id] ?? null;
                    $attrs = is_array($entry) ? (string) ($entry['attrs'] ?? '') : '';
                    $originalInner = is_array($entry) ? (string) ($entry['inner'] ?? '') : '';
                    $newInner = (string) ($m[5] ?? '');
                    return $rebuild($attrs, $newInner, $originalInner, $id);
                },
                $html
            );
            return [is_string($restored) ? $restored : $html, $needsHeal];
        }

        //fallback: DeepL dropped the marker — zip anchors by document order
        $idList = array_keys($map);
        sort($idList, SORT_NUMERIC);
        $i = 0;
        $restored = preg_replace_callback(
            '/<a\b([^>]*)>(.*?)<\/a>/is',
            static function (array $m) use ($map, &$i, $idList, $rebuild): string {
                if (!isset($idList[$i])) {
                    return (string) $m[0];
                }
                $id = (int) $idList[$i];
                $i++;
                $entry = $map[$id] ?? null;
                $attrs = is_array($entry) ? (string) ($entry['attrs'] ?? '') : trim((string) ($m[1] ?? ''));
                $originalInner = is_array($entry) ? (string) ($entry['inner'] ?? '') : '';
                $newInner = (string) ($m[2] ?? '');
                return $rebuild($attrs, $newInner, $originalInner, $id);
            },
            $html
        );

        return [is_string($restored) ? $restored : $html, $needsHeal];
    }

    /**
     * Normalize em/en dash spacing in translations.
     *
     * DeepL often emits Chicago-style unspaced em dashes ("change—from", "risks—for"),
     * including when the Dutch source used periods or spaced dashes ("kaart — voor").
     * Prefer spaced dashes to match site typography. Does not touch hyphen-minus.
     */
    private static function normalizeSpacedDashes(string $source, string $translation): string
    {
        unset($source);
        if ($translation === '' || preg_match('/[—–]/u', $translation) !== 1) {
            return $translation;
        }

        $normalized = preg_replace('/(\S)\s*([—–])\s*(\S)/u', '$1 $2 $3', $translation);
        return is_string($normalized) ? $normalized : $translation;
    }

    /**
     * Strip DeepL-inserted commas immediately before/after <br> when the source had none.
     *
     * NL multi-line addresses often use "Street 4<br>1234AB City". DeepL HTML mode
     * frequently rewrites that toward English punctuation as either
     * "Street 4<br>, 1234AB City" or "Street 4,<br>1234AB City". Leave commas alone
     * when the source already had a comma next to <br>.
     */
    public static function stripSpuriousBreakCommas(string $source, string $translation): string
    {
        if ($translation === '' || !preg_match('/,\s*<br\s*\/?>|<br\s*\/?>\s*,/i', $translation)) {
            return $translation;
        }
        if (preg_match('/,\s*<br\s*\/?>|<br\s*\/?>\s*,/i', $source)) {
            return $translation;
        }

        $normalized = preg_replace('/,\s*(<br\s*\/?>)/i', '$1', $translation);
        $normalized = is_string($normalized) ? $normalized : $translation;
        $normalized = preg_replace('/(<br\s*\/?>)\s*,\s*/i', '$1', $normalized);
        return is_string($normalized) ? $normalized : $translation;
    }

    /**
     * DeepL has no API flag to force sentence case. It often Title-Cases short English
     * phrases (e.g. "Samen verder komen" → "Moving Forward Together") even with
     * preserve_formatting=true (that option only affects sentence-start casing/punctuation).
     *
     * Prefer aligning to the source casing pattern when word counts match: lowercase
     * source words force corresponding translated words to lowercase (keeps ALL-CAPS
     * tokens). Falls back to Title Case → sentence case when counts diverge —
     * including NL compounds that expand to multi-word EN ("Succesverhalen" → "Success Stories")
     * and EN Title Case that leaves connectors lowercase ("First Name and Last Name").
     * Skips HTML, intentional Title Case / ALL CAPS sources, and long/ambiguous strings.
     */
    private static function normalizeUnexpectedTitleCase(string $source, string $translation): string
    {
        if ($source === '' || $translation === '' || $source === $translation) {
            return $translation;
        }

        //do not rewrite markup payloads
        if (str_contains($source, '<') || str_contains($translation, '<')) {
            return $translation;
        }

        if (self::isAllCapsPhrase($source) || self::isAllCapsPhrase($translation)) {
            return $translation;
        }

        //respect intentional Title Case in the source
        if (self::isTitleCasePhrase($source)) {
            return $translation;
        }

        if (!self::looksLikeSentenceOrLowerCasePhrase($source)) {
            return $translation;
        }

        $sourceWordCount = self::countLetterWords($source);
        $translationWordCount = self::countLetterWords($translation);
        //need a multi-word translation to rewrite; single-word NL compounds may expand (1→N)
        if ($sourceWordCount < 1 || $translationWordCount < 2) {
            return $translation;
        }

        //per-word alignment handles mixed Title Case ("Partners in Positive Impact")
        if ($sourceWordCount === $translationWordCount && $sourceWordCount <= 12) {
            return self::applySourceLowercasePattern($source, $translation);
        }

        //fallback when word counts diverge (incl. 1→N compounds): rewrite clear Title Case,
        //including EN style that leaves small words lowercase ("First Name and Last Name")
        if (!self::isTitleCasePhrase($translation) && !self::looksLikeMostlyTitleCasePhrase($translation)) {
            return $translation;
        }

        if ($translationWordCount > 12) {
            return $translation;
        }

        return self::toSentenceCasePhrase($translation);
    }

    /**
     * True when most letter-words look Title-Cased, allowing a minority of fully-lowercase
     * connector words (and/or/the/of/…). Used when NL→EN word counts diverge so strict
     * isTitleCasePhrase() would miss DeepL's English Title Case style.
     * ALL-CAPS tokens (USA, KIEM, test [NL] prefixes) are ignored for the majority check.
     * Non-connector lowercase words (e.g. "amp" inside &amp;) reject the phrase.
     */
    private static function looksLikeMostlyTitleCasePhrase(string $text): bool
    {
        $words = self::splitLetterWords($text);
        if (count($words) < 2) {
            return false;
        }

        $properTitleCased = 0;
        $connectorLower = 0;
        foreach ($words as $word) {
            if (self::isFullyLowercaseWord($word)) {
                if (!self::isLikelyTitleCaseConnector($word)) {
                    return false;
                }
                $connectorLower++;
                continue;
            }

            //ignore ALL-CAPS brand / locale tokens so they do not inflate the majority
            if (mb_strlen($word) > 1 && $word === mb_strtoupper($word)) {
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

            $properTitleCased++;
        }

        return $properTitleCased >= 2 && $properTitleCased > $connectorLower;
    }

    /**
     * Small words DeepL often leaves lowercase inside otherwise Title-Cased English.
     */
    private static function isLikelyTitleCaseConnector(string $word): bool
    {
        static $connectors = [
            'a' => true,
            'an' => true,
            'the' => true,
            'and' => true,
            'or' => true,
            'but' => true,
            'nor' => true,
            'as' => true,
            'at' => true,
            'by' => true,
            'for' => true,
            'in' => true,
            'of' => true,
            'on' => true,
            'per' => true,
            'to' => true,
            'up' => true,
            'via' => true,
            'vs' => true,
            'with' => true,
            'from' => true,
            'into' => true,
            'onto' => true,
            'over' => true,
            'than' => true,
            'that' => true,
            'if' => true,
            'und' => true,
            'oder' => true,
            'en' => true,
            'van' => true,
            'de' => true,
            'het' => true,
            'von' => true,
            'zu' => true,
            'im' => true,
            'am' => true,
            'der' => true,
            'die' => true,
            'das' => true,
        ];

        return isset($connectors[mb_strtolower($word)]);
    }

    /**
     * Force translated words to lowercase wherever the aligned source word was lowercase.
     * Leaves capitalized source positions and ALL-CAPS translation tokens untouched.
     */
    private static function applySourceLowercasePattern(string $source, string $translation): string
    {
        $sourceWords = self::splitLetterWords($source);
        $parts = preg_split('/(\p{L}[\p{L}\p{N}\'’-]*)/u', $translation, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $translation;
        }

        $wordIndex = 0;
        $out = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\p{L}/u', $part) !== 1) {
                $out .= $part;
                continue;
            }

            $sourceWord = $sourceWords[$wordIndex] ?? null;
            $wordIndex++;

            if ($sourceWord === null) {
                $out .= $part;
                continue;
            }

            //keep intentional ALL-CAPS tokens (USA, KIEM, FAQ, …)
            if (mb_strlen($part) > 1 && $part === mb_strtoupper($part)) {
                $out .= $part;
                continue;
            }

            if (self::isFullyLowercaseWord($sourceWord)) {
                $out .= mb_strtolower($part);
                continue;
            }

            $out .= $part;
        }

        return $out;
    }

    private static function isFullyLowercaseWord(string $word): bool
    {
        if ($word === '' || preg_match('/\p{L}/u', $word) !== 1) {
            return false;
        }
        return $word === mb_strtolower($word);
    }

    /**
     * @return list<string>
     */
    private static function splitLetterWords(string $text): array
    {
        if (!preg_match_all('/\p{L}[\p{L}\p{N}\'’-]*/u', $text, $matches) || !isset($matches[0])) {
            return [];
        }
        return array_values(array_filter($matches[0], static fn(string $w): bool => $w !== ''));
    }

    private static function countLetterWords(string $text): int
    {
        return count(self::splitLetterWords($text));
    }

    private static function isAllCapsPhrase(string $text): bool
    {
        $words = self::splitLetterWords($text);
        if ($words === []) {
            return false;
        }
        foreach ($words as $word) {
            if ($word !== mb_strtoupper($word)) {
                return false;
            }
        }
        return true;
    }

    private static function isTitleCasePhrase(string $text): bool
    {
        $words = self::splitLetterWords($text);
        if (count($words) < 2) {
            return false;
        }
        foreach ($words as $word) {
            $first = mb_substr($word, 0, 1);
            $rest = mb_substr($word, 1);
            if ($first !== mb_strtoupper($first)) {
                return false;
            }
            //allow ALL-CAPS brand tokens inside an otherwise Title Case phrase
            if ($rest !== '' && $rest === mb_strtoupper($rest) && preg_match('/\p{L}/u', $rest)) {
                continue;
            }
            if ($rest !== '' && $rest !== mb_strtolower($rest)) {
                return false;
            }
        }
        return true;
    }

    /**
     * True when the source looks sentence-case or mostly-lowercase (not Title Case).
     * Single words count when fully lowercase, typical heading shape (First upper, rest lower),
     * or they contain lowercase letters (e.g. "CO₂-reductieplan") so NL compounds can still
     * trigger EN Title Case cleanup after 1→N expansion.
     */
    private static function looksLikeSentenceOrLowerCasePhrase(string $text): bool
    {
        $words = self::splitLetterWords($text);
        if ($words === []) {
            return false;
        }

        if (count($words) === 1) {
            $word = $words[0];
            if (self::isSentenceCaseStyleWord($word)) {
                return true;
            }
            //hyphenated / chemical prefixes: has lowercase letters but not a clean First+lower shape
            return self::wordContainsLowercaseLetter($word);
        }

        foreach ($words as $word) {
            $first = mb_substr($word, 0, 1);
            //lowercase letter start (not a caseless script glyph)
            if ($first === mb_strtolower($first) && $first !== mb_strtoupper($first)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fully lowercase, or first letter upper + remaining letters lower (NL heading / sentence start).
     */
    private static function isSentenceCaseStyleWord(string $word): bool
    {
        if ($word === '' || preg_match('/\p{L}/u', $word) !== 1) {
            return false;
        }
        if (self::isFullyLowercaseWord($word)) {
            return true;
        }

        $first = mb_substr($word, 0, 1);
        $rest = mb_substr($word, 1);
        if ($first !== mb_strtoupper($first) || $first === mb_strtolower($first)) {
            return false;
        }
        if ($rest === '') {
            return true;
        }

        return $rest === mb_strtolower($rest);
    }

    private static function wordContainsLowercaseLetter(string $word): bool
    {
        if ($word === '' || !preg_match_all('/\p{L}/u', $word, $matches) || !isset($matches[0])) {
            return false;
        }
        foreach ($matches[0] as $letter) {
            if ($letter === mb_strtolower($letter) && $letter !== mb_strtoupper($letter)) {
                return true;
            }
        }
        return false;
    }

    private static function toSentenceCasePhrase(string $text): string
    {
        $parts = preg_split('/(\p{L}[\p{L}\p{N}\'’-]*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $text;
        }

        $out = '';
        $sawLetter = false;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\p{L}/u', $part) !== 1) {
                $out .= $part;
                continue;
            }

            //keep intentional ALL-CAPS tokens (USA, FAQ, …)
            $isAllCaps = mb_strlen($part) > 1 && $part === mb_strtoupper($part);
            $word = $isAllCaps ? $part : mb_strtolower($part);
            if (!$sawLetter) {
                if (!$isAllCaps) {
                    $word = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
                }
                $sawLetter = true;
            }
            $out .= $word;
        }

        return $out;
    }

    /**
     * Build default DeepL options for a given context and target language.
     * These defaults can later be driven by Novi settings stored in WordPress options.
     *
     * @param string $context Hint about what is being translated ('plain' or 'html').
     * @param string $targetLang Target language code (WordPress locale, e.g. 'nl', 'de_DE').
     * @return array
     */
    private static function getDefaultOptions(string $context, string $targetLang): array
    {
        // Read Novi settings once per request; fall back to hard-coded defaults
        $rawSettings = function_exists('get_option')
            ? (array) \get_option('novi_content_translator_settings', [])
            : [];

        //formality is the only setting; split_sentences and preserve_formatting use hardcoded defaults
        $formality = 'default';
        if (isset($rawSettings['formality']) && in_array($rawSettings['formality'], ['default', 'more', 'less'], true)) {
            $formality = $rawSettings['formality'];
        }

        $options = [];

        if ($formality === 'more' || $formality === 'less') {
            $options['formality'] = $formality;
        }

        $options['split_sentences'] = 'nonewlines';
        $options['preserve_formatting'] = true;

        // Context-specific defaults
        if ($context === 'html') {
            $options['tag_handling'] = 'html';
        }

        // Placeholders for future glossary and tag configuration driven by settings
        // $options['glossary_id'] = $rawSettings['glossary_id'] ?? null;
        // $options['non_splitting_tags'] = $rawSettings['non_splitting_tags'] ?? null;
        // $options['ignore_tags'] = $rawSettings['ignore_tags'] ?? null;

        return $options;
    }
}

