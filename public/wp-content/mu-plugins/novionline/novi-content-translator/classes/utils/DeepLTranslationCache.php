<?php

namespace NoviOnline\ContentTranslator\Core;

/**
 * DeepL translation transient cache (opt-in).
 *
 * Stores only the translated string, keyed by an md5 hash containing:
 * - source language
 * - target language
 * - original text
 * plus context/options to avoid collisions across different translation modes.
 *
 * This cache is intended to be enabled for bulk CLI runs to reduce repeated DeepL API usage.
 */
final class DeepLTranslationCache
{
    private const TRANSLATION_TRANSIENT_PREFIX = 'nct_deepl_t_';

    private static bool $enabled = false;
    private static int $ttlSeconds = 0;

    public static function enable(int $ttlSeconds): void
    {
        self::$enabled = true;
        self::$ttlSeconds = max(0, (int) $ttlSeconds);
    }

    public static function disable(): void
    {
        self::$enabled = false;
        self::$ttlSeconds = 0;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled && self::$ttlSeconds > 0;
    }

    public static function getTtlSeconds(): int
    {
        return self::$ttlSeconds;
    }

    /**
     * Build the transient key for one translation input.
     */
    public static function buildKey(string $sourceLang, string $targetLang, string $context, array $mergedOptions, string $text): string
    {
        $encoder = function_exists('wp_json_encode') ? 'wp_json_encode' : 'json_encode';
        $optionsJson = (string) $encoder($mergedOptions);

        // user requirement: md5 must include source language, target language and origin text
        $payload = strtolower(trim($sourceLang)) . '|' . strtolower(trim($targetLang)) . '|' . $text;
        // include context/options too to prevent collisions across different translation modes
        $payload .= '|' . strtolower(trim($context)) . '|' . $optionsJson;

        return self::TRANSLATION_TRANSIENT_PREFIX . md5($payload);
    }

    public static function get(string $key): string
    {
        if (!self::isEnabled() || !function_exists('get_transient')) {
            return '';
        }
        $cached = get_transient($key);
        return is_string($cached) ? $cached : '';
    }

    public static function set(string $key, string $translatedText): void
    {
        if (!self::isEnabled() || !function_exists('set_transient')) {
            return;
        }
        if ($translatedText === '') {
            return;
        }
        set_transient($key, $translatedText, self::$ttlSeconds);
    }

    /**
     * Delete translation cache entries whose stored value contains any of the needles.
     *
     * Used when string overrules change so stale cached translations cannot stick around.
     *
     * @param array<int, string> $needles
     */
    public static function deleteTransientsContaining(array $needles): int
    {
        $normalizedNeedles = [];
        foreach ($needles as $needle) {
            $needle = trim((string) $needle);
            if ($needle === '') {
                continue;
            }
            $normalizedNeedles[$needle] = $needle;
        }
        $normalizedNeedles = array_values($normalizedNeedles);
        if ($normalizedNeedles === []) {
            return 0;
        }

        // unit-test transient store
        if (isset($GLOBALS['__nct_transients']) && is_array($GLOBALS['__nct_transients'])) {
            $deleted = 0;
            foreach ($GLOBALS['__nct_transients'] as $key => $entry) {
                if (!is_string($key) || !str_starts_with($key, self::TRANSLATION_TRANSIENT_PREFIX)) {
                    continue;
                }
                $value = '';
                if (is_array($entry) && array_key_exists('value', $entry)) {
                    $value = is_string($entry['value']) ? $entry['value'] : '';
                } elseif (is_string($entry)) {
                    $value = $entry;
                }
                if ($value === '' || !self::valueContainsAnyNeedle($value, $normalizedNeedles)) {
                    continue;
                }
                unset($GLOBALS['__nct_transients'][$key]);
                $deleted++;
            }
            return $deleted;
        }

        if (!function_exists('delete_transient')) {
            return 0;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_col')) {
            return 0;
        }

        $like = $wpdb->esc_like('_transient_' . self::TRANSLATION_TRANSIENT_PREFIX) . '%';
        $optionNames = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
        if (!is_array($optionNames) || $optionNames === []) {
            return 0;
        }

        $deleted = 0;
        foreach ($optionNames as $optionName) {
            if (!is_string($optionName) || !str_starts_with($optionName, '_transient_')) {
                continue;
            }
            $transientKey = substr($optionName, strlen('_transient_'));
            if (!str_starts_with($transientKey, self::TRANSLATION_TRANSIENT_PREFIX)) {
                continue;
            }
            $value = function_exists('get_option') ? \get_option($optionName) : false;
            $haystack = is_string($value) ? $value : '';
            if ($haystack === '' || !self::valueContainsAnyNeedle($haystack, $normalizedNeedles)) {
                continue;
            }
            delete_transient($transientKey);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @param array<int, string> $needles
     */
    private static function valueContainsAnyNeedle(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($value, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
